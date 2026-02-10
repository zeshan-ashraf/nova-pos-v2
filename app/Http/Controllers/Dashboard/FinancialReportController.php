<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Dashboard\Traits\ReportTrait;
use App\Models\AccountTransaction;
use App\Models\Activity;
use App\Models\Order;
use App\Models\StockLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Financial reports: ledger-consistent, multi-tenant by shop_id.
 * Source of truth: account_transactions, expenses (activities), orders (revenue only), stock_logs (COGS only).
 * No direct reliance on orders/purchases for cash movement; cash flow from account_transactions only.
 */
class FinancialReportController extends Controller
{
    use ReportTrait;

    /**
     * REPORT 1: Revenue Report
     * URL: /reports/financial/revenue
     * Definition: Revenue = money earned from SALES (not cash received). Revenue ≠ cash flow.
     * Source: orders table (gross revenue). Includes cash, bank, and credit sales (even if unpaid).
     */
    public function revenue(Request $request)
    {
        $authUser = auth()->user();
        $dateRange = $this->getDateRange($request);
        $shopFilter = $this->getShopFilter($request, $authUser);

        // Gross revenue: sum of order totals in date range (all sales, paid or not)
        $revenueQuery = Order::query()
            ->whereBetween('order_date', [$dateRange['start_datetime'], $dateRange['end_datetime']]);
        $this->applyShopFilter($revenueQuery, $shopFilter['shop_ids']);
        $totalRevenue = (float) (clone $revenueQuery)->sum('total');

        // Group by date for breakdown (defensive: empty collection if no data)
        $byDate = (clone $revenueQuery)
            ->select(DB::raw('DATE(order_date) as date'), DB::raw('SUM(total) as revenue'))
            ->groupBy(DB::raw('DATE(order_date)'))
            ->orderBy('date')
            ->get();

        return view('reports.financial.revenue', [
            'dateRange'     => $dateRange,
            'shopFilter'    => $shopFilter,
            'totalRevenue'  => $totalRevenue,
            'byDate'        => $byDate,
        ]);
    }

    /**
     * REPORT 2: Expense Report
     * URL: /reports/financial/expense
     * Definition: Expenses = money spent by the business.
     * Source: expenses table (activities). Include only approved/posted when status column exists; currently all rows treated as posted.
     */
    public function expense(Request $request)
    {
        $authUser = auth()->user();
        $dateRange = $this->getDateRange($request);
        $shopFilter = $this->getShopFilter($request, $authUser);

        $expenseQuery = Activity::query()
            ->whereBetween('date', [$dateRange['start_datetime'], $dateRange['end_datetime']]);
        $this->applyShopFilter($expenseQuery, $shopFilter['shop_ids']);
        $totalExpenses = (float) (clone $expenseQuery)->sum('activity_cost');

        // Group by date
        $byDate = (clone $expenseQuery)
            ->select('date', DB::raw('SUM(activity_cost) as total'))
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        // Group by expense category (expenses.expense_title via expense_id)
        $byCategory = (clone $expenseQuery)
            ->leftJoin('expenses', 'activities.expense_id', '=', 'expenses.id')
            ->select(DB::raw("COALESCE(expenses.expense_title, 'Uncategorized') as title"), DB::raw('SUM(activities.activity_cost) as total'))
            ->groupBy(DB::raw("COALESCE(expenses.expense_title, 'Uncategorized')"))
            ->orderByDesc('total')
            ->get();

        return view('reports.financial.expense', [
            'dateRange'      => $dateRange,
            'shopFilter'     => $shopFilter,
            'totalExpenses'  => $totalExpenses,
            'byDate'         => $byDate,
            'byCategory'     => $byCategory,
        ]);
    }

