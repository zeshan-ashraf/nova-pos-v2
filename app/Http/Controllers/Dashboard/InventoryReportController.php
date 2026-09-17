<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Dashboard\Traits\ReportTrait;
use App\Models\Category;
use App\Models\Product;
use App\Services\Reports\StockMovementReportService;
use App\Services\Reports\StockValuationReportService;
use App\Support\ActiveShop;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class InventoryReportController extends Controller
{
    use ReportTrait;

    public function stock(Request $request)
    {
        $authUser = auth()->user();
        $shopFilter = $this->getShopFilter($request, $authUser);
        $row = $this->getRowCount($request);

        // Qty and all stock data from products table (product_store, low_stock_warning, buying_price, etc.)
        $productsQuery = Product::with(['shop.parent']);

        $this->applyShopFilter($productsQuery, $shopFilter['shop_ids']);

        // Filter by stock status if provided
        if ($request->filled('stock_status')) {
            $status = $request->input('stock_status');
            if ($status === 'low') {
                $productsQuery->whereColumn('product_store', '<=', 'low_stock_warning');
            } elseif ($status === 'out') {
                $productsQuery->where('product_store', '<=', 0);
            }
        }

        // Summary from filtered query only (before sort/join/paginate) so totals are correct on every page
        $summaryQuery = clone $productsQuery;
        $totalProducts = $summaryQuery->count();
        $lowStockCount = (clone $summaryQuery)->whereColumn('product_store', '<=', 'low_stock_warning')->count();
        $outOfStockCount = (clone $summaryQuery)->where('product_store', '<=', 0)->count();
        $totalStockValue = (clone $summaryQuery)->sum(DB::raw('product_store * buying_price'));

        // Sort: product_name, product_code, category, product_store. Order: asc / desc (toggle on column click).
        $allowedSort = ['product_name', 'product_code', 'category', 'product_store'];
        $sort = $request->input('sort', 'product_name');
        if (!in_array($sort, $allowedSort, true)) {
            $sort = 'product_name';
        }
        $order = strtolower($request->input('order', 'asc')) === 'desc' ? 'desc' : 'asc';

        if ($sort === 'category') {
            $productsQuery->leftJoin('categories', 'products.category_id', '=', 'categories.id')
                ->select('products.*');
        }
        $sortColumn = match ($sort) {
            'product_code' => 'products.product_code',
            'category' => 'categories.name',
            'product_store' => 'products.product_store',
            default => 'products.product_name',
        };
        $productsQuery->orderBy($sortColumn, $order);

        $products = $productsQuery->paginate($row)->appends($request->query());
        $this->eagerLoadCategoryForStockReport($products->getCollection());

        return view('reports.inventory.stock', compact(
            'shopFilter', 'products', 'totalProducts', 'lowStockCount',
            'outOfStockCount', 'totalStockValue', 'row', 'sort', 'order'
        ));
    }

    /**
     * Eager load category relation for stock report (by category_id only, no shop scope)
     * so the Category column displays correctly for all products.
     */
    private function eagerLoadCategoryForStockReport($products): void
    {
        if ($products->isEmpty()) {
            return;
        }
        $categoryIds = $products->pluck('category_id')->filter()->unique()->values()->all();
        if (empty($categoryIds)) {
            $products->each->setRelation('category', null);
            return;
        }
        $categories = Category::withoutGlobalScope('shop')
            ->whereIn('id', $categoryIds)
            ->get()
            ->keyBy('id');
        foreach ($products as $product) {
            $product->setRelation('category', $categories->get($product->category_id));
        }
    }

    public function stockMovement(Request $request)
    {
        $authUser = auth()->user();
        $shopFilter = $this->getShopFilter($request, $authUser);
        $format = $request->input('format');
        $exportCsv = $request->input('export') === 'csv' || $format === 'csv';

        if ($request->wantsJson() || $format === 'json' || $exportCsv) {
            if (! $request->filled('date_filter')) {
                $request->merge(['date_filter' => 'all']);
            }
            $dateRange = $this->getDateRange($request);
            $filters = [
                'from_date'      => $dateRange['start_date'],
                'to_date'        => $dateRange['end_date'],
                'product_id'     => $request->input('product_id'),
                'movement_type'  => $request->input('movement_type'),
                'user_id'        => $request->input('user_id'),
                'sort'           => $request->input('sort', 'date'),
                'order'          => $request->input('order', 'asc'),
            ];
            if ($authUser->shop_id) {
                $filters['shop_id'] = $authUser->shop_id;
            } else {
                $filters['shop_ids'] = $shopFilter['shop_ids']->toArray();
                if ($request->filled('shop_id') && $request->input('shop_id') !== 'all') {
                    $filters['shop_id'] = $request->input('shop_id');
                    unset($filters['shop_ids']);
                }
            }
            $page = (int) $request->input('page', 1);
            $perPage = (int) $request->input('per_page', StockMovementReportService::DEFAULT_PAGE_SIZE);
            if ($exportCsv) {
                $exportPerPage = StockMovementReportService::MAX_PAGE_SIZE;
                $filename = 'stock-movement-' . now()->format('Ymd-His') . '.csv';

                return response()->stream(function () use ($filters, $exportPerPage) {
                    $out = fopen('php://output', 'w');
                    // CSV header
                    fputcsv($out, [
                        'Date',
                        'Product Name',
                        'Code',
                        'Reference',
                        'Type',
                        'Qty IN',
                        'Qty OUT',
                        'Balance',
                    ]);

                    $page = 1;
                    $lastPage = 1;

                    while ($page <= $lastPage) {
                        $report = app(StockMovementReportService::class)->getReport($filters, $page, $exportPerPage);
                        $rows = $report['data'] ?? [];
                        $meta = $report['meta'] ?? [];
                        $lastPage = (int) ($meta['last_page'] ?? 1);

                        foreach ($rows as $row) {
                            fputcsv($out, [
                                $row['date'] ?? '',
                                $row['product_name'] ?? '',
                                $row['product_code'] ?? '',
                                $row['reference'] ?? '',
                                $row['movement_type'] ?? '',
                                $row['qty_in'] ?? 0,
                                $row['qty_out'] ?? 0,
                                $row['balance'] ?? 0,
                            ]);
                        }
                        $page++;
                    }

                    fclose($out);
                }, 200, [
                    'Content-Type' => 'text/csv',
                    'Content-Disposition' => 'attachment; filename="' . $filename . '"',
                ]);
            }

            $report = app(StockMovementReportService::class)->getReport($filters, $page, $perPage);
            return response()->json($report);
        }

        $selectedProductId = $request->input('product_id');
        $selectedProduct = $selectedProductId ? Product::find($selectedProductId) : null;
        return view('reports.inventory.stock-movement', compact('shopFilter', 'selectedProductId', 'selectedProduct'));
    }

    /**
     * Search products for stock movement report (autocomplete).
     * Returns only products belonging to the logged-in user's shop(s).
     * Shop-scoped user: their shop (or parent + branches). Admin: current active shop or filter shop_id only.
     */
    public function searchProducts(Request $request)
    {
        $search = $request->get('q', '');
        $page = $request->get('page', 1);
        $authUser = auth()->user();
        $visibleShopIds = ActiveShop::visibleShopIds($authUser);
     
        $productsQuery = Product::query();

        // Restrict to logged-in user's shop(s) only — never all shops.
        if ($authUser->shop_id) {
            // User is tied to a shop: only products from that shop (or parent + child shops).
            $productsQuery->where('shop_id', $authUser->shop_id);
        } else {
            // Admin: only one shop at a time — active shop from session, or shop from filter. Never all shops.
            $activeShopId = ActiveShop::id();
            $requestShopId = $request->get('shop_id');
            $singleShopId = null;
            if ($activeShopId && $visibleShopIds->contains($activeShopId)) {
                $singleShopId = $activeShopId;
            } elseif ($requestShopId && $requestShopId !== 'all' && $visibleShopIds->contains($requestShopId)) {
                $singleShopId = $requestShopId;
            }
            if ($singleShopId !== null) {
                $productsQuery->where('shop_id', $singleShopId);
            } else {
                $productsQuery->whereRaw('1 = 0');
            }
        }

        if ($search !== '') {
            $productsQuery->where(function ($query) use ($search) {
                $query->where('product_name', 'like', '%' . $search . '%')
                    ->orWhere('product_code', 'like', '%' . $search . '%');
            });
        }

        $totalCount = (clone $productsQuery)->count();
        $perPage = 50;
        $offset = ($page - 1) * $perPage;

        $products = $productsQuery->orderBy('product_code', 'asc')
            ->orderBy('product_name', 'asc')
            ->offset($offset)
            ->limit($perPage)
            ->get()
            ->map(function ($product) {
                $displayText = $product->product_name;
                if ($product->product_code) {
                    $displayText .= ' (' . $product->product_code . ')';
                }
                return [
                    'id'   => $product->id,
                    'text' => $displayText,
                    'name' => $product->product_name,
                    'code' => $product->product_code,
                    'unit' => $product->unit ?: Product::UNIT_PIECE,
                ];
            });

        return response()->json([
            'results'    => $products,
            'pagination' => ['more' => ($page * $perPage) < $totalCount],
        ]);
    }

    public function stockValuation(Request $request)
    {
        $authUser = auth()->user();
        $shopFilter = $this->getShopFilter($request, $authUser);

        if ($request->wantsJson() || $request->input('format') === 'json') {
            $filters = [
                'product_id'                => $request->input('product_id'),
                'category_id'               => $request->input('category_id'),
                'status'                    => $request->input('status'),
                'min_quantity'              => $request->input('min_quantity'),
                'max_quantity'             => $request->input('max_quantity'),
                'include_zero_or_negative'  => $request->boolean('include_zero_or_negative'),
            ];
            if ($authUser->shop_id) {
                $filters['shop_id'] = $authUser->shop_id;
            } else {
                $filters['shop_ids'] = $shopFilter['shop_ids']->toArray();
                if ($request->filled('shop_id') && $request->input('shop_id') !== 'all') {
                    $filters['shop_id'] = $request->input('shop_id');
                    unset($filters['shop_ids']);
                }
            }
            $page = (int) $request->input('page', 1);
            $perPage = (int) $request->input('per_page', StockValuationReportService::DEFAULT_PAGE_SIZE);
            $report = app(StockValuationReportService::class)->getReport($filters, $page, $perPage);
            return response()->json($report);
        }

        $shopIds = $shopFilter['shop_ids']->toArray();
        $categories = $shopIds === []
            ? Category::orderBy('name')->get(['id', 'name'])
            : Category::whereIn('shop_id', $shopIds)->orderBy('name')->get(['id', 'name']);
        $selectedProductId = $request->input('product_id');
        $selectedProduct = $selectedProductId ? Product::find($selectedProductId) : null;
        return view('reports.inventory.stock-valuation', compact('shopFilter', 'categories', 'selectedProductId', 'selectedProduct'));
    }

    public function expiredProducts(Request $request)
    {
        $authUser = auth()->user();
        $shopFilter = $this->getShopFilter($request, $authUser);
        $row = $this->getRowCount($request);

        $query = Product::with(['shop.parent'])
            ->whereNotNull('expire_date')
            ->where('expire_date', '<=', Carbon::today()->format('Y-m-d'));

        $this->applyShopFilter($query, $shopFilter['shop_ids']);

        $products = $query->orderBy('expire_date')->paginate($row)->appends($request->query());
        Product::eagerLoadSameShopCategory($products->getCollection());
        $totalCount = (clone $query)->count();

        return view('reports.inventory.expired-products', compact(
            'shopFilter', 'products', 'totalCount', 'row'
        ));
    }
}
