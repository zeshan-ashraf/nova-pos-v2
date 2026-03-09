<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Dashboard\Traits\ReportTrait;
use App\Models\Order;
use App\Models\OrderDetails;
use App\Models\Product;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Support\ActiveShop;

class DashboardController extends Controller
{
    use ReportTrait;

    public function index(){
        $authUser = auth()->user();
        $visibleShopIds = ActiveShop::visibleShopIds($authUser);

        $ordersQuery = Order::query();
        $productsQuery = Product::query();

        // Apply shop filtering for orders
        if ($authUser->shop_id) {
            $ordersQuery->whereIn('shop_id', $visibleShopIds);
        } else {
            $ordersQuery->where(function ($query) use ($visibleShopIds) {
                $query->whereNull('shop_id');
                if ($visibleShopIds->isNotEmpty()) {
                    $query->orWhereIn('shop_id', $visibleShopIds);
                }
            });
        }

        // Apply shop filtering for products
        if ($authUser->shop_id) {
            $productsQuery->whereIn('shop_id', $visibleShopIds);
        } else {
            $productsQuery->where(function ($query) use ($visibleShopIds) {
                $query->whereNull('shop_id');
                if ($visibleShopIds->isNotEmpty()) {
                    $query->orWhereIn('shop_id', $visibleShopIds);
                }
            });
        }

        return view('dashboard.index', [
            'total_paid' => (clone $ordersQuery)->sum('pay'),
            'total_due' => (clone $ordersQuery)->sum('due'),
            'complete_orders' => (clone $ordersQuery)->where('order_status', 'complete')->get(),
            'products' => (clone $productsQuery)->orderBy('product_store')->take(5)->get(),
            'new_products' => (clone $productsQuery)->orderBy('buying_date')->take(2)->get(),
        ]);
    }

    /**
     * Return consolidated KPIs as JSON for the selected date range and shops.
     * Optional: includes previous-period values and trend % for arrows.
     */
    public function getKPIs(Request $request)
    {
        $request->validate([
            'date_filter' => 'nullable|string|in:today,yesterday,this_week,last_week,this_month,last_month,this_year,last_year,custom',
            'start_date'  => 'nullable|date',
            'end_date'    => 'nullable|date|after_or_equal:start_date',
            'shop_ids'    => 'nullable|array',
            'shop_ids.*'  => 'integer|exists:shops,id',
        ]);

        $authUser = auth()->user();
        $visibleShopIds = ActiveShop::visibleShopIds($authUser);
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

    /**
     * Aggregate KPI values for a date range and shop IDs.
     * Performance: ensure orders table has indexes on order_date and shop_id.
     * For very large datasets, consider daily aggregate tables or Redis cache.
     */
    private function aggregateKpis($startDt, $endDt, $shopIds): array
    {
        $ordersQuery = Order::query()
            ->whereBetween('order_date', [
                $startDt->format('Y-m-d H:i:s'),
                $endDt->format('Y-m-d 23:59:59'),
            ])
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
}