    /**
     * REPORT 3: Cash Flow Report
     * URL: /reports/financial/cash-flow
     * Definition: Actual money movement. Ledger only; no unpaid sales/purchases.
     * Business rule for UI: credit => INFLOW (money received), debit => OUTFLOW (money spent).
     * Opening balance (source_type=opening) appears as inflow when stored as credit; excluded from P&L.
     * Source: account_transactions only. shop_id filter applied everywhere.
     */
    public function cashFlow(Request $request)
    {
        $authUser = auth()->user();
        $dateRange = $this->getDateRange($request);
        $shopFilter = $this->getShopFilter($request, $authUser);

        $baseQuery = AccountTransaction::query()
            ->whereBetween('transaction_date', [$dateRange['start_datetime'], $dateRange['end_datetime']]);
        $this->applyShopFilter($baseQuery, $shopFilter['shop_ids']);

        // Cash flow UI mapping: credit = inflow (money received), debit = outflow (money spent). Defensive: empty set => zero.
        $totalInflow = (float) (clone $baseQuery)->where('direction', AccountTransaction::DIRECTION_CREDIT)->sum('amount');
        $totalOutflow = (float) (clone $baseQuery)->where('direction', AccountTransaction::DIRECTION_DEBIT)->sum('amount');
        $netCashFlow = $totalInflow - $totalOutflow;

        // Group by transaction_date (by day), account_type, account_ref_id. Inflow = credit, outflow = debit. Defensive: empty shop list => no rows.
        $byDateAccountQuery = AccountTransaction::query()
            ->leftJoin('bank_shop', 'bank_shop.id', '=', 'account_transactions.account_ref_id')
            ->leftJoin('banks', 'banks.id', '=', 'bank_shop.bank_id')
            ->whereBetween('account_transactions.transaction_date', [$dateRange['start_datetime'], $dateRange['end_datetime']]);
        $this->applyShopFilter($byDateAccountQuery, $shopFilter['shop_ids'], 'account_transactions.shop_id');
        $byDateAccountQuery
            ->select(
                DB::raw('DATE(account_transactions.transaction_date) as date'),
                'account_transactions.account_type',
                'account_transactions.account_ref_id',
                DB::raw('MAX(banks.name) as bank_name'),
                DB::raw("SUM(CASE WHEN account_transactions.direction = 'credit' THEN account_transactions.amount ELSE 0 END) as inflow"),
                DB::raw("SUM(CASE WHEN account_transactions.direction = 'debit' THEN account_transactions.amount ELSE 0 END) as outflow")
            )
            ->groupBy(DB::raw('DATE(account_transactions.transaction_date)'), 'account_transactions.account_type', 'account_transactions.account_ref_id')
            ->orderBy('date')
            ->orderBy('account_transactions.account_type');

        $byDateAccount = $byDateAccountQuery->get();

        // Human-readable account name: cash => 'Cash', bank => banks.name or 'Unknown Bank'
        $byDateAccount->transform(function ($row) {
            $row->account_name = $row->account_type === AccountTransaction::ACCOUNT_TYPE_CASH
                ? 'Cash'
                : ($row->bank_name ?? 'Unknown Bank');
            return $row;
        });

        return view('reports.financial.cash-flow', [
            'dateRange'       => $dateRange,
            'shopFilter'      => $shopFilter,
            'totalInflow'     => $totalInflow,
            'totalOutflow'    => $totalOutflow,
            'netCashFlow'     => $netCashFlow,
            'byDateAccount'   => $byDateAccount,
        ]);
    }

    /**
     * REPORT 4: Profit & Loss Report
     * URL: /reports/financial/profit-loss
     * Definition: Profit = Revenue - COGS - Expenses. Opening balance does NOT affect profit.
     * Revenue: same as Revenue Report (orders). COGS: from stock_logs (source_type=sale, direction=out), qty × purchase cost. Expenses: same as Expense Report.
     */
    public function profitLoss(Request $request)
    {
        $authUser = auth()->user();
        $dateRange = $this->getDateRange($request);
        $shopFilter = $this->getShopFilter($request, $authUser);

        // Revenue: gross from orders (all sales in date range)
        $revenueQuery = Order::query()
            ->whereBetween('order_date', [$dateRange['start_datetime'], $dateRange['end_datetime']]);
        $this->applyShopFilter($revenueQuery, $shopFilter['shop_ids']);
        $revenue = (float) (clone $revenueQuery)->sum('total');

        // COGS: stock_logs where source_type=sale, direction=out; cost = qty × products.buying_price only.
        $orderIdsInRange = Order::query()
            ->whereBetween('order_date', [$dateRange['start_datetime'], $dateRange['end_datetime']]);
        $this->applyShopFilter($orderIdsInRange, $shopFilter['shop_ids']);
        $orderIdsInRange = $orderIdsInRange->pluck('id')->map(fn ($id) => (string) $id)->values();

        $cogs = 0.0;
        if ($orderIdsInRange->isNotEmpty()) {
            $cogsQuery = StockLog::query()
                ->join('products', 'stock_logs.product_id', '=', 'products.id')
                ->where('stock_logs.source_type', 'sale')
                ->where('stock_logs.direction', 'out')
                ->whereIn('stock_logs.source_id', $orderIdsInRange);
            $this->applyShopFilter($cogsQuery, $shopFilter['shop_ids'], 'stock_logs.shop_id');
            $cogs = (float) ((clone $cogsQuery)->selectRaw('SUM(stock_logs.qty * COALESCE(products.buying_price, 0)) as cogs')->value('cogs') ?? 0);
        }

        // Expenses: sum from activities table, same logic as Expense Report
        $expenseQuery = Activity::query()
            ->whereBetween('date', [$dateRange['start_datetime'], $dateRange['end_datetime']]);
        $this->applyShopFilter($expenseQuery, $shopFilter['shop_ids']);
        $expenses = (float) (clone $expenseQuery)->sum('activity_cost');

        // P&L totals (defensive: avoid negative margins from bad data)
        $grossProfit = $revenue - $cogs;
        $netProfit = $grossProfit - $expenses;
        $profitMargin = $revenue > 0 ? (($netProfit / $revenue) * 100) : 0;

        return view('reports.financial.profit-loss', [
            'dateRange'    => $dateRange,
            'shopFilter'   => $shopFilter,
            'revenue'      => $revenue,
            'cogs'         => $cogs,
            'expenses'     => $expenses,
            'grossProfit'  => $grossProfit,
            'netProfit'    => $netProfit,
            'profitMargin' => $profitMargin,
        ]);
    }
}
