<?php

namespace App\Http\Controllers\Report;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;

class StockAuditController extends Controller
{
    public function index(Request $request)
    {
        $shopId = auth()->user()->shop_id;
        $mismatchOnly = $request->boolean('mismatch_only');

        if (!$shopId) {
            return view('reports.inventory.stock_audit', [
                'rows' => collect(),
                'totals' => [],
                'mismatchOnly' => $mismatchOnly,
            ]);
        }

        $query = <<<'SQL'
SELECT 
    p.id AS product_id,
    p.product_name,
    p.product_code,
    p.product_store AS available_stock,
    COALESCE(p.unit, 'piece') AS unit,
    p.shop_id,

    COALESCE(pur.total_purchased, 0) AS total_purchased,
    COALESCE(ord.total_ordered, 0) AS total_sold,
    COALESCE(sr.total_sale_return, 0) AS total_sale_return,
    COALESCE(pr.total_purchase_return, 0) AS total_purchase_return,

    (
        COALESCE(pur.total_purchased, 0)
        - COALESCE(ord.total_ordered, 0)
        - COALESCE(pr.total_purchase_return, 0)
        + COALESCE(sr.total_sale_return, 0)
    ) AS expected_stock,

    COALESCE(sl.stock_balance, 0) AS ledger_stock,

    (
        (
            COALESCE(pur.total_purchased, 0)
            - COALESCE(ord.total_ordered, 0)
            - COALESCE(pr.total_purchase_return, 0)
            + COALESCE(sr.total_sale_return, 0)
        )
        - COALESCE(sl.stock_balance, 0)
    ) AS stock_difference

FROM products p

LEFT JOIN (
    SELECT product_id, SUM(quantity) AS total_purchased
    FROM purchase_details
    WHERE deleted_at IS NULL
    GROUP BY product_id
) pur ON pur.product_id = p.id

LEFT JOIN (
    SELECT od.product_id, SUM(od.quantity) AS total_ordered
    FROM order_details od
    INNER JOIN orders o ON o.id = od.order_id
    WHERE od.deleted_at IS NULL
      AND o.deleted_at IS NULL
    GROUP BY od.product_id
) ord ON ord.product_id = p.id

LEFT JOIN (
    SELECT product_id, SUM(qty) AS total_sale_return
    FROM stock_logs
    WHERE source_type = 'sale_return'
      AND deleted_at IS NULL
    GROUP BY product_id
) sr ON sr.product_id = p.id

LEFT JOIN (
    SELECT product_id, SUM(qty) AS total_purchase_return
    FROM stock_logs
    WHERE source_type = 'purchase_return'
      AND deleted_at IS NULL
    GROUP BY product_id
) pr ON pr.product_id = p.id

LEFT JOIN (
    SELECT 
        product_id,
        SUM(
            CASE 
                WHEN direction IN ('in', 'purchase') THEN qty
                WHEN direction IN ('out', 'sale') THEN -qty
                ELSE 0
            END
        ) AS stock_balance
    FROM stock_logs
    WHERE deleted_at IS NULL
    GROUP BY product_id
) sl ON sl.product_id = p.id

WHERE p.shop_id = ?
SQL;

        if ($mismatchOnly) {
            $query .= <<<'SQL'
 AND (
    (
        COALESCE(pur.total_purchased, 0)
        - COALESCE(ord.total_ordered, 0)
        - COALESCE(pr.total_purchase_return, 0)
        + COALESCE(sr.total_sale_return, 0)
    ) - COALESCE(sl.stock_balance, 0)
) != 0
SQL;
        }

        $query .= "\nORDER BY stock_difference ASC";

        $rows = collect(DB::select($query, [$shopId]));

        if ($request->input('export') === 'csv') {
            return $this->exportCsv($rows);
        }

        $totals = $this->totalsByUnit($rows);

        return view('reports.inventory.stock_audit', [
            'rows' => $rows,
            'totals' => $totals,
            'mismatchOnly' => $mismatchOnly,
        ]);
    }

    private function exportCsv($rows): StreamedResponse
    {
        $filename = 'stock-audit-' . now()->format('Ymd-His') . '.csv';
        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ];

        return response()->stream(function () use ($rows) {
            $out = fopen('php://output', 'w');
            fputcsv($out, [
                'Product Name',
                'Code',
                'Unit',
                'Available Stock',
                'Total Purchased',
                'Total Sold',
                'Sale Return',
                'Purchase Return',
                'Expected Stock',
                'Ledger Stock',
                'Difference',
            ]);

            foreach ($rows as $row) {
                $unit = $row->unit ?? 'piece';
                fputcsv($out, [
                    $row->product_name,
                    $row->product_code,
                    $unit,
                    \App\Models\Product::formatQuantityForUnit($row->available_stock, $unit),
                    \App\Models\Product::formatQuantityForUnit($row->total_purchased, $unit),
                    \App\Models\Product::formatQuantityForUnit($row->total_sold, $unit),
                    \App\Models\Product::formatQuantityForUnit($row->total_sale_return, $unit),
                    \App\Models\Product::formatQuantityForUnit($row->total_purchase_return, $unit),
                    \App\Models\Product::formatQuantityForUnit($row->expected_stock, $unit),
                    \App\Models\Product::formatQuantityForUnit($row->ledger_stock, $unit),
                    \App\Models\Product::formatQuantityForUnit($row->stock_difference, $unit),
                ]);
            }

            fclose($out);
        }, 200, $headers);
    }

    /**
     * Quantity totals stay grouped by unit. A single cross-unit sum is not a quantity.
     *
     * @param \Illuminate\Support\Collection<int, object> $rows
     * @return array<int, array{unit: string, available_stock: string, total_purchased: string, total_sold: string, total_sale_return: string, total_purchase_return: string, expected_stock: string, ledger_stock: string, stock_difference: string}>
     */
    private function totalsByUnit($rows): array
    {
        $units = app(\App\Support\ProductUnitValidator::class);
        $buckets = [];
        foreach ($rows as $row) {
            $unit = in_array($row->unit ?? null, \App\Models\Product::allowedUnits(), true)
                ? $row->unit
                : \App\Models\Product::UNIT_PIECE;
            if (! isset($buckets[$unit])) {
                $buckets[$unit] = [
                    'unit' => $unit,
                    'available_stock' => '0',
                    'total_purchased' => '0',
                    'total_sold' => '0',
                    'total_sale_return' => '0',
                    'total_purchase_return' => '0',
                    'expected_stock' => '0',
                    'ledger_stock' => '0',
                    'stock_difference' => '0',
                ];
            }
            foreach (['available_stock', 'total_purchased', 'total_sold', 'total_sale_return', 'total_purchase_return', 'expected_stock', 'ledger_stock', 'stock_difference'] as $field) {
                $buckets[$unit][$field] = $units->add(
                    $units->formatQuantity($buckets[$unit][$field]),
                    $units->formatQuantity($row->{$field} ?? 0)
                );
            }
        }

        return array_values($buckets);
    }
}

