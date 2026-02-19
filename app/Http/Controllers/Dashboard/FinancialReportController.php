<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Dashboard\Traits\ReportTrait;
use App\Models\AccountTransaction;
use App\Models\Activity;
use App\Models\Order;
use App\Models\OrderDetails;
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

        // Cash flow: only cash and bank movements; customer/supplier ledger entries must NOT affect
        $baseQuery = AccountTransaction::query()
            ->whereBetween('transaction_date', [$dateRange['start_datetime'], $dateRange['end_datetime']])
            ->whereIn('account_type', [AccountTransaction::ACCOUNT_TYPE_CASH, AccountTransaction::ACCOUNT_TYPE_BANK]);
        $this->applyShopFilter($baseQuery, $shopFilter['shop_ids']);

        // Cash flow UI mapping: credit = inflow (money received), debit = outflow (money spent). Defensive: empty set => zero.
        $totalInflow = (float) (clone $baseQuery)->where('direction', AccountTransaction::DIRECTION_CREDIT)->sum('amount');
        $totalOutflow = (float) (clone $baseQuery)->where('direction', AccountTransaction::DIRECTION_DEBIT)->sum('amount');
        $netCashFlow = $totalInflow - $totalOutflow;

        // Group by transaction_date (by day), account_type, account_ref_id. Inflow = credit, outflow = debit. Only cash/bank.
        $byDateAccountQuery = AccountTransaction::query()
            ->leftJoin('bank_shop', 'bank_shop.id', '=', 'account_transactions.account_ref_id')
            ->leftJoin('banks', 'banks.id', '=', 'bank_shop.bank_id')
            ->whereBetween('account_transactions.transaction_date', [$dateRange['start_datetime'], $dateRange['end_datetime']])
            ->whereIn('account_transactions.account_type', [AccountTransaction::ACCOUNT_TYPE_CASH, AccountTransaction::ACCOUNT_TYPE_BANK]);
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
     * Definition: Revenue = Sales (ledger). COGS = cost of sold products (order_details × buying_price).
     * Gross Profit = Sales Revenue - Cost of Goods Sold. Direct inventory costing; no purchases/opening/closing stock in P&L.
     */
    public function profitLoss(Request $request)
    {
        $authUser = auth()->user();
        $dateRange = $this->getDateRange($request);
        $shopFilter = $this->getShopFilter($request, $authUser);

        $start = $dateRange['start_datetime'];
        $end = $dateRange['end_datetime'];

        // 1) Sales Revenue: sum of sale-account credits (all sales: cash + credit). Ledger is source of truth.
        //    Fallback to orders.total when ledger has no sale entries (e.g. legacy data or backfill not run).
        $salesQuery = AccountTransaction::query()
            ->where('account_type', AccountTransaction::ACCOUNT_TYPE_SALE)
            ->where('direction', AccountTransaction::DIRECTION_CREDIT)
            ->whereBetween('transaction_date', [$start, $end]);
        $this->applyShopFilter($salesQuery, $shopFilter['shop_ids']);
        $totalSales = (float) (clone $salesQuery)->sum('amount');

        if ($totalSales == 0) {
            $ordersRevenueQuery = Order::query()
                ->whereBetween('order_date', [$start, $end]);
            $this->applyShopFilter($ordersRevenueQuery, $shopFilter['shop_ids']);
            $totalSales = (float) (clone $ordersRevenueQuery)->sum('total');
        }

        // 2) COGS: from sold products only. SUM(order_details.quantity * products.buying_price)
        //    orders → order_details → products; filter by orders.shop_id, orders.order_date; exclude soft-deleted
        $cogsQuery = Order::query()
            ->join('order_details', function ($join) {
                $join->on('orders.id', '=', 'order_details.order_id')
                    ->whereNull('order_details.deleted_at');
            })
            ->join('products', 'order_details.product_id', '=', 'products.id')
            ->whereBetween('orders.order_date', [$start, $end])
            ->selectRaw('SUM(order_details.quantity * COALESCE(products.buying_price, 0)) as cost_of_goods_sold');
        $this->applyShopFilter($cogsQuery, $shopFilter['shop_ids'], 'orders.shop_id');
        $cogs = (float) $cogsQuery->value('cost_of_goods_sold');

        // 3) Gross Profit = Sales Revenue - COGS
        $grossProfit = $totalSales - $cogs;

        // 4) Total Operating Expenses: account_transactions where source_type = 'expense', direction = 'debit'
        $expensesQuery = AccountTransaction::query()
            ->where('source_type', AccountTransaction::SOURCE_EXPENSE)
            ->where('direction', AccountTransaction::DIRECTION_DEBIT);
        $this->applyShopFilter($expensesQuery, $shopFilter['shop_ids']);
        if (($dateRange['date_filter'] ?? '') !== 'all') {
            $expensesQuery->whereBetween('transaction_date', [$start, $end]);
        }
        $expenses = (float) $expensesQuery->sum('amount');

        // Operating Profit = Gross Profit - Total Operating Expenses
        $operatingProfit = $grossProfit - $expenses;
        // Net Profit (other income/expenses = 0 for now)
        $netProfit = $operatingProfit;
        $revenue = $totalSales;
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
