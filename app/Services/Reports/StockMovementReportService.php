<?php

namespace App\Services\Reports;

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

/**
 * Stock Movement Report — READ-ONLY.
 *
 * Single source of truth: stock_logs table only. No joins to orders/purchases;
 * no stock writes, no accounting, no expenses.
 *
 * Running balance: per (product_id, shop_id), chronological by created_at then id.
 * IN increases balance, OUT decreases. Balance is computed in SQL via window sum
 * and is not stored.
 */
class StockMovementReportService
{
    /** Default page size; max allowed for performance. */
    public const DEFAULT_PAGE_SIZE = 50;
    public const MAX_PAGE_SIZE = 500;

    /**
     * Build report: filtered rows with qty_in, qty_out, running balance.
     *
     * @param array $filters [ from_date, to_date, product_id?, shop_id?, movement_type? (source_type), user_id? ]
     * @param int $page 1-based page
     * @param int $perPage page size (capped at MAX_PAGE_SIZE)
     * @return array{ data: array<int, array>, meta: array{ total: int, per_page: int, current_page: int, last_page: int } }
     */
    public function getReport(array $filters, int $page = 1, int $perPage = self::DEFAULT_PAGE_SIZE): array
    {
        $perPage = max(1, min($perPage, self::MAX_PAGE_SIZE));
        $page = max(1, $page);
        $offset = ($page - 1) * $perPage;

        $sort = $filters['sort'] ?? 'id';
        $order = strtolower($filters['order'] ?? 'desc') === 'asc' ? 'asc' : 'desc';
        $allowedSort = ['id', 'date', 'product_name', 'product_code'];
        if (!in_array($sort, $allowedSort, true)) {
            $sort = 'id';
        }
        $orderByClause = $this->buildOrderBy($sort, $order);

        $bindings = [];
        $where = $this->buildWhere($filters, $bindings, 'sl');

        $countSql = "SELECT COUNT(*) AS total FROM stock_logs sl WHERE {$where}";
        $total = (int) (DB::selectOne($countSql, $bindings)->total ?? 0);
        $lastPage = $total > 0 ? (int) ceil($total / $perPage) : 1;

        $dataSql = "
            SELECT
                sl.id,
                sl.product_id,
                sl.shop_id,
                p.product_name,
                p.product_code,
                COALESCE(sl.adjustment_date, sl.created_at) AS date,
                sl.direction,
                sl.source_type AS movement_type,
                sl.source_id,
                ref_p.purchase_no AS ref_purchase_no,
                ref_o.invoice_no AS ref_invoice_no,
                COALESCE(sl.qty, 0) AS qty,
                CASE WHEN sl.direction = 'in' THEN COALESCE(sl.qty, 0) ELSE 0 END AS qty_in,
                CASE WHEN sl.direction = 'out' THEN COALESCE(sl.qty, 0) ELSE 0 END AS qty_out,
                SUM(
                    CASE WHEN sl.direction = 'in' THEN COALESCE(sl.qty, 0) ELSE -COALESCE(sl.qty, 0) END
                ) OVER (
                    PARTITION BY sl.product_id, COALESCE(sl.shop_id, 0)
                    ORDER BY sl.created_at ASC, sl.id ASC
                    ROWS BETWEEN UNBOUNDED PRECEDING AND CURRENT ROW
                ) AS balance
            FROM stock_logs sl
            JOIN products p ON p.id = sl.product_id
            LEFT JOIN purchases ref_p ON sl.source_type = 'purchase' AND sl.source_id = ref_p.id
            LEFT JOIN orders ref_o ON sl.source_type = 'sale' AND sl.source_id = ref_o.id
            WHERE {$where}
            ORDER BY {$orderByClause}
            LIMIT ? OFFSET ?
        ";

        $rows = [];
        try {
            $rows = DB::select($dataSql, array_merge($bindings, [$perPage, $offset]));
        } catch (QueryException $e) {
            // Fallback when window functions are not supported (older MySQL/MariaDB).
            $rows = $this->getRowsWithoutWindow($where, $bindings, $perPage, $offset, $orderByClause);
        }

        $data = [];
        foreach ($rows as $row) {
            $data[] = [
                'id'            => (int) $row->id,
                'product_id'    => (int) $row->product_id,
                'shop_id'       => $row->shop_id !== null ? (int) $row->shop_id : null,
                'product_name'  => $row->product_name ?? null,
                'product_code'  => $row->product_code ?? null,
                'date'          => Carbon::parse($row->date)->toIso8601String(),
                'reference'     => $this->buildReference(
                    $row->movement_type ?? null,
                    $row->source_id ?? null,
                    $row->ref_purchase_no ?? null,
                    $row->ref_invoice_no ?? null
                ),
                'movement_type' => $row->movement_type ?? null,
                'qty_in'        => (int) ($row->qty_in ?? 0),
                'qty_out'       => (int) ($row->qty_out ?? 0),
                'balance'       => (int) ($row->balance ?? 0),
            ];
        }

        return [
            'data' => $data,
            'meta' => [
                'total'        => $total,
                'per_page'     => $perPage,
                'current_page' => $page,
                'last_page'    => $lastPage,
            ],
        ];
    }

    /**
     * Build ORDER BY clause for sort column and direction (no user input in values).
     */
    private function buildOrderBy(string $sort, string $order): string
    {
        $dir = strtoupper($order) === 'ASC' ? 'ASC' : 'DESC';
        $column = match ($sort) {
            'date' => 'COALESCE(sl.adjustment_date, sl.created_at)',
            'product_name' => 'p.product_name',
            'product_code' => 'p.product_code',
            default => 'sl.id',
        };
        return "{$column} {$dir}, sl.id {$dir}";
    }

