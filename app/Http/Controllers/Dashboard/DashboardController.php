<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Dashboard\Traits\ReportTrait;
use App\Models\AccountTransaction;
use App\Models\Order;
use App\Models\OrderDetails;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\Shop;
use Carbon\CarbonPeriod;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Support\ActiveShop;
use App\Support\InterShopTransferStatus;
use App\Support\MotherShopSuperAdmin;
use Illuminate\Support\Collection;

class DashboardController extends Controller
{
    use ReportTrait;

    public function index(Request $request){
        $visibleShopIds = $this->dashboardScopeShopIds();
        $dateRange = $this->getDateRange($request);
        $financialKpis = $this->buildShopFinancialKpis($dateRange);
        $groupBy = $request->input('group_by');
        $ordersOverview = $this->buildOrdersOverviewSeries($dateRange, $groupBy);
        $revenueVsCost = $this->buildRevenueVsCostSeries($dateRange, $groupBy);

        $ordersQuery = Order::query();
        $productsQuery = Product::query();

        // Apply strict dashboard shop scope.
        if ($visibleShopIds->isEmpty()) {
            $ordersQuery->whereRaw('1 = 0');
            $productsQuery->whereRaw('1 = 0');
        } else {
            $ordersQuery->whereIn('shop_id', $visibleShopIds->all());
            $productsQuery->whereIn('shop_id', $visibleShopIds->all());
        }

        return view('dashboard.index', [
            'total_paid' => (clone $ordersQuery)->sum('pay'),
            'total_due' => (clone $ordersQuery)->sum('due'),
            'complete_orders' => (clone $ordersQuery)->whereIn('order_status', ['complete', InterShopTransferStatus::COMPLETED])->count(),
            'customer_total_due' => $financialKpis['total_due'],
            'net_cash' => $financialKpis['net_cash'],
            'total_sales' => $financialKpis['total_sales'],
            'profit' => $financialKpis['profit'],
            'orders_overview' => $ordersOverview,
            'revenue_vs_cost' => $revenueVsCost,
            'dateRange' => $dateRange,
            'products' => (clone $productsQuery)->orderBy('product_store')->take(5)->get(),
            'new_products' => (clone $productsQuery)->orderBy('buying_date')->take(2)->get(),
        ]);
    }

    /**
     * KPI endpoint for logged-in shop user:
     * - net_cash: cash/bank inflow - outflow
     * - total_sales: valid sales total
     * - profit: total_sales - cogs
     */
    public function getFinancialKpis(Request $request)
    {
        return response()->json($this->buildShopFinancialKpis($this->getDateRange($request)));
    }

    /**
     * Return consolidated KPIs as JSON for the selected date range and shops.
     * Optional: includes previous-period values and trend % for arrows.
     */
    public function getKPIs(Request $request)
    {
        $request->validate([
            'date_filter' => 'nullable|string|in:today,yesterday,this_week,last_week,this_month,last_month,this_year,last_year,custom,all_time',
            'start_date'  => 'nullable|date',
            'end_date'    => 'nullable|date|after_or_equal:start_date',
            'shop_ids'    => 'nullable|array',
            'shop_ids.*'  => 'integer|exists:shops,id',
        ]);

        $visibleShopIds = $this->dashboardScopeShopIds();
        $emptyKpis = [
            'total_sales' => 0, 'total_cogs' => 0, 'gross_profit' => 0,
            'total_discounts' => 0, 'net_profit' => 0, 'profit_margin' => 0,
            'total_sales_trend_pct' => null, 'total_cogs_trend_pct' => null, 'gross_profit_trend_pct' => null,
            'total_discounts_trend_pct' => null, 'net_profit_trend_pct' => null, 'profit_margin_trend_pct' => null,
        ];
        if ($visibleShopIds->isEmpty()) {
            return response()->json($emptyKpis);
        }

        $shopIds = $request->filled('shop_ids') && is_array($request->shop_ids)
            ? collect($request->shop_ids)->filter(fn ($id) => $visibleShopIds->contains($id))->values()
            : $visibleShopIds;
        if ($shopIds->isEmpty()) {
            $shopIds = $visibleShopIds;
        }

        $dateRange = $this->getDateRange($request);
        $startDt = $dateRange['start_datetime'];
        $endDt = $dateRange['end_datetime'];
        $days = $startDt->diffInDays($endDt) + 1;
        $prevEnd = $startDt->copy()->subDay();
        $prevStart = $prevEnd->copy()->subDays($days - 1);

        $current = $this->aggregateKpis($startDt, $endDt, $shopIds);
        $previous = $this->aggregateKpis($prevStart, $prevEnd, $shopIds);

        $trend = function ($curr, $prev) {
            if ($prev == 0) return $curr != 0 ? 100 : null;
            return round((($curr - $prev) / abs($prev)) * 100, 1);
        };

        return response()->json([
            'total_sales'       => round($current['total_sales'], 2),
            'total_cogs'        => round($current['total_cogs'], 2),
            'gross_profit'      => round($current['gross_profit'], 2),
            'total_discounts'   => round($current['total_discounts'], 2),
            'net_profit'        => round($current['net_profit'], 2),
            'profit_margin'     => round($current['profit_margin'], 2),
            'total_sales_trend_pct'   => $trend($current['total_sales'], $previous['total_sales']),
            'total_cogs_trend_pct'    => $trend($current['total_cogs'], $previous['total_cogs']),
            'gross_profit_trend_pct'  => $trend($current['gross_profit'], $previous['gross_profit']),
            'total_discounts_trend_pct' => $trend($current['total_discounts'], $previous['total_discounts']),
            'net_profit_trend_pct'    => $trend($current['net_profit'], $previous['net_profit']),
            'profit_margin_trend_pct' => $previous['profit_margin'] !== null && $previous['profit_margin'] != 0 ? $trend($current['profit_margin'], $previous['profit_margin']) : null,
        ]);
    }

