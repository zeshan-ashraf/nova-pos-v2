<?php

namespace App\Http\Controllers\Dashboard\Traits;

use Carbon\Carbon;
use App\Support\ActiveShop;
use Illuminate\Http\Request;

trait ReportTrait
{
    /**
     * Get date range from request with default to today.
     */
    protected function getDateRange(Request $request): array
    {
        $dateFilter = $request->input('date_filter') ?? $request->input('date_range', 'today');
        $startDate = $request->input('start_date');
        $endDate = $request->input('end_date');

        $launch = config('app.erp_launch_date', '2025-11-01');

        switch ($dateFilter) {
            case 'all':
                // No date filtering at all.
                return [
                    'date_filter' => $dateFilter,
                    'start_date' => null,
                    'end_date' => null,
                    'start_datetime' => null,
                    'end_datetime' => null,
                ];
            case 'all_time':
                // Fixed ERP launch → today (ignore tampered start/end for this preset)
                $startDate = $launch;
                $endDate = Carbon::today()->format('Y-m-d');
                break;
            case 'today':
                $startDate = Carbon::today()->format('Y-m-d');
                $endDate = Carbon::today()->format('Y-m-d');
                break;
            case 'yesterday':
                $startDate = Carbon::yesterday()->format('Y-m-d');
                $endDate = Carbon::yesterday()->format('Y-m-d');
                break;
            case 'this_week':
                $startDate = Carbon::now()->startOfWeek()->format('Y-m-d');
                $endDate = Carbon::now()->endOfWeek()->format('Y-m-d');
                break;
            case 'last_week':
                $startDate = Carbon::now()->subWeek()->startOfWeek()->format('Y-m-d');
                $endDate = Carbon::now()->subWeek()->endOfWeek()->format('Y-m-d');
                break;
            case 'this_month':
                $startDate = Carbon::now()->startOfMonth()->format('Y-m-d');
                $endDate = Carbon::now()->endOfMonth()->format('Y-m-d');
                break;
            case 'last_month':
                $startDate = Carbon::now()->subMonth()->startOfMonth()->format('Y-m-d');
                $endDate = Carbon::now()->subMonth()->endOfMonth()->format('Y-m-d');
                break;
            case 'this_year':
                $startDate = Carbon::now()->startOfYear()->format('Y-m-d');
                $endDate = Carbon::now()->endOfYear()->format('Y-m-d');
                break;
            case 'last_year':
                $startDate = Carbon::now()->subYear()->startOfYear()->format('Y-m-d');
                $endDate = Carbon::now()->subYear()->endOfYear()->format('Y-m-d');
                break;
            case 'custom':
                if (!$startDate) {
                    $startDate = Carbon::today()->format('Y-m-d');
                }
                if (!$endDate) {
                    $endDate = Carbon::today()->format('Y-m-d');
                }
                break;
            default:
                $startDate = Carbon::today()->format('Y-m-d');
                $endDate = Carbon::today()->format('Y-m-d');
        }

        if (empty($startDate)) {
            $startDate = Carbon::today()->format('Y-m-d');
        }
        if (empty($endDate)) {
            $endDate = Carbon::today()->format('Y-m-d');
        }
        if (Carbon::parse($startDate)->gt(Carbon::parse($endDate))) {
            [$startDate, $endDate] = [$endDate, $startDate];
        }

        // Inclusive range in app timezone: [start 00:00:00.000000, end 23:59:59.999999]
        $startDt = Carbon::parse($startDate)->startOfDay();
        $endDt = Carbon::parse($endDate)->endOfDay();

        return [
            'date_filter' => $dateFilter,
            'start_date' => $startDate,
            'end_date' => $endDate,
            'start_datetime' => $startDt,
            'end_datetime' => $endDt,
        ];
    }

    /**
     * Get shop filter from request.
     */
    protected function getShopFilter(Request $request, $authUser): array
    {
        $visibleShopIds = ActiveShop::visibleShopIds($authUser);
        $selectedShopId = $request->input('shop_id');

        // If user is not admin (has shop_id), scope is strictly their one shop only
        if ($authUser->shop_id) {
            return [
                'shop_ids' => collect([$authUser->shop_id]),
                'selected_shop_id' => $authUser->shop_id,
                'shops' => collect(),
            ];
        }

        // Admin can see all shops and filter
        $shops = \App\Models\Shop::whereIn('id', $visibleShopIds)
            ->orderBy('name')
            ->get();

        // If no shop selected, default to all visible shops
        if (!$selectedShopId || $selectedShopId === 'all') {
            return [
                'shop_ids' => $visibleShopIds,
                'selected_shop_id' => 'all',
                'shops' => $shops,
            ];
        }

        // Filter by selected shop
        if ($visibleShopIds->contains($selectedShopId)) {
            return [
                'shop_ids' => collect([$selectedShopId]),
                'selected_shop_id' => $selectedShopId,
                'shops' => $shops,
            ];
        }

        return [
            'shop_ids' => $visibleShopIds,
            'selected_shop_id' => 'all',
            'shops' => $shops,
        ];
    }

    /**
     * Apply shop filter to query.
     */
    protected function applyShopFilter($query, $shopIds, $shopColumn = 'shop_id')
    {
        if ($shopIds->isEmpty()) {
            return $query->whereRaw('1 = 0'); // No results
        }

        return $query->whereIn($shopColumn, $shopIds);
    }

    /**
     * Get pagination row count.
     */
    protected function getRowCount(Request $request): int
    {
        $row = (int) $request->input('row', 50);
        if ($row < 1 || $row > 100) {
            return 50;
        }
        return $row;
    }
}