    /**
     * Fallback when window functions are not available.
     * NOTE: Balance is computed within the returned page only (sufficient for basic visibility).
     */
    private function getRowsWithoutWindow(string $where, array $bindings, int $perPage, int $offset, string $orderByClause = 'sl.id DESC, sl.id DESC'): array
    {
        $sql = "
            SELECT
                sl.id,
                sl.product_id,
                sl.shop_id,
                p.product_name,
                p.product_code,
                COALESCE(sl.adjustment_date, sl.created_at) AS date,
                sl.direction,
                sl.source_type AS movement_type,
                sl.source_id,
                ref_p.purchase_no AS ref_purchase_no,
                ref_o.invoice_no AS ref_invoice_no,
                COALESCE(sl.qty, 0) AS qty,
                CASE WHEN sl.direction = 'in' THEN COALESCE(sl.qty, 0) ELSE 0 END AS qty_in,
                CASE WHEN sl.direction = 'out' THEN COALESCE(sl.qty, 0) ELSE 0 END AS qty_out
            FROM stock_logs sl
            JOIN products p ON p.id = sl.product_id
            LEFT JOIN purchases ref_p ON sl.source_type = 'purchase' AND sl.source_id = ref_p.id
            LEFT JOIN orders ref_o ON sl.source_type = 'sale' AND sl.source_id = ref_o.id
            WHERE {$where}
            ORDER BY {$orderByClause}
            LIMIT ? OFFSET ?
        ";
        $rows = DB::select($sql, array_merge($bindings, [$perPage, $offset]));

        // Running balance: process in chronological order (oldest first)
        $chrono = array_reverse($rows);
        $running = [];
        foreach ($chrono as $r) {
            $key = ((int) $r->product_id) . '|' . (string) ($r->shop_id ?? 0);
            $delta = strtolower((string) $r->direction) === 'in' ? (int) $r->qty : -(int) $r->qty;
            $running[$key] = ($running[$key] ?? 0) + $delta;
            $r->balance = $running[$key];
        }
        // Return newest first (same order as query)
        return $rows;
    }

    /**
     * Build WHERE clause and bindings from filters.
     * user_id is accepted but not applied (stock_logs has no user_id column).
     */
    private function buildWhere(array $filters, array &$bindings, string $alias = 'sl'): string
    {
        $conditions = ['1 = 1', "{$alias}.deleted_at IS NULL"];

        // Date filter now applies on adjustment_date (backfilled for sale/purchase),
        // so movement reports align with business dates instead of raw created_at.
        if (!empty($filters['from_date'])) {
            $conditions[] = "{$alias}.adjustment_date >= ?";
            $bindings[] = Carbon::parse($filters['from_date'])->startOfDay()->toDateTimeString();
        }
        if (!empty($filters['to_date'])) {
            $conditions[] = "{$alias}.adjustment_date <= ?";
            $bindings[] = Carbon::parse($filters['to_date'])->endOfDay()->toDateTimeString();
        }
        if (isset($filters['product_id']) && $filters['product_id'] !== '' && $filters['product_id'] !== null) {
            $conditions[] = "{$alias}.product_id = ?";
            $bindings[] = $filters['product_id'];
        }
        if (isset($filters['shop_ids']) && is_array($filters['shop_ids'])) {
            if (count($filters['shop_ids']) === 0) {
                $conditions[] = '1 = 0';
            } else {
                $placeholders = implode(',', array_fill(0, count($filters['shop_ids']), '?'));
                $conditions[] = "{$alias}.shop_id IN ({$placeholders})";
                foreach ($filters['shop_ids'] as $sid) {
                    $bindings[] = $sid;
                }
            }
        } elseif (isset($filters['shop_id']) && $filters['shop_id'] !== '' && $filters['shop_id'] !== null && $filters['shop_id'] !== 'all') {
            $conditions[] = "{$alias}.shop_id = ?";
            $bindings[] = $filters['shop_id'];
        }
        if (!empty($filters['movement_type'])) {
            $conditions[] = "{$alias}.source_type = ?";
            $bindings[] = $filters['movement_type'];
        }
        // user_id: not stored on stock_logs; ignored until schema supports it

        return implode(' AND ', $conditions);
    }

    /**
     * Human-readable reference: for purchase show purchase_no, for sale show invoice_no, else label (#id).
     */
    private function buildReference(?string $sourceType, ?string $sourceId, ?string $purchaseNo = null, ?string $invoiceNo = null): string
    {
        $type = $sourceType ?? '';
        $id = trim((string) $sourceId ?? '');

        if ($type === 'purchase' && $purchaseNo !== null && $purchaseNo !== '') {
            return $purchaseNo;
        }
        if ($type === 'sale' && $invoiceNo !== null && $invoiceNo !== '') {
            return $invoiceNo;
        }

        $labels = [
            'opening'         => 'Opening',
            'purchase'         => 'PO',
            'sale'             => 'Invoice',
            'purchase_return'  => 'Purchase Return',
            'sale_return'      => 'Sale Return',
            'adjustment'       => 'Adjustment',
            'loss'             => 'Damage/Loss',
            'expired'          => 'Expired',
            'theft'            => 'Theft',
        ];

        $label = $labels[$type] ?? ucfirst(str_replace('_', ' ', $type));
        if ($id !== '') {
            return $label . ' #' . $id;
        }
        return $label;
    }
}
