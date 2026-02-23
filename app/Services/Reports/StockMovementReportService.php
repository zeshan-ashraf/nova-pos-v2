<?php

namespace App\Services\Reports;

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

        $bindings = [];
        $where = $this->buildWhere($filters, $bindings);

        // Total count for pagination (no window function needed).
        $countSql = "SELECT COUNT(*) AS total FROM stock_logs WHERE {$where}";
        $total = (int) DB::selectOne($countSql, $bindings)->total;

        // Running balance: delta = +qty for 'in', -qty for 'out'. Balance = SUM(delta) OVER (PARTITION BY product_id, shop_partition ORDER BY created_at, id).
        // shop_id can be null; use COALESCE(shop_id, 0) for partition so all null-shop rows for a product are in one partition.
        $dataBindings = array_merge($bindings, [$perPage, $offset]);

        $dataSql = "
            WITH filtered AS (
                SELECT
                    sl.id,
                    sl.product_id,
                    sl.shop_id,
                    COALESCE(sl.qty, 0) AS qty,
                    sl.direction,
                    sl.source_type,
                    sl.source_id,
                    sl.reason,
                    sl.created_at
                FROM stock_logs sl
                WHERE {$where}
            ),
            with_delta AS (
                SELECT
                    *,
                    CASE WHEN direction = 'in' THEN COALESCE(qty, 0) ELSE -COALESCE(qty, 0) END AS delta
                FROM filtered
            )
            SELECT
                w.id,
                w.product_id,
                w.shop_id,
                p.product_name,
                p.product_code,
                w.created_at AS date,
                w.direction,
                w.source_type AS movement_type,
                w.source_id,
                w.qty,
                SUM(w.delta) OVER (
                    PARTITION BY w.product_id, COALESCE(w.shop_id, 0)
                    ORDER BY w.created_at ASC, w.id ASC
                    ROWS BETWEEN UNBOUNDED PRECEDING AND CURRENT ROW
                ) AS balance
            FROM with_delta w
            JOIN products p ON p.id = w.product_id
            ORDER BY w.created_at ASC, w.id ASC
            LIMIT ? OFFSET ?
        ";

        $rows = DB::select($dataSql, $dataBindings);

        $data = [];
        foreach ($rows as $row) {
            $qty = (int) $row->qty;
            $isIn = strtolower((string) $row->direction) === 'in';
            $data[] = [
                'id'           => (int) $row->id,
                'product_id'   => $productId,
                'product_name' => $row->product_name ?? null,
                'date'         => Carbon::parse($row->date)->toIso8601String(),
                'source_type'  => $row->source_type ?? null,
                'source_id'    => $row->source_id ?? null,
                'qty_in'       => (int) $row->qty_in,
                'qty_out'      => (int) $row->qty_out,
                'balance'      => $balance,
            ];
        }

        return ['rows' => $data, 'opening_balances' => $openingBalances];
    }

    /**
     * MySQL < 8: no window; fetch rows ordered by product_id, created_at, id and compute running balance in PHP.
     */
    private function getReportWithoutWindow(
        string $where,
        array $bindings,
        array $filters,
        ?string $fromDate,
        ?string $toDate,
        int $perPage,
        int $offset,
        int $total
    ): array {
        $dataBindings = array_merge($bindings, [$perPage, $offset]);

        $dataSql = "
            SELECT
                sl.id,
                sl.product_id,
                sl.shop_id,
                COALESCE(sl.qty, 0) AS qty,
                sl.direction,
                sl.source_type,
                sl.source_id,
                sl.created_at AS date,
                p.product_name,
                CASE WHEN sl.direction = 'in' THEN sl.qty ELSE 0 END AS qty_in,
                CASE WHEN sl.direction = 'out' THEN sl.qty ELSE 0 END AS qty_out
            FROM stock_logs sl
            JOIN products p ON p.id = sl.product_id
            WHERE {$where}
            ORDER BY sl.product_id ASC, sl.created_at ASC, sl.id ASC
            LIMIT ? OFFSET ?
        ";

        $rows = DB::select($dataSql, $dataBindings);
        $productIdsOnPage = array_values(array_unique(array_map(function ($r) {
            return (int) $r->product_id;
        }, $rows)));
        $openingBalances = $this->getOpeningBalances($filters, $fromDate, $productIdsOnPage);

        $runningByProduct = [];
        $data = [];
        $seenProductIds = [];
        foreach ($rows as $row) {
            $productId = (int) $row->product_id;
            $delta = strtolower((string) $row->direction) === 'in' ? (int) $row->qty : -(int) $row->qty;
            $runningByProduct[$productId] = ($runningByProduct[$productId] ?? 0) + $delta;
            $balance = ($openingBalances[$productId] ?? 0) + $runningByProduct[$productId];

            if (!in_array($productId, $seenProductIds, true)) {
                $seenProductIds[] = $productId;
                $data[] = $this->openingBalanceRow(
                    $fromDate,
                    $row->product_name,
                    $openingBalances[$productId] ?? 0
                );
            }

            $data[] = [
                'id'            => (int) $row->id,
                'product_id'    => (int) $row->product_id,
                'shop_id'       => $row->shop_id !== null ? (int) $row->shop_id : null,
                'product_name'  => $row->product_name ?? null,
                'product_code'  => $row->product_code ?? null,
                'date'          => Carbon::parse($row->date)->toIso8601String(),
                'reference'     => $this->buildReference($row->movement_type, $row->source_id),
                'movement_type' => $row->movement_type,
                'qty_in'        => $isIn ? $qty : 0,
                'qty_out'       => $isIn ? 0 : $qty,
                'balance'       => (int) $row->balance,
            ];
        }

        $lastPage = $total > 0 ? (int) ceil($total / $perPage) : 1;

    private function openingBalanceRow(?string $fromDate, ?string $productName, int $opening): array
    {
        return [
            'data' => $data,
            'meta' => [
                'total'         => $total,
                'per_page'      => $perPage,
                'current_page'  => $page,
                'last_page'     => $lastPage,
            ],
        ];
    }

    /**
     * Build WHERE clause and bindings from filters.
     * user_id is accepted but not applied (stock_logs has no user_id column).
     */
    private function buildWhere(array $filters, array &$bindings): string
    {
        $conditions = ['1 = 1'];

        if (!empty($filters['from_date'])) {
            $conditions[] = 'created_at >= ?';
            $bindings[] = Carbon::parse($filters['from_date'])->startOfDay()->toDateTimeString();
        }
        if (!empty($filters['to_date'])) {
            $conditions[] = 'created_at <= ?';
            $bindings[] = Carbon::parse($filters['to_date'])->endOfDay()->toDateTimeString();
        }
        if (isset($filters['product_id']) && $filters['product_id'] !== '' && $filters['product_id'] !== null) {
            $conditions[] = 'product_id = ?';
            $bindings[] = $filters['product_id'];
        }
        if (isset($filters['shop_ids']) && is_array($filters['shop_ids'])) {
            if (count($filters['shop_ids']) === 0) {
                $conditions[] = '1 = 0';
            } else {
                $placeholders = implode(',', array_fill(0, count($filters['shop_ids']), '?'));
                $conditions[] = "shop_id IN ({$placeholders})";
                foreach ($filters['shop_ids'] as $sid) {
                    $bindings[] = $sid;
                }
            }
        } elseif (isset($filters['shop_id']) && $filters['shop_id'] !== '' && $filters['shop_id'] !== null && $filters['shop_id'] !== 'all') {
            $conditions[] = 'shop_id = ?';
            $bindings[] = $filters['shop_id'];
        }
        if (!empty($filters['movement_type'])) {
            $conditions[] = 'source_type = ?';
            $bindings[] = $filters['movement_type'];
        }
        // user_id: not stored on stock_logs; ignored until schema supports it

        return implode(' AND ', $conditions);
    }

    /**
     * Human-readable reference from source_type and source_id.
     * Works for any future source_type without code change.
     */
    private function buildReference(?string $sourceType, ?string $sourceId): string
    {
        $type = $sourceType ?? '';
        $id = trim((string) $sourceId ?? '');

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