    public function getActivities(Request $request)
    {
        $orders = Order::withoutGlobalScopes()
            ->latest()
            ->limit(10)
            ->get(['id', 'shop_id', 'total', 'created_at']);

        $purchases = Purchase::withoutGlobalScopes()
            ->latest()
            ->limit(10)
            ->get(['id', 'shop_id', 'total', 'created_at']);

        $shopIds = $orders->pluck('shop_id')
            ->merge($purchases->pluck('shop_id'))
            ->filter()
            ->unique()
            ->values();

        $shopNames = Shop::withoutGlobalScopes()
            ->whereIn('id', $shopIds)
            ->pluck('name', 'id');

        $activities = $orders->map(function ($order) use ($shopNames) {
            return [
                'type' => 'sale',
                'shop' => $shopNames[$order->shop_id] ?? 'Unknown Shop',
                'amount' => (float) $order->total,
                'time' => $order->created_at
                    ? $order->created_at->timezone(config('app.timezone'))->toIso8601String()
                    : null,
            ];
        })->merge(
            $purchases->map(function ($purchase) use ($shopNames) {
                return [
                    'type' => 'purchase',
                    'shop' => $shopNames[$purchase->shop_id] ?? 'Unknown Shop',
                    'amount' => (float) $purchase->total,
                    'time' => $purchase->created_at
                        ? $purchase->created_at->timezone(config('app.timezone'))->toIso8601String()
                        : null,
                ];
            })
        )->sortByDesc('time')
            ->take(10)
            ->values();

        return response()->json($activities);
    }

    public function getShopPerformance(Request $request)
    {
        $request->validate([
            'date_filter' => 'nullable|string|in:today,yesterday,this_week,last_week,this_month,last_month,this_year,last_year,custom,all_time',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'shops' => 'nullable',
            'shop_id' => 'nullable|integer|exists:shops,id',
            'shop_ids' => 'nullable|array',
            'shop_ids.*' => 'integer|exists:shops,id',
        ]);

        $payload = $this->buildShopPerformanceRows($request);

        return response()->json(['shops' => $payload]);
    }

    public function getInsights(Request $request)
    {
        $request->validate([
            'date_filter' => 'nullable|string|in:today,yesterday,this_week,last_week,this_month,last_month,this_year,last_year,custom,all_time',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'shops' => 'nullable',
            'shop_id' => 'nullable|integer|exists:shops,id',
            'shop_ids' => 'nullable|array',
            'shop_ids.*' => 'integer|exists:shops,id',
        ]);

        $rows = $this->buildShopPerformanceRows($request);
        $default = ['name' => '-', 'value' => 0];

        $topSelling = $rows->sortByDesc('sales')->first();
        $highestProfit = $rows->sortByDesc('net')->first();
        $bestMargin = $rows->sortByDesc('margin')->first();
        $lowest = $rows->sortBy('net')->first();

        return response()->json([
            'top_selling' => $topSelling
                ? ['name' => $topSelling['shop_name'], 'value' => $topSelling['sales']]
                : $default,
            'highest_profit' => $highestProfit
                ? ['name' => $highestProfit['shop_name'], 'value' => $highestProfit['net']]
                : $default,
            'best_margin' => $bestMargin
                ? ['name' => $bestMargin['shop_name'], 'value' => $bestMargin['margin']]
                : $default,
            'lowest' => $lowest
                ? ['name' => $lowest['shop_name'], 'value' => $lowest['net']]
                : $default,
        ]);
    }

