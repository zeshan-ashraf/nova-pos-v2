<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Dashboard\Traits\ReportTrait;
use App\Models\AccountTransaction;
use App\Models\Activity;
use App\Models\Order;
use App\Models\OrderDetails;
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
     * Definition: Actual money movement for cash/bank only. deleted_at IS NULL via SoftDeletes.
     * Business rule for UI: debit => INFLOW, credit => OUTFLOW (for account_type cash/bank only).
     * Source: account_transactions only. shop_id and date range filter applied.
     */
    public function cashFlow(Request $request)
    {
        $authUser = auth()->user();
        $dateRange = $this->getDateRange($request);
        $shopFilter = $this->getShopFilter($request, $authUser);

        // Cash flow: only cash and bank; no source_type used for inflow/outflow
        $baseQuery = AccountTransaction::query()
            ->whereBetween('transaction_date', [$dateRange['start_datetime'], $dateRange['end_datetime']])
            ->whereIn('account_type', [AccountTransaction::ACCOUNT_TYPE_CASH, AccountTransaction::ACCOUNT_TYPE_BANK]);
        $this->applyShopFilter($baseQuery, $shopFilter['shop_ids']);

        // Correct logic: debit = Inflow, credit = Outflow
        $totalInflow = (float) (clone $baseQuery)->where('direction', AccountTransaction::DIRECTION_DEBIT)->sum('amount');
        $totalOutflow = (float) (clone $baseQuery)->where('direction', AccountTransaction::DIRECTION_CREDIT)->sum('amount');
        $netCashFlow = $totalInflow - $totalOutflow;

        // Group by transaction_date, account (cash vs each bank). Inflow = debit, outflow = credit.
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
                DB::raw("SUM(CASE WHEN account_transactions.direction = 'debit' THEN account_transactions.amount ELSE 0 END) as inflow"),
                DB::raw("SUM(CASE WHEN account_transactions.direction = 'credit' THEN account_transactions.amount ELSE 0 END) as outflow")
            )
            ->groupBy(DB::raw('DATE(account_transactions.transaction_date)'), 'account_transactions.account_type', 'account_transactions.account_ref_id')
            ->orderBy('date', 'asc')
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
     *
     * Rules (no stock_logs; order_details only for COGS and Sales):
     * - Sales = SUM(order_details.unitcost * order_details.quantity). Date filter: orders.order_date (same as orders list / revenue).
     * - COGS = SUM(order_details.quantity * order_details.cost_per_unit). No fallback, no ABS.
     * - Gross Profit = Total Sales - COGS.
     * - Net Profit = Gross Profit - Operating Expenses. (Discounts not shown on P&L.)
     * - Soft-deleted orders/order_details excluded.
     */
    public function profitLoss(Request $request)
    {
        $authUser = auth()->user();
        $request->mergeIfMissing(['date_filter' => 'all']);
        $dateRange = $this->getDateRange($request);
        $shopFilter = $this->getShopFilter($request, $authUser);

        $start = $dateRange['start_datetime'];
        $end = $dateRange['end_datetime'];
        $dateScoped = $start !== null && $end !== null;

        // 1) Sales, COGS — single query (orders + order_details), one row per line, no duplicate inflation
        $salesCogsQuery = Order::query()
            ->join('order_details', function ($join) {
                $join->on('orders.id', '=', 'order_details.order_id')
                    ->whereNull('order_details.deleted_at');
            })
            ->when($dateScoped, fn ($q) => $q->whereBetween('orders.order_date', [$start, $end]))
            ->selectRaw("
                SUM(order_details.unitcost * order_details.quantity) AS total_sales,
                SUM(order_details.quantity * COALESCE(order_details.cost_per_unit, 0)) AS cogs
            ");
        $this->applyShopFilter($salesCogsQuery, $shopFilter['shop_ids'], 'orders.shop_id');
        $row = $salesCogsQuery->first();

        $totalSales = (float) ($row->total_sales ?? 0);
        $cogs = (float) ($row->cogs ?? 0);

        // 2) Gross Profit = Total Sales - COGS
        $grossProfit = $totalSales - $cogs;

        // 3) Operating Expenses from account_transactions: grouped by expense category, with individual lines
        $expensesQuery = AccountTransaction::query()
            ->leftJoin('activities', function ($join) {
                $join->on('account_transactions.source_id', '=', 'activities.id')
                    ->whereNull('activities.deleted_at');
            })
            ->leftJoin('expenses', 'activities.expense_id', '=', 'expenses.id')
            ->where('account_transactions.source_type', AccountTransaction::SOURCE_EXPENSE)
            ->where('account_transactions.direction', AccountTransaction::DIRECTION_DEBIT)
            ->whereNull('account_transactions.deleted_at')
            // Operating expenses on P&L should exclude purchase-linked expenses.
            // Keep current LEFT JOIN behavior for unmatched activity rows (shown as Uncategorized).
            ->whereNull('activities.purchase_id');
        $this->applyShopFilter($expensesQuery, $shopFilter['shop_ids'], 'account_transactions.shop_id');
        if ($dateScoped) {
            $expensesQuery->whereBetween('account_transactions.transaction_date', [$start, $end]);
        }
        $expenseRows = $expensesQuery
            ->select(
                'account_transactions.id',
                'account_transactions.transaction_date',
                'account_transactions.description',
                'account_transactions.amount',
                DB::raw("COALESCE(expenses.expense_title, 'Uncategorized') as category_name")
            )
            ->orderBy('category_name')
            ->orderBy('account_transactions.transaction_date')
            ->orderBy('account_transactions.id')
            ->get();

        // Group by category (alphabetical), build per-category totals and line items
        $expensesByCategory = collect();
        foreach ($expenseRows->groupBy('category_name') as $catName => $rows) {
            $total = (float) $rows->sum('amount');
            $lines = $rows->map(fn ($r) => [
                'date' => $r->transaction_date?->format('Y-m-d') ?? '',
                'description' => $r->description ?? '—',
                'amount' => (float) $r->amount,
            ])->values()->all();
            $expensesByCategory->push([
                'name' => $catName,
                'total' => $total,
                'lines' => $lines,
            ]);
        }
        $expensesByCategory = $expensesByCategory->sortBy('name')->values();
        $expenses = (float) $expenseRows->sum('amount');

        // 4) Net Profit = Gross Profit - Operating Expenses (discounts not shown on P&L)
        $netProfit = $grossProfit - $expenses;
        $revenue = $totalSales;
        $profitMargin = $revenue > 0 ? (($netProfit / $revenue) * 100) : 0;

        return view('reports.financial.profit-loss', [
            'dateRange'          => $dateRange,
            'shopFilter'         => $shopFilter,
            'revenue'            => $revenue,
            'cogs'               => $cogs,
            'expenses'           => $expenses,
            'expensesByCategory' => $expensesByCategory,
            'grossProfit'        => $grossProfit,
            'netProfit'          => $netProfit,
            'profitMargin'       => $profitMargin,
        ]);
    }

    /**
     * P&L line-level detail (debug): one row per order line with sales and COGS.
     * Same scope as P&L (date range + shop). COGS from order_details.cost_per_unit only (no fallback).
     */
    public function profitLossLineDetail(Request $request)
    {
        $authUser = auth()->user();
        $request->mergeIfMissing(['date_filter' => 'all']);
        $dateRange = $this->getDateRange($request);
        $shopFilter = $this->getShopFilter($request, $authUser);

        $start = $dateRange['start_datetime'];
        $end = $dateRange['end_datetime'];
        $dateScoped = $start !== null && $end !== null;

        $query = Order::query()
            ->join('order_details', function ($join) {
                $join->on('orders.id', '=', 'order_details.order_id')
                    ->whereNull('order_details.deleted_at');
            })
            ->join('products', 'order_details.product_id', '=', 'products.id')
            ->when($dateScoped, fn ($q) => $q->whereBetween('orders.order_date', [$start, $end]))
            ->select(
                'orders.id as order_id',
                'orders.invoice_no',
                'orders.order_date',
                'products.product_name',
                'products.product_code',
                'order_details.quantity',
                'order_details.unit',
                'order_details.unitcost as unit_sell_price',
                'order_details.total as line_revenue',
                DB::raw('COALESCE(order_details.cost_per_unit, 0) as cost_per_unit_used'),
                DB::raw('order_details.quantity * COALESCE(order_details.cost_per_unit, 0) as line_cogs')
            )
            ->orderBy('orders.order_date')
            ->orderBy('orders.id')
            ->orderBy('order_details.id');

        $this->applyShopFilter($query, $shopFilter['shop_ids'], 'orders.shop_id');
        $rows = $query->get();

        return view('reports.financial.profit-loss-line-detail', [
            'dateRange'  => $dateRange,
            'shopFilter' => $shopFilter,
            'rows'       => $rows,
        ]);
    }
}
