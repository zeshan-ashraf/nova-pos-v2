<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Dashboard\Traits\ReportTrait;
use App\Models\Activity;
use App\Models\Order;
use App\Models\OrderDetails;
use App\Models\Purchase;
use App\Models\Shop;
use Illuminate\Http\Request;

class SuperAdminDashboardController extends Controller
{
    use ReportTrait;

    /**
     * Display the Super Admin comparison dashboard.
     * Shop dropdown shows all shops (beyond active-shop scope).
     */
    public function index(Request $request)
    {
        $shops = \App\Models\Shop::orderBy('name')->get(['id', 'name']);

        return view('super_admin.dashboard', [
            'shops' => $shops,
            'erp_launch_date' => config('app.erp_launch_date', '2025-11-01'),
        ]);
    }

    /**
     * Return consolidated KPIs as JSON for the selected date range and shops.
     * Beyond scope: when no shop_ids sent, aggregates across ALL shops.
     */
    public function kpis(Request $request)
    {
        $request->validate([
            'date_filter' => 'nullable|string|in:today,yesterday,this_week,last_week,this_month,last_month,this_year,last_year,custom,all_time',
            'start_date'  => 'nullable|date',
            'end_date'    => 'nullable|date|after_or_equal:start_date',
            'shop_ids'    => 'nullable|array',
            'shop_ids.*'  => 'integer|exists:shops,id',
            'shop_id'     => 'nullable|integer|exists:shops,id',
        ]);

        $allShopIds = \App\Models\Shop::pluck('id')->values();
        $requested = $request->input('shop_ids');
        if (! is_array($requested) && $request->filled('shop_id')) {
            $requested = [$request->input('shop_id')];
        }
        $requested = is_array($requested) ? array_map('intval', array_filter($requested)) : [];
        $shopIds = count($requested) > 0
            ? collect($requested)->filter(fn ($id) => $allShopIds->contains($id))->values()
            : $allShopIds;

        $emptyKpis = [
            'total_sales' => 0, 'total_cogs' => 0, 'total_purchases' => 0, 'gross_profit' => 0,
            'total_discounts' => 0, 'total_expense' => 0, 'net_profit' => 0, 'profit_margin' => 0,
            'total_sales_trend_pct' => null, 'total_cogs_trend_pct' => null, 'total_purchases_trend_pct' => null, 'gross_profit_trend_pct' => null,
            'total_discounts_trend_pct' => null, 'total_expense_trend_pct' => null, 'net_profit_trend_pct' => null, 'profit_margin_trend_pct' => null,
        ];
        if ($shopIds->isEmpty()) {
            return response()->json($emptyKpis);
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
            if ($prev == 0) {
                return $curr != 0 ? 100 : null;
            }
            return round((($curr - $prev) / abs($prev)) * 100, 1);
        };

        return response()->json([
            'total_sales'       => round($current['total_sales'], 2),
            'total_cogs'        => round($current['total_cogs'], 2),
            'total_purchases'   => round($current['total_purchases'], 2),
            'gross_profit'      => round($current['gross_profit'], 2),
            'total_discounts'   => round($current['total_discounts'], 2),
            'total_expense'     => round($current['total_expense'], 2),
            'net_profit'        => round($current['net_profit'], 2),
            'profit_margin'     => round($current['profit_margin'], 2),
            'total_sales_trend_pct'   => $trend($current['total_sales'], $previous['total_sales']),
            'total_cogs_trend_pct'    => $trend($current['total_cogs'], $previous['total_cogs']),
            'total_purchases_trend_pct' => $trend($current['total_purchases'], $previous['total_purchases']),
            'gross_profit_trend_pct'  => $trend($current['gross_profit'], $previous['gross_profit']),
            'total_discounts_trend_pct' => $trend($current['total_discounts'], $previous['total_discounts']),
            'total_expense_trend_pct' => $trend($current['total_expense'], $previous['total_expense']),
            'net_profit_trend_pct'    => $trend($current['net_profit'], $previous['net_profit']),
            'profit_margin_trend_pct' => $previous['profit_margin'] !== null && $previous['profit_margin'] != 0
                ? $trend($current['profit_margin'], $previous['profit_margin']) : null,
        ]);
    }

    public function activities(Request $request)
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

    /**
     * Aggregate KPI values for a date range and shop IDs (beyond scope).
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
        $totalPurchases = (float) Purchase::query()
            ->whereIn('shop_id', $shopIds)
            ->whereBetween('purchase_date', [$startDt->format('Y-m-d'), $endDt->format('Y-m-d')])
            ->sum('total');
        $totalExpense = (float) Activity::query()
            ->whereIn('shop_id', $shopIds)
            ->whereBetween('date', [$startDt->format('Y-m-d'), $endDt->format('Y-m-d')])
            ->sum('activity_cost');
        $grossProfit = $totalSales - $totalCogs;
        $netProfit = $grossProfit - $totalDiscounts;
        $profitMargin = $totalSales > 0 ? ($netProfit / $totalSales) * 100 : 0;

        return [
            'total_sales' => $totalSales,
            'total_cogs' => $totalCogs,
            'total_purchases' => $totalPurchases,
            'gross_profit' => $grossProfit,
            'total_discounts' => $totalDiscounts,
            'total_expense' => $totalExpense,
            'net_profit' => $netProfit,
            'profit_margin' => $profitMargin,
        ];
    }
}