    /**
     * Current inventory snapshot per shop: stock value (qty × cost) and low-stock SKU count.
     * Dates are accepted for filter-bar parity; values reflect current product rows (not point-in-time).
     */
    public function getInventorySnapshot(Request $request)
    {
        $request->validate([
            'date_filter' => 'nullable|string|in:today,yesterday,this_week,last_week,this_month,last_month,this_year,last_year,custom,all_time',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'shops' => 'nullable',
            'shop_id' => 'nullable|integer|exists:shops,id',
            'shop_ids' => 'nullable|array',
            'shop_ids.*' => 'integer|exists:shops,id',
        ]);

        $shopIds = $this->resolveShopIdsForDashboardAggregates($request);

        $stockAgg = Product::withoutGlobalScopes()
            ->selectRaw('shop_id, COALESCE(SUM(COALESCE(product_store, 0) * COALESCE(buying_price, 0)), 0) as stock_value')
            ->whereNotNull('shop_id')
            ->when($shopIds->isNotEmpty(), fn ($q) => $q->whereIn('shop_id', $shopIds))
            ->groupBy('shop_id')
            ->get()
            ->keyBy('shop_id');

        $lowAgg = Product::withoutGlobalScopes()
            ->selectRaw('shop_id, COUNT(*) as low_stock')
            ->whereNotNull('shop_id')
            ->whereRaw('COALESCE(product_store, 0) <= COALESCE(low_stock_warning, 10)')
            ->when($shopIds->isNotEmpty(), fn ($q) => $q->whereIn('shop_id', $shopIds))
            ->groupBy('shop_id')
            ->get()
            ->keyBy('shop_id');

        $shopsQuery = Shop::withoutGlobalScopes()->select(['id', 'name']);
        if ($shopIds->isNotEmpty()) {
            $shopsQuery->whereIn('id', $shopIds);
        }
        $shops = $shopsQuery->orderBy('name')->get();

        $payload = $shops->map(function ($shop) use ($stockAgg, $lowAgg) {
            $sv = $stockAgg->get($shop->id);

            $lv = $lowAgg->get($shop->id);

            return [
                'shop_id' => (int) $shop->id,
                'shop_name' => $shop->name,
                'stock_value' => round((float) ($sv->stock_value ?? 0), 2),
                'low_stock' => (int) ($lv->low_stock ?? 0),
            ];
        })->values();

        return response()->json(['shops' => $payload]);
    }

