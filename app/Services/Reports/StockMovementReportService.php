<?php

namespace App\Services\Reports;

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

/**
 * Stock Movement Report — READ-ONLY.
 *
 * Single source of truth: stock_logs for quantities; optional LEFT JOINs only to resolve
 * human-readable reference numbers and links (read-only). No stock writes, no accounting.
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

        $sort = $filters['sort'] ?? 'date';
        $order = strtolower($filters['order'] ?? 'asc') === 'asc' ? 'asc' : 'desc';
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
                ref_sr.return_no AS ref_sale_return_no,
                ref_pr.return_no AS ref_purchase_return_no,
                CASE sl.source_type
                    WHEN 'sale' THEN COALESCE(NULLIF(TRIM(cust_o.shopname), ''), NULLIF(TRIM(cust_o.name), ''))
                    WHEN 'purchase' THEN COALESCE(NULLIF(TRIM(supp_p.shopname), ''), NULLIF(TRIM(supp_p.name), ''))
                    WHEN 'sale_return' THEN COALESCE(NULLIF(TRIM(cust_sr.shopname), ''), NULLIF(TRIM(cust_sr.name), ''))
                    WHEN 'purchase_return' THEN COALESCE(NULLIF(TRIM(supp_pr.shopname), ''), NULLIF(TRIM(supp_pr.name), ''))
                    ELSE NULL
                END AS ref_party_name,
                COALESCE(sl.qty, 0) AS qty,
                CASE WHEN sl.direction = 'in' THEN COALESCE(sl.qty, 0) ELSE 0 END AS qty_in,
                CASE WHEN sl.direction = 'out' THEN COALESCE(sl.qty, 0) ELSE 0 END AS qty_out,
                SUM(
                    CASE WHEN sl.direction = 'in' THEN COALESCE(sl.qty, 0) ELSE -COALESCE(sl.qty, 0) END
                ) OVER (
                    PARTITION BY sl.product_id, COALESCE(sl.shop_id, 0)
                    ORDER BY {$orderByClause}
                    ROWS BETWEEN UNBOUNDED PRECEDING AND CURRENT ROW
                ) AS balance
            FROM stock_logs sl
            JOIN products p ON p.id = sl.product_id
            LEFT JOIN purchases ref_p ON sl.source_type = 'purchase' AND sl.source_id = ref_p.id
            LEFT JOIN orders ref_o ON sl.source_type IN ('sale', 'mother_sale') AND sl.source_id = ref_o.id
            LEFT JOIN sale_returns ref_sr ON sl.source_type = 'sale_return' AND sl.source_id = ref_sr.id
            LEFT JOIN purchase_returns ref_pr ON sl.source_type = 'purchase_return' AND sl.source_id = ref_pr.id
            LEFT JOIN customers cust_o ON cust_o.id = ref_o.customer_id
            LEFT JOIN suppliers supp_p ON supp_p.id = ref_p.supplier_id
            LEFT JOIN customers cust_sr ON cust_sr.id = ref_sr.customer_id
            LEFT JOIN purchases pur_for_pr ON pur_for_pr.id = ref_pr.purchase_id
            LEFT JOIN suppliers supp_pr ON supp_pr.id = pur_for_pr.supplier_id
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
                'date'          => Carbon::parse($row->date)->timezone(config('app.timezone'))->toIso8601String(),
                'reference'     => $this->buildReference(
                    $row->movement_type ?? null,
                    $row->source_id ?? null,
                    $row->ref_purchase_no ?? null,
                    $row->ref_invoice_no ?? null,
                    $row->ref_sale_return_no ?? null,
                    $row->ref_purchase_return_no ?? null,
                    $row->ref_party_name ?? null
                ),
                'reference_url' => $this->referenceUrl($row->movement_type ?? null, $row->source_id ?? null),
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
                ref_sr.return_no AS ref_sale_return_no,
                ref_pr.return_no AS ref_purchase_return_no,
                CASE sl.source_type
                    WHEN 'sale' THEN COALESCE(NULLIF(TRIM(cust_o.shopname), ''), NULLIF(TRIM(cust_o.name), ''))
                    WHEN 'purchase' THEN COALESCE(NULLIF(TRIM(supp_p.shopname), ''), NULLIF(TRIM(supp_p.name), ''))
                    WHEN 'sale_return' THEN COALESCE(NULLIF(TRIM(cust_sr.shopname), ''), NULLIF(TRIM(cust_sr.name), ''))
                    WHEN 'purchase_return' THEN COALESCE(NULLIF(TRIM(supp_pr.shopname), ''), NULLIF(TRIM(supp_pr.name), ''))
                    ELSE NULL
                END AS ref_party_name,
                COALESCE(sl.qty, 0) AS qty,
                CASE WHEN sl.direction = 'in' THEN COALESCE(sl.qty, 0) ELSE 0 END AS qty_in,
                CASE WHEN sl.direction = 'out' THEN COALESCE(sl.qty, 0) ELSE 0 END AS qty_out
            FROM stock_logs sl
            JOIN products p ON p.id = sl.product_id
            LEFT JOIN purchases ref_p ON sl.source_type = 'purchase' AND sl.source_id = ref_p.id
            LEFT JOIN orders ref_o ON sl.source_type IN ('sale', 'mother_sale') AND sl.source_id = ref_o.id
            LEFT JOIN sale_returns ref_sr ON sl.source_type = 'sale_return' AND sl.source_id = ref_sr.id
            LEFT JOIN purchase_returns ref_pr ON sl.source_type = 'purchase_return' AND sl.source_id = ref_pr.id
            LEFT JOIN customers cust_o ON cust_o.id = ref_o.customer_id
            LEFT JOIN suppliers supp_p ON supp_p.id = ref_p.supplier_id
            LEFT JOIN customers cust_sr ON cust_sr.id = ref_sr.customer_id
            LEFT JOIN purchases pur_for_pr ON pur_for_pr.id = ref_pr.purchase_id
            LEFT JOIN suppliers supp_pr ON supp_pr.id = pur_for_pr.supplier_id
            WHERE {$where}
            ORDER BY {$orderByClause}
            LIMIT ? OFFSET ?
        ";
        $rows = DB::select($sql, array_merge($bindings, [$perPage, $offset]));

        // Running balance must follow the same order as the result rows are returned/displayed.
        $running = [];
        foreach ($rows as $r) {
            $key = ((int) $r->product_id) . '|' . (string) ($r->shop_id ?? 0);
            $delta = strtolower((string) $r->direction) === 'in' ? (int) $r->qty : -(int) $r->qty;
            $running[$key] = ($running[$key] ?? 0) + $delta;
            $r->balance = $running[$key];
        }
        // Return in the same order as query/display.
        return $rows;
    }

    /**
     * Build WHERE clause and bindings from filters.
     * user_id is accepted but not applied (stock_logs has no user_id column).
     */
    private function buildWhere(array $filters, array &$bindings, string $alias = 'sl'): string
    {
       
        $conditions = ['1 = 1', "{$alias}.deleted_at IS NULL"];

        // Prefer adjustment_date (business date); when null, use created_at so rows still match the range.
        $effectiveDate = "COALESCE({$alias}.adjustment_date, {$alias}.created_at)";
        if (!empty($filters['from_date'])) {
            $conditions[] = "{$effectiveDate} >= ?";
            $bindings[] = Carbon::parse($filters['from_date'])->startOfDay()->toDateTimeString();
        }
        if (!empty($filters['to_date'])) {
            $conditions[] = "{$effectiveDate} <= ?";
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
     * Human-readable reference: document no., optionally followed by customer or supplier (middle dot).
     */
    private function buildReference(
        ?string $sourceType,
        ?string $sourceId,
        ?string $purchaseNo = null,
        ?string $invoiceNo = null,
        ?string $saleReturnNo = null,
        ?string $purchaseReturnNo = null,
        ?string $partyName = null
    ): string {
        $type = $sourceType ?? '';
        $id = trim((string) $sourceId ?? '');

        $base = null;
        if ($type === 'purchase' && $purchaseNo !== null && $purchaseNo !== '') {
            $base = $purchaseNo;
        } elseif ($type === 'sale' && $invoiceNo !== null && $invoiceNo !== '') {
            $base = $invoiceNo;
        } elseif ($type === 'mother_sale') {
            $base = $id !== '' ? 'Mother sale #' . $id : 'Mother sale';
            if ($invoiceNo !== null && $invoiceNo !== '') {
                $base .= ' (' . $invoiceNo . ')';
            }
        } elseif ($type === 'sale_return' && $saleReturnNo !== null && $saleReturnNo !== '') {
            $base = $saleReturnNo;
        } elseif ($type === 'purchase_return' && $purchaseReturnNo !== null && $purchaseReturnNo !== '') {
            $base = $purchaseReturnNo;
        }

        if ($base === null) {
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
            $base = $id !== '' ? $label . ' #' . $id : $label;
        }

        return $this->formatReferenceWithParty($base, $partyName);
    }

    /**
     * Appends customer/supplier name after the document reference when present (e.g. "INV-001 · Acme Ltd").
     */
    private function formatReferenceWithParty(string $base, ?string $partyName): string
    {
        $party = trim((string) ($partyName ?? ''));
        if ($party === '') {
            return $base;
        }

        return $base . ' · ' . $party;
    }

    /**
     * URL to open the related document in a new tab (sale order, purchase, sale/purchase return).
     */
    private function referenceUrl(?string $sourceType, $sourceId): ?string
    {
        $id = trim((string) ($sourceId ?? ''));
        if ($id === '' || ! ctype_digit($id)) {
            return null;
        }
        $nid = (int) $id;
        if ($nid < 1) {
            return null;
        }

        try {
            return match ($sourceType) {
                'sale' => route('order.orderDetails', ['order_id' => $nid]),
                'mother_sale' => route('order.orderDetails', ['order_id' => $nid]),
                'purchase' => route('purchases.show', ['purchase_id' => $nid]),
                'sale_return' => route('sale-returns.show', ['return_id' => $nid]),
                'purchase_return' => route('purchase-returns.show', $nid),
                default => null,
            };
        } catch (\Throwable $e) {
            return null;
        }
    }
}
