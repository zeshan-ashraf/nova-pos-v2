<?php

namespace App\Services\Reports;

use App\Models\Product;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

/**
 * Stock Valuation Report — READ-ONLY.
 *
 * Uses ONLY the products table. No stock_logs, sales, or purchases.
 * Valuation = current quantity × unit prices; all calculations in query.
 * Nothing is written back to the database.
 */
class StockValuationReportService
{
    /** Quantity column on products table. */
    private const QTY_COLUMN = 'product_store';

    /** Default page size; max for performance. */
    public const DEFAULT_PAGE_SIZE = 50;
    public const MAX_PAGE_SIZE = 500;

    /**
     * Build stock valuation report from products table only.
     *
     * @param array $filters product_id?, category_id?, shop_id?, shop_ids?, status?, min_quantity?, max_quantity?, include_zero_or_negative?
     * @param int $page 1-based page
     * @param int $perPage page size (capped at MAX_PAGE_SIZE)
     * @return array{ data: array<int, array>, meta: array{ total: int, per_page: int, current_page: int, last_page: int } }
     */
    public function getReport(array $filters, int $page = 1, int $perPage = self::DEFAULT_PAGE_SIZE): array
    {
        $perPage = max(1, min($perPage, self::MAX_PAGE_SIZE));
        $page = max(1, $page);

        $query = Product::query()
            ->withoutGlobalScopes()
            ->select([
                'products.id',
                'products.product_name',
                'products.product_code',
                'products.category_id',
                'products.shop_id',
                self::QTY_COLUMN . ' as quantity',
                'products.buying_price',
                'products.selling_price',
                'products.status',
            ])
            ->selectRaw(
                'COALESCE(products.' . self::QTY_COLUMN . ', 0) * COALESCE(products.buying_price, 0) AS stock_cost_value'
            )
            ->selectRaw(
                'COALESCE(products.' . self::QTY_COLUMN . ', 0) * COALESCE(products.selling_price, 0) AS stock_sale_value'
            )
            ->selectRaw(
                '(COALESCE(products.selling_price, 0) - COALESCE(products.buying_price, 0)) * COALESCE(products.' . self::QTY_COLUMN . ', 0) AS profit_potential'
            );

        $this->applyFilters($query, $filters);

        $query->with(['category:id,name']);
        $query->orderBy('products.product_name');

        $paginator = $query->paginate($perPage, ['*'], 'page', $page);

        $data = $this->formatRows($paginator->items());

        return [
            'data' => $data,
            'meta' => [
                'total'         => $paginator->total(),
                'per_page'      => $paginator->perPage(),
                'current_page'  => $paginator->currentPage(),
                'last_page'     => $paginator->lastPage(),
            ],
        ];
    }

    /**
     * Apply filters to the products query. No stock_logs or other tables.
     */
    private function applyFilters($query, array $filters): void
    {
        if (isset($filters['shop_ids']) && is_array($filters['shop_ids']) && count($filters['shop_ids']) > 0) {
            $query->whereIn('products.shop_id', $filters['shop_ids']);
        } elseif (isset($filters['shop_id']) && $filters['shop_id'] !== '' && $filters['shop_id'] !== null && $filters['shop_id'] !== 'all') {
            $query->where('products.shop_id', $filters['shop_id']);
        }

        if (isset($filters['product_id']) && $filters['product_id'] !== '' && $filters['product_id'] !== null) {
            $query->where('products.id', $filters['product_id']);
        }

        if (isset($filters['category_id']) && $filters['category_id'] !== '' && $filters['category_id'] !== null) {
            $query->where('products.category_id', $filters['category_id']);
        }

        if (isset($filters['status']) && $filters['status'] !== '' && $filters['status'] !== null) {
            $query->where('products.status', $filters['status']);
        }

        if (isset($filters['min_quantity']) && $filters['min_quantity'] !== '' && $filters['min_quantity'] !== null) {
            $query->whereRaw('COALESCE(products.' . self::QTY_COLUMN . ', 0) >= ?', [(int) $filters['min_quantity']]);
        }

        if (isset($filters['max_quantity']) && $filters['max_quantity'] !== '' && $filters['max_quantity'] !== null) {
            $query->whereRaw('COALESCE(products.' . self::QTY_COLUMN . ', 0) <= ?', [(int) $filters['max_quantity']]);
        }

        if (empty($filters['include_zero_or_negative'])) {
            $query->whereRaw('COALESCE(products.' . self::QTY_COLUMN . ', 0) > 0');
        }
    }

    /**
     * Format paginator items to API shape. Category from relation.
     *
     * @param array<int, \App\Models\Product> $items
     * @return array<int, array>
     */
    private function formatRows(array $items): array
    {
        $rows = [];
        foreach ($items as $row) {
            $qty = (int) ($row->quantity ?? $row->{self::QTY_COLUMN} ?? 0);
            $buying = (float) ($row->buying_price ?? 0);
            $selling = (float) ($row->selling_price ?? 0);
            $stockCost = (float) ($row->stock_cost_value ?? 0);
            $stockSale = (float) ($row->stock_sale_value ?? 0);
            $profitPotential = (float) ($row->profit_potential ?? 0);

            $categoryName = null;
            if ($row->relationLoaded('category') && $row->category) {
                $categoryName = $row->category->name;
            }

            $rows[] = [
                'product_id'        => (int) $row->id,
                'product_name'     => $row->product_name ?? '',
                'sku'               => $row->product_code ?? null,
                'quantity'          => $qty,
                'buying_price'      => round($buying, 2),
                'selling_price'     => round($selling, 2),
                'stock_cost_value'  => round($stockCost, 2),
                'stock_sale_value'  => round($stockSale, 2),
                'profit_potential'  => round($profitPotential, 2),
                'category'         => $categoryName,
            ];
        }
        return $rows;
    }
}