    public function getBusinessPulse(Request $request)
    {
        $request->validate([
            'date_filter' => 'nullable|string|in:today,yesterday,this_week,last_week,this_month,last_month,this_year,last_year,custom,all_time',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'shops' => 'nullable',
            'shop_id' => 'nullable|integer|exists:shops,id',
            'shop_ids' => 'nullable|array',
            'shop_ids.*' => 'integer|exists:shops,id',
        ]);

        $shopIds = $this->resolveShopIdsForDashboardAggregates($request);
        $dateRange = $this->getDateRange($request);
        $startDt = $dateRange['start_datetime'];
        $endDt = $dateRange['end_datetime'];

        $ordersBase = Order::query()
            ->whereNotNull('shop_id')
            ->when($shopIds->isNotEmpty(), fn ($q) => $q->whereIn('shop_id', $shopIds))
            ->whereBetween('order_date', [$startDt, $endDt]);

        $miniSales = (float) (clone $ordersBase)->sum('total');
        $miniOrders = (int) (clone $ordersBase)->count();
        $invoiceDiscount = (float) (clone $ordersBase)->sum('invoice_discount');

        $detailsAgg = OrderDetails::query()
            ->joinSub((clone $ordersBase)->select(['id', 'shop_id']), 'fo', function ($join) {
                $join->on('order_details.order_id', '=', 'fo.id');
            })
            ->selectRaw('
                COALESCE(SUM(order_details.quantity * COALESCE(NULLIF(order_details.cost_per_unit, 0), 0)), 0) as cogs,
                COALESCE(SUM(COALESCE(order_details.item_discount, 0)), 0) as line_discount
            ')
            ->first();

        $miniCogs = (float) ($detailsAgg->cogs ?? 0);
        $lineDiscount = (float) ($detailsAgg->line_discount ?? 0);
        $miniProfit = $miniSales - $miniCogs - ($invoiceDiscount + $lineDiscount);

        $topShopAgg = (clone $ordersBase)
            ->reorder()
            ->selectRaw('shop_id, COALESCE(SUM(total), 0) as sales')
            ->groupBy('shop_id')
            ->orderByDesc('sales')
            ->first();

        $topShopName = '-';
        $topShopSales = 0.0;
        if ($topShopAgg && !empty($topShopAgg->shop_id)) {
            $topShopName = (string) (Shop::withoutGlobalScopes()->where('id', $topShopAgg->shop_id)->value('name') ?? '-');
            $topShopSales = (float) ($topShopAgg->sales ?? 0);
        }

        $lowStockCount = (int) Product::withoutGlobalScopes()
            ->whereNotNull('shop_id')
            ->when($shopIds->isNotEmpty(), fn ($q) => $q->whereIn('shop_id', $shopIds))
            ->whereRaw('COALESCE(product_store, 0) <= COALESCE(low_stock_warning, 10)')
            ->count();

        $lossRows = (clone $ordersBase)
            ->reorder()
            ->select(['id', 'shop_id']);
        $lossSales = (clone $ordersBase)
            ->reorder()
            ->selectRaw('shop_id, COALESCE(SUM(total), 0) as sales, COALESCE(SUM(invoice_discount), 0) as invoice_discount')
            ->groupBy('shop_id')
            ->get()
            ->keyBy('shop_id');
        $lossDetail = OrderDetails::query()
            ->joinSub((clone $lossRows)->select(['id', 'shop_id']), 'lo', function ($join) {
                $join->on('order_details.order_id', '=', 'lo.id');
            })
            ->selectRaw('
                lo.shop_id as shop_id,
                COALESCE(SUM(order_details.quantity * COALESCE(NULLIF(order_details.cost_per_unit, 0), 0)), 0) as cogs,
                COALESCE(SUM(COALESCE(order_details.item_discount, 0)), 0) as line_discount
            ')
            ->groupBy('lo.shop_id')
            ->get()
            ->keyBy('shop_id');
        $lossShopCount = $lossSales->keys()->merge($lossDetail->keys())->unique()->filter(function ($shopId) use ($lossSales, $lossDetail) {
            $sales = (float) ($lossSales->get($shopId)->sales ?? 0);
            $invoiceDisc = (float) ($lossSales->get($shopId)->invoice_discount ?? 0);
            $cogs = (float) ($lossDetail->get($shopId)->cogs ?? 0);
            $lineDisc = (float) ($lossDetail->get($shopId)->line_discount ?? 0);
            $net = $sales - $cogs - ($invoiceDisc + $lineDisc);
            return $net < 0;
        })->count();

        $todayStart = now()->startOfDay();
        $todayEnd = now()->endOfDay();
        $yesterdayStart = now()->subDay()->startOfDay();
        $yesterdayEnd = now()->subDay()->endOfDay();

        $salesBase = Order::query()
            ->whereNotNull('shop_id')
            ->when($shopIds->isNotEmpty(), fn ($q) => $q->whereIn('shop_id', $shopIds));

        $todaySales = (float) (clone $salesBase)
            ->whereBetween('order_date', [$todayStart, $todayEnd])
            ->sum('total');
        $yesterdaySales = (float) (clone $salesBase)
            ->whereBetween('order_date', [$yesterdayStart, $yesterdayEnd])
            ->sum('total');

        if ($yesterdaySales > 0) {
            $salesChangePct = (($todaySales - $yesterdaySales) / $yesterdaySales) * 100;
        } else {
            $salesChangePct = $todaySales > 0 ? 100 : 0;
        }
        $salesDirection = $salesChangePct > 0 ? 'up' : ($salesChangePct < 0 ? 'down' : 'same');

        return response()->json([
            'mini' => [
                'sales' => round($miniSales, 2),
                'orders' => $miniOrders,
                'profit' => round($miniProfit, 2),
            ],
            'alerts' => [
                $lowStockCount . ' products low in stock',
                $lossShopCount . ' shops in loss today',
            ],
            'sales_change' => [
                'percentage' => round($salesChangePct, 1),
                'direction' => $salesDirection,
            ],
            'top_shop' => [
                'name' => $topShopName,
                'sales' => round($topShopSales, 2),
            ],
            'updated_at' => now()->format('Y-m-d H:i:s'),
        ]);
    }

    private function parseShopIdsFromFilter($shopsInput, ?Collection $allowedShopIds = null): Collection
    {
        $shopIds = collect();
        if (is_array($shopsInput)) {
            $shopIds = collect($shopsInput)->map(fn ($id) => (int) $id)->filter();
        } elseif (is_string($shopsInput) && $shopsInput !== '' && $shopsInput !== 'all') {
            $shopIds = collect([(int) $shopsInput])->filter();
        } elseif (is_numeric($shopsInput)) {
            $shopIds = collect([(int) $shopsInput])->filter();
        }
        $shopIds = $shopIds->unique()->values();

        if ($allowedShopIds === null) {
            return $shopIds;
        }

        if ($allowedShopIds->isEmpty()) {
            return collect();
        }

        if ($shopIds->isEmpty()) {
            return $allowedShopIds->values();
        }

        return $shopIds
            ->intersect($allowedShopIds)
            ->values();
    }

    /**
     * Shop IDs for multi-shop dashboard aggregates (insights, shop performance, pulse, inventory).
     * Aligns with super-admin KPI when {@see MotherShopSuperAdmin} passes shop_id / shop_ids or shops=all.
     */
    private function resolveShopIdsForDashboardAggregates(Request $request): Collection
    {
        $user = auth()->user();
        $allShopIds = Shop::withoutGlobalScopes()->pluck('id')->values();

        $requested = $request->input('shop_ids');
        if (! is_array($requested) && $request->filled('shop_id')) {
            $requested = [$request->input('shop_id')];
        }
        $requested = is_array($requested) ? array_map('intval', array_filter($requested)) : [];
        $fromExplicit = collect($requested)
            ->map(fn ($id) => (int) $id)
            ->filter(fn (int $id) => $id > 0 && $allShopIds->contains($id))
            ->unique()
            ->values();

        if ($fromExplicit->isNotEmpty()) {
            return $fromExplicit;
        }

        if ($user && MotherShopSuperAdmin::allows($user)) {
            $shopsInput = $request->input('shops');
            if ($shopsInput === 'all' || $shopsInput === null || $shopsInput === '') {
                return $allShopIds;
            }

            $parsed = $this->parseShopIdsFromFilter($shopsInput, null);
            if ($parsed->isNotEmpty()) {
                return $parsed
                    ->map(fn ($id) => (int) $id)
                    ->filter(fn (int $id) => $id > 0 && $allShopIds->contains($id))
                    ->values();
            }

            return $allShopIds;
        }

        return $this->parseShopIdsFromFilter($request->input('shops'), $this->dashboardScopeShopIds());
    }

    private function buildShopPerformanceRows(Request $request)
    {
        $shopIds = $this->resolveShopIdsForDashboardAggregates($request);
        $dateRange = $this->getDateRange($request);
        $startDt = $dateRange['start_datetime'];
        $endDt = $dateRange['end_datetime'];

        // Match SuperAdminDashboardController::aggregateKpis + /orders/all: exclude soft-deleted rows.
        $ordersAggQuery = Order::query()
            ->selectRaw('shop_id, COALESCE(SUM(total), 0) as sales, COUNT(*) as orders_count, COALESCE(SUM(invoice_discount), 0) as invoice_discount')
            ->whereNotNull('shop_id')
            ->whereBetween('order_date', [$startDt, $endDt])
            ->groupBy('shop_id');
        if ($shopIds->isNotEmpty()) {
            $ordersAggQuery->whereIn('shop_id', $shopIds);
        }

        $ordersAgg = $ordersAggQuery->get()->keyBy('shop_id');

        $filteredOrdersForJoin = Order::query()
            ->select(['id', 'shop_id'])
            ->whereNotNull('shop_id')
            ->whereBetween('order_date', [$startDt, $endDt]);
        if ($shopIds->isNotEmpty()) {
            $filteredOrdersForJoin->whereIn('shop_id', $shopIds);
        }

        $detailAgg = OrderDetails::query()
            ->joinSub($filteredOrdersForJoin, 'fo', function ($join) {
                $join->on('order_details.order_id', '=', 'fo.id');
            })
            ->selectRaw('
                fo.shop_id as shop_id,
                COALESCE(SUM(order_details.quantity * COALESCE(NULLIF(order_details.cost_per_unit, 0), 0)), 0) as cogs,
                COALESCE(SUM(COALESCE(order_details.item_discount, 0)), 0) as line_discount
            ')
            ->groupBy('fo.shop_id')
            ->get()
            ->keyBy('shop_id');

        $shopsQuery = Shop::withoutGlobalScopes()->select(['id', 'name']);
        if ($shopIds->isNotEmpty()) {
            $shopsQuery->whereIn('id', $shopIds);
        }
        $shops = $shopsQuery->orderBy('name')->get();

        return $shops->map(function ($shop) use ($ordersAgg, $detailAgg) {
            $orderData = $ordersAgg->get($shop->id);
            $detailData = $detailAgg->get($shop->id);

            $sales = (float) ($orderData->sales ?? 0);
            $orders = (int) ($orderData->orders_count ?? 0);
            $invoiceDiscount = (float) ($orderData->invoice_discount ?? 0);
            $lineDiscount = (float) ($detailData->line_discount ?? 0);
            $cogs = (float) ($detailData->cogs ?? 0);
            $gross = $sales - $cogs;
            $discount = $invoiceDiscount + $lineDiscount;
            $net = $gross - $discount;
            $margin = $sales > 0 ? round(($net / $sales) * 100, 2) : 0;

            return [
                'shop_id' => (int) $shop->id,
                'shop_name' => $shop->name,
                'sales' => round($sales, 2),
                'orders' => $orders,
                'cogs' => round($cogs, 2),
                'gross' => round($gross, 2),
                'discount' => round($discount, 2),
                'net' => round($net, 2),
                'net_profit' => round($net, 2),
                'margin' => $margin,
            ];
        })->values();
    }

    /**
     * Aggregate KPI values for a date range and shop IDs.
     * Performance: ensure orders table has indexes on order_date and shop_id.
     * For very large datasets, consider daily aggregate tables or Redis cache.
     */
    private function aggregateKpis($startDt, $endDt, $shopIds): array
    {
        $ordersQuery = Order::query()
            ->whereBetween('order_date', [$startDt, $endDt])
            ->whereIn('shop_id', $shopIds);

        $orderIds = (clone $ordersQuery)->pluck('id');
        $totalSales = (float) (clone $ordersQuery)->sum('total');
        $invoiceDiscount = (float) (clone $ordersQuery)->sum('invoice_discount');

        $lineDiscount = 0;
        $totalCogs = 0;
        if ($orderIds->isNotEmpty()) {
            $detailsAgg = OrderDetails::query()
                ->whereIn('order_id', $orderIds)
                ->selectRaw('
                    COALESCE(SUM(quantity * COALESCE(NULLIF(cost_per_unit, 0), 0)), 0) as cogs,
                    COALESCE(SUM(COALESCE(item_discount, 0)), 0) as line_disc
                ')
                ->first();
            $totalCogs = (float) ($detailsAgg->cogs ?? 0);
            $lineDiscount = (float) ($detailsAgg->line_disc ?? 0);
        }

        $totalDiscounts = $invoiceDiscount + $lineDiscount;
        $grossProfit = $totalSales - $totalCogs;
        $netProfit = $grossProfit - $totalDiscounts;
        $profitMargin = $totalSales > 0 ? ($netProfit / $totalSales) * 100 : 0;

        return [
            'total_sales' => $totalSales,
            'total_cogs' => $totalCogs,
            'gross_profit' => $grossProfit,
            'total_discounts' => $totalDiscounts,
            'net_profit' => $netProfit,
            'profit_margin' => $profitMargin,
        ];
    }

    /**
     * Default dashboard scope:
     * - Shop user: strictly their own shop_id only.
     * - Non-shop/platform user: visible shops from active shop context.
     */
    private function dashboardScopeShopIds(): Collection
    {
        $user = auth()->user();
        if ($user && $user->shop_id) {
            return collect([(int) $user->shop_id]);
        }

        if (!$user) {
            return collect();
        }

        return ActiveShop::visibleShopIds($user)
            ->map(fn ($id) => (int) $id)
            ->filter()
            ->unique()
            ->values();
    }

    /**
     * Build financial KPIs scoped strictly to authenticated user's shop_id.
     *
     * @param array{start_datetime:\Carbon\Carbon,end_datetime:\Carbon\Carbon} $dateRange
     * @return array{total_due: float, net_cash: float, total_sales: float, profit: float}
     */
    private function buildShopFinancialKpis(array $dateRange): array
    {
        $user = auth()->user();
        $shopId = $user?->shop_id ? (int) $user->shop_id : null;

        if (!$shopId) {
            return [
                'total_due' => 0.0,
                'net_cash' => 0.0,
                'total_sales' => 0.0,
                'profit' => 0.0,
            ];
        }

        $startDt = $dateRange['start_datetime'];
        $endDt = $dateRange['end_datetime'];

        $cashFlowQuery = AccountTransaction::query()
            ->where('shop_id', $shopId)
            ->whereBetween('transaction_date', [$startDt, $endDt])
            ->whereIn('account_type', [
                AccountTransaction::ACCOUNT_TYPE_CASH,
                AccountTransaction::ACCOUNT_TYPE_BANK,
            ]);

        $totalInflow = (float) (clone $cashFlowQuery)
            ->where('direction', AccountTransaction::DIRECTION_DEBIT)
            ->sum('amount');
        $totalOutflow = (float) (clone $cashFlowQuery)
            ->where('direction', AccountTransaction::DIRECTION_CREDIT)
            ->sum('amount');
        $netCash = $totalInflow - $totalOutflow;

        $ordersInRange = Order::query()
            ->where('shop_id', $shopId)
            ->whereBetween('order_date', [$startDt, $endDt]);

        $totalDue = (float) (clone $ordersInRange)->sum('due');
        $totalSales = (float) (clone $ordersInRange)
            ->whereIn('order_status', ['complete', InterShopTransferStatus::COMPLETED])
            ->sum('total');

        $totalCost = (float) OrderDetails::query()
            ->join('orders', function ($join) use ($shopId) {
                $join->on('orders.id', '=', 'order_details.order_id')
                    ->where('orders.shop_id', '=', $shopId)
                    ->whereIn('orders.order_status', ['complete', InterShopTransferStatus::COMPLETED])
                    ->whereNull('orders.deleted_at');
            })
            ->leftJoin('products', 'products.id', '=', 'order_details.product_id')
            ->whereBetween('orders.order_date', [$startDt, $endDt])
            ->selectRaw('COALESCE(SUM(order_details.quantity * COALESCE(NULLIF(order_details.cost_per_unit, 0), products.buying_price, 0)), 0) as total_cost')
            ->value('total_cost');

        return [
            'total_due' => round($totalDue, 2),
            'net_cash' => round($netCash, 2),
            'total_sales' => round($totalSales, 2),
            'profit' => round($totalSales - $totalCost, 2),
        ];
    }

    /**
     * Build Revenue vs Cost vs Profit series for dashboard chart.
     *
     * - Strictly scoped to authenticated user's shop_id
     * - Uses global date filter range
     * - Auto grouping: <=31 days => daily, otherwise monthly
     * - Revenue: completed orders total
     * - Cost: order_details quantity * (cost_per_unit fallback product buying_price)
     * - Profit: Revenue - Cost
     *
     * @param array{start_datetime:\Carbon\Carbon,end_datetime:\Carbon\Carbon} $dateRange
     * @param string|null $requestedGroupBy
     * @return array{
     *   labels: array<int,string>,
     *   revenue: array<int,float>,
     *   cost: array<int,float>,
     *   profit: array<int,float>,
     *   group_by: string,
     *   auto_group_by: string,
     *   manual_override: bool
     * }
     */
    private function buildRevenueVsCostSeries(array $dateRange, ?string $requestedGroupBy = null): array
    {
        $shopId = (int) (auth()->user()?->shop_id ?? 0);
        $startDt = $dateRange['start_datetime']->copy()->startOfDay();
        $endDt = $dateRange['end_datetime']->copy()->endOfDay();
        $autoGroupBy = ($startDt->diffInDays($endDt) + 1) <= 31 ? 'daily' : 'monthly';

        $requestedGroupBy = is_string($requestedGroupBy) ? strtolower($requestedGroupBy) : null;
        $selectedGroupBy = in_array($requestedGroupBy, ['daily', 'monthly'], true)
            ? $requestedGroupBy
            : $autoGroupBy;
        $isDaily = $selectedGroupBy === 'daily';

        if ($shopId <= 0) {
            return [
                'labels' => [],
                'revenue' => [],
                'cost' => [],
                'profit' => [],
                'group_by' => $selectedGroupBy,
                'auto_group_by' => $autoGroupBy,
                'manual_override' => $requestedGroupBy !== null,
            ];
        }

        $groupExpr = $isDaily
            ? 'DATE(order_date)'
            : "DATE_FORMAT(order_date, '%Y-%m')";
        $groupExprOrders = $isDaily
            ? 'DATE(orders.order_date)'
            : "DATE_FORMAT(orders.order_date, '%Y-%m')";

        $revenueRows = Order::query()
            ->where('shop_id', $shopId)
            ->whereIn('order_status', ['complete', InterShopTransferStatus::COMPLETED])
            ->whereBetween('order_date', [$startDt, $endDt])
            ->selectRaw($groupExpr . ' as d, COALESCE(SUM(total), 0) as amount')
            ->groupBy('d')
            ->orderBy('d')
            ->get();

        $costRows = OrderDetails::query()
            ->join('orders', function ($join) use ($shopId) {
                $join->on('orders.id', '=', 'order_details.order_id')
                    ->where('orders.shop_id', '=', $shopId)
                    ->whereIn('orders.order_status', ['complete', InterShopTransferStatus::COMPLETED])
                    ->whereNull('orders.deleted_at');
            })
            ->leftJoin('products', 'products.id', '=', 'order_details.product_id')
            ->whereBetween('orders.order_date', [$startDt, $endDt])
            ->selectRaw('
                ' . $groupExprOrders . ' as d,
                COALESCE(
                    SUM(order_details.quantity * COALESCE(NULLIF(order_details.cost_per_unit, 0), products.buying_price, 0)),
                    0
                ) as amount
            ')
            ->groupBy('d')
            ->orderBy('d')
            ->get();

        $revenueMap = $revenueRows->pluck('amount', 'd');
        $costMap = $costRows->pluck('amount', 'd');

        $labels = [];
        $revenue = [];
        $cost = [];
        $profit = [];

        $periodStart = $isDaily ? $startDt->copy() : $startDt->copy()->startOfMonth();
        $periodEnd = $isDaily ? $endDt->copy() : $endDt->copy()->startOfMonth();
        $periodStep = $isDaily ? '1 day' : '1 month';

        foreach (CarbonPeriod::create($periodStart, $periodStep, $periodEnd) as $date) {
            $key = $isDaily ? $date->format('Y-m-d') : $date->format('Y-m');
            $r = round((float) ($revenueMap[$key] ?? 0), 2);
            $c = round((float) ($costMap[$key] ?? 0), 2);
            $labels[] = $key;
            $revenue[] = $r;
            $cost[] = $c;
            $profit[] = round($r - $c, 2);
        }

        return [
            'labels' => $labels,
            'revenue' => $revenue,
            'cost' => $cost,
            'profit' => $profit,
            'group_by' => $selectedGroupBy,
            'auto_group_by' => $autoGroupBy,
            'manual_override' => $requestedGroupBy !== null,
        ];
    }

    /**
     * Build Orders overview series for the Overview chart.
     *
     * @param array{start_datetime:\Carbon\Carbon,end_datetime:\Carbon\Carbon} $dateRange
     * @param string|null $requestedGroupBy
     * @return array{
     *   labels: array<int,string>,
     *   orders: array<int,int>,
     *   group_by: string,
     *   auto_group_by: string,
     *   manual_override: bool
     * }
     */
    private function buildOrdersOverviewSeries(array $dateRange, ?string $requestedGroupBy = null): array
    {
        $shopId = (int) (auth()->user()?->shop_id ?? 0);
        $startDt = $dateRange['start_datetime']->copy()->startOfDay();
        $endDt = $dateRange['end_datetime']->copy()->endOfDay();
        $autoGroupBy = ($startDt->diffInDays($endDt) + 1) <= 31 ? 'daily' : 'monthly';

        $requestedGroupBy = is_string($requestedGroupBy) ? strtolower($requestedGroupBy) : null;
        $selectedGroupBy = in_array($requestedGroupBy, ['daily', 'monthly'], true)
            ? $requestedGroupBy
            : $autoGroupBy;
        $isDaily = $selectedGroupBy === 'daily';

        if ($shopId <= 0) {
            return [
                'labels' => [],
                'orders' => [],
                'group_by' => $selectedGroupBy,
                'auto_group_by' => $autoGroupBy,
                'manual_override' => $requestedGroupBy !== null,
            ];
        }

        $groupExpr = $isDaily
            ? 'DATE(order_date)'
            : "DATE_FORMAT(order_date, '%Y-%m')";

        $rows = Order::query()
            ->where('shop_id', $shopId)
            ->whereBetween('order_date', [$startDt, $endDt])
            ->selectRaw($groupExpr . ' as d, COUNT(*) as c')
            ->groupBy('d')
            ->orderBy('d')
            ->get();

        $countMap = $rows->pluck('c', 'd');
        $labels = [];
        $orders = [];

        $periodStart = $isDaily ? $startDt->copy() : $startDt->copy()->startOfMonth();
        $periodEnd = $isDaily ? $endDt->copy() : $endDt->copy()->startOfMonth();
        $periodStep = $isDaily ? '1 day' : '1 month';

        foreach (CarbonPeriod::create($periodStart, $periodStep, $periodEnd) as $date) {
            $key = $isDaily ? $date->format('Y-m-d') : $date->format('Y-m');
            $labels[] = $key;
            $orders[] = (int) ($countMap[$key] ?? 0);
        }

        return [
            'labels' => $labels,
            'orders' => $orders,
            'group_by' => $selectedGroupBy,
            'auto_group_by' => $autoGroupBy,
            'manual_override' => $requestedGroupBy !== null,
        ];
    }
}
