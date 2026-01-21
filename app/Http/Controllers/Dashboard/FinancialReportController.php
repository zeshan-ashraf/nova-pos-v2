<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Dashboard\Traits\ReportTrait;
use App\Models\Order;
use App\Models\Purchase;
use App\Models\Expense;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class FinancialReportController extends Controller
{
    use ReportTrait;

    public function profitLoss(Request $request)
    {
        $authUser = auth()->user();
        $dateRange = $this->getDateRange($request);
        $shopFilter = $this->getShopFilter($request, $authUser);

        // Revenue (Sales)
        $revenueQuery = Order::whereBetween('order_date', [$dateRange['start_datetime'], $dateRange['end_datetime']]);
        $this->applyShopFilter($revenueQuery, $shopFilter['shop_ids']);
        $revenue = $revenueQuery->sum('total');

        // COGS (Cost of Goods Sold - Purchase costs)
        $cogsQuery = Purchase::whereBetween('purchase_date', [$dateRange['start_datetime'], $dateRange['end_datetime']]);
        $this->applyShopFilter($cogsQuery, $shopFilter['shop_ids']);
        $cogs = $cogsQuery->sum('total');

        // Expenses
        $expenseQuery = Expense::whereBetween('date', [$dateRange['start_datetime'], $dateRange['end_datetime']]);
        $this->applyShopFilter($expenseQuery, $shopFilter['shop_ids']);
        $expenses = $expenseQuery->sum('activity_cost');

        // Gross Profit
        $grossProfit = $revenue - $cogs;
        
        // Net Profit
        $netProfit = $grossProfit - $expenses;

        // Profit Margin
        $profitMargin = $revenue > 0 ? ($netProfit / $revenue) * 100 : 0;

        return view('reports.financial.profit-loss', compact(
            'dateRange', 'shopFilter', 'revenue', 'cogs', 'expenses', 
            'grossProfit', 'netProfit', 'profitMargin'
        ));
    }

    public function revenue(Request $request)
    {
        return view('reports.financial.revenue');
    }

    public function expense(Request $request)
    {
        return view('reports.financial.expense');
    }

    public function cashFlow(Request $request)
    {
        return view('reports.financial.cash-flow');
    }
}
