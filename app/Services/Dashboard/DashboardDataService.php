<?php

namespace App\Services\Dashboard;

use App\Models\AccountTransaction;
use App\Models\Activity;
use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderDetails;
use App\Models\PaymentLog;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\SaleReturn;
use App\Models\ShopExpense;
use App\Support\InterShopTransferStatus;
use App\Services\HoldInvoiceService;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
class DashboardDataService
{
  private const CACHE_TTL_SECONDS = 120;

  /**
   * @param  array{start_datetime:\Carbon\Carbon,end_datetime:\Carbon\Carbon,date_filter?:string,start_date?:string,end_date?:string}  $dateRange
   */
  public function build(int $shopId, array $dateRange): array
  {
    if ($shopId <= 0) {
      return $this->emptyPayload($dateRange);
    }

    $cacheKey = sprintf(
      'dashboard:%d:%s:%s:%s',
      $shopId,
      $dateRange['date_filter'] ?? 'today',
      $dateRange['start_date'] ?? '',
      $dateRange['end_date'] ?? ''
    );

    return Cache::remember($cacheKey, self::CACHE_TTL_SECONDS, function () use ($shopId, $dateRange) {
      return $this->compute($shopId, $dateRange);
    });
  }

  /**
   * @param  array{start_datetime:\Carbon\Carbon,end_datetime:\Carbon\Carbon,date_filter?:string,start_date?:string,end_date?:string}  $dateRange
   */
  private function compute(int $shopId, array $dateRange): array
  {
    $startDt = $dateRange['start_datetime']->copy();
    $endDt = $dateRange['end_datetime']->copy();
    $todayStart = Carbon::today()->startOfDay();
    $todayEnd = Carbon::today()->endOfDay();
    $monthStart = Carbon::now()->startOfMonth();
    $monthEnd = Carbon::now()->endOfMonth();
    $yesterdayStart = Carbon::yesterday()->startOfDay();
    $yesterdayEnd = Carbon::yesterday()->endOfDay();

    $todaySales = $this->sumOrderTotals($shopId, $todayStart, $todayEnd);
    $yesterdaySales = $this->sumOrderTotals($shopId, $yesterdayStart, $yesterdayEnd);
    $todayProfit = $this->calcProfit($shopId, $todayStart, $todayEnd);
    $yesterdayProfit = $this->calcProfit($shopId, $yesterdayStart, $yesterdayEnd);
    $monthlySales = $this->sumOrderTotals($shopId, $monthStart, $monthEnd);
    $lastMonthStart = Carbon::now()->subMonth()->startOfMonth();
    $lastMonthEnd = Carbon::now()->subMonth()->endOfMonth();
    $lastMonthSales = $this->sumOrderTotals($shopId, $lastMonthStart, $lastMonthEnd);

    $filteredSales = $this->sumOrderTotals($shopId, $startDt, $endDt);
    $prevRange = $this->previousPeriod($startDt, $endDt);
    $prevSales = $this->sumOrderTotals($shopId, $prevRange['start'], $prevRange['end']);

    return [
      'dateRange' => $dateRange,
      'currency' => config('app.currency_symbol', 'PKR '),
      'kpis' => [
        'today_sales' => [
          'value' => round($todaySales, 2),
          'trend_pct' => $this->trendPct($todaySales, $yesterdaySales),
          'trend_label' => 'vs yesterday',
        ],
        'today_profit' => [
          'value' => round($todayProfit, 2),
          'trend_pct' => $this->trendPct($todayProfit, $yesterdayProfit),
          'trend_label' => 'vs yesterday',
        ],
        'monthly_sales' => [
          'value' => round($monthlySales, 2),
          'trend_pct' => $this->trendPct($monthlySales, $lastMonthSales),
          'trend_label' => 'vs last month',
        ],
        'pending_orders' => [
          'value' => $this->countPendingOrders($shopId),
          'trend_label' => 'open invoices',
        ],
        'low_stock_products' => [
          'value' => $this->countLowStock($shopId),
          'trend_label' => 'need attention',
        ],
        'total_receivables' => [
          'value' => round($this->totalReceivables($shopId), 2),
          'trend_label' => 'customer dues',
        ],
        'total_payables' => [
          'value' => round($this->totalPayables($shopId), 2),
          'trend_label' => 'supplier dues',
        ],
        'cash_in_hand' => [
          'value' => round($this->cashInHand($shopId), 2),
          'trend_label' => 'cash balance',
        ],
        'filtered_sales' => [
          'value' => round($filteredSales, 2),
          'trend_pct' => $this->trendPct($filteredSales, $prevSales),
          'trend_label' => 'vs previous period',
        ],
      ],
      'today_overview' => $this->todayOverview($shopId, $todayStart, $todayEnd),
      'charts' => [
        'sales_trend' => $this->salesTrendChart($shopId),
        'revenue_vs_expense' => $this->revenueVsExpenseChart($shopId),
        'top_products' => $this->topProductsChart($shopId, $startDt, $endDt),
        'payment_methods' => $this->paymentMethodsChart($shopId, $startDt, $endDt),
      ],
      'sidebar' => [
        'low_stock' => $this->lowStockAlerts($shopId),
        'recent_sales' => $this->recentSales($shopId),
        'pending_receivables' => $this->pendingReceivables($shopId),
        'top_customers' => $this->topCustomers($shopId, $startDt, $endDt),
      ],
      'tables' => [
        'recent_invoices' => $this->recentInvoices($shopId),
        'recent_purchases' => $this->recentPurchases($shopId),
        'recent_expenses' => $this->recentExpenses($shopId),
      ],
      'orders_overview' => $this->ordersOverviewForRange($shopId, $startDt, $endDt),
      'revenue_vs_cost' => $this->revenueVsCostForRange($shopId, $startDt, $endDt),
    ];
  }

  /**
   * @param  array{start_datetime:\Carbon\Carbon,end_datetime:\Carbon\Carbon}  $dateRange
   */
  private function emptyPayload(array $dateRange): array
  {
    $zeroKpi = ['value' => 0, 'trend_pct' => null, 'trend_label' => ''];
    return [
      'dateRange' => $dateRange,
      'currency' => config('app.currency_symbol', 'PKR '),
      'kpis' => array_fill_keys(
        ['today_sales', 'today_profit', 'monthly_sales', 'pending_orders', 'low_stock_products', 'total_receivables', 'total_payables', 'cash_in_hand', 'filtered_sales'],
        $zeroKpi
      ),
      'today_overview' => array_fill_keys(
        ['invoices', 'purchases', 'expenses', 'sale_returns', 'payments_received'],
        0
      ),
      'charts' => [
        'sales_trend' => ['labels' => [], 'sales' => []],
        'revenue_vs_expense' => ['labels' => [], 'sales' => [], 'expenses' => [], 'profit' => []],
        'top_products' => ['labels' => [], 'quantities' => []],
        'payment_methods' => ['labels' => [], 'amounts' => []],
      ],
      'sidebar' => [
        'low_stock' => [],
        'recent_sales' => [],
        'pending_receivables' => [],
        'top_customers' => [],
      ],
      'tables' => [
        'recent_invoices' => [],
        'recent_purchases' => [],
        'recent_expenses' => [],
      ],
      'orders_overview' => ['labels' => [], 'orders' => []],
      'revenue_vs_cost' => ['labels' => [], 'revenue' => [], 'cost' => [], 'profit' => []],
    ];
  }

  private function sumOrderTotals(int $shopId, Carbon $start, Carbon $end): float
  {
    return (float) Order::query()
      ->where('shop_id', $shopId)
      ->whereBetween('order_date', [$start, $end])
      ->sum('total');
  }

  private function calcProfit(int $shopId, Carbon $start, Carbon $end): float
  {
    $sales = $this->sumOrderTotals($shopId, $start, $end);
    $cogs = $this->sumCogs($shopId, $start, $end);

    return $sales - $cogs;
  }

  private function sumCogs(int $shopId, Carbon $start, Carbon $end): float
  {
    return (float) OrderDetails::query()
      ->join('orders', function ($join) use ($shopId, $start, $end) {
        $join->on('orders.id', '=', 'order_details.order_id')
          ->where('orders.shop_id', '=', $shopId)
          ->whereBetween('orders.order_date', [$start, $end])
          ->whereNull('orders.deleted_at');
      })
      ->leftJoin('products', 'products.id', '=', 'order_details.product_id')
      ->selectRaw('COALESCE(SUM(order_details.quantity * COALESCE(NULLIF(order_details.cost_per_unit, 0), products.buying_price, 0)), 0) as cogs')
      ->value('cogs');
  }

  private function countPendingOrders(int $shopId): int
  {
    return (int) Order::query()
      ->where('shop_id', $shopId)
      ->where(function ($q) {
        $q->where('order_status', 'pending')
          ->orWhere('order_status', InterShopTransferStatus::PENDING)
          ->orWhere('order_status', InterShopTransferStatus::APPROVED)
          ->orWhere('order_status', HoldInvoiceService::STATUS_HOLD);
      })
      ->count();
  }

  private function countLowStock(int $shopId): int
  {
    return (int) Product::withoutGlobalScopes()
      ->where('shop_id', $shopId)
      ->whereRaw('COALESCE(product_store, 0) <= COALESCE(low_stock_warning, 10)')
      ->count();
  }

  private function totalReceivables(int $shopId): float
  {
    $rows = AccountTransaction::query()
      ->where('shop_id', $shopId)
      ->where('account_type', AccountTransaction::ACCOUNT_TYPE_CUSTOMER)
      ->selectRaw("account_ref_id, SUM(CASE WHEN direction = 'debit' THEN amount ELSE -amount END) as balance")
      ->groupBy('account_ref_id')
      ->havingRaw("SUM(CASE WHEN direction = 'debit' THEN amount ELSE -amount END) > 0")
      ->get();

    return (float) $rows->sum('balance');
  }

  private function totalPayables(int $shopId): float
  {
    $rows = AccountTransaction::query()
      ->where('shop_id', $shopId)
      ->where('account_type', AccountTransaction::ACCOUNT_TYPE_SUPPLIER)
      ->selectRaw("account_ref_id, SUM(CASE WHEN direction = 'credit' THEN amount ELSE -amount END) as balance")
      ->groupBy('account_ref_id')
      ->havingRaw("SUM(CASE WHEN direction = 'credit' THEN amount ELSE -amount END) > 0")
      ->get();

    return (float) $rows->sum('balance');
  }

  private function cashInHand(int $shopId): float
  {
    $raw = AccountTransaction::query()
      ->where('shop_id', $shopId)
      ->where('account_type', AccountTransaction::ACCOUNT_TYPE_CASH)
      ->selectRaw("SUM(CASE WHEN direction = 'debit' THEN amount ELSE -amount END) as balance")
      ->value('balance');

    return (float) ($raw ?? 0);
  }

  /**
   * @return array{invoices:int,purchases:int,expenses:float,sale_returns:int,payments_received:float}
   */
  private function todayOverview(int $shopId, Carbon $start, Carbon $end): array
  {
    $activityExpenses = (float) Activity::query()
      ->where('shop_id', $shopId)
      ->whereBetween('date', [$start->toDateString(), $end->toDateString()])
      ->sum('activity_cost');

    $shopExpenses = (float) ShopExpense::query()
      ->where('shop_id', $shopId)
      ->whereBetween('expense_date', [$start->toDateString(), $end->toDateString()])
      ->sum('amount');

    $paymentsReceived = (float) AccountTransaction::query()
      ->where('shop_id', $shopId)
      ->where('source_type', AccountTransaction::SOURCE_CUSTOMER_PAYMENT)
      ->where('direction', AccountTransaction::DIRECTION_DEBIT)
      ->whereIn('account_type', [AccountTransaction::ACCOUNT_TYPE_CASH, AccountTransaction::ACCOUNT_TYPE_BANK])
      ->whereBetween('transaction_date', [$start->toDateString(), $end->toDateString()])
      ->sum('amount');

    return [
      'invoices' => (int) Order::query()
        ->where('shop_id', $shopId)
        ->whereBetween('order_date', [$start, $end])
        ->count(),
      'purchases' => (int) Purchase::query()
        ->where('shop_id', $shopId)
        ->whereBetween('purchase_date', [$start, $end])
        ->count(),
      'expenses' => round($activityExpenses + $shopExpenses, 2),
      'sale_returns' => (int) SaleReturn::query()
        ->where('shop_id', $shopId)
        ->whereBetween('return_date', [$start, $end])
        ->count(),
      'payments_received' => round($paymentsReceived, 2),
    ];
  }

  private function salesTrendChart(int $shopId): array
  {
    $end = Carbon::today()->endOfDay();
    $start = Carbon::today()->subDays(29)->startOfDay();

    $rows = Order::query()
      ->where('shop_id', $shopId)
      ->whereBetween('order_date', [$start, $end])
      ->selectRaw('DATE(order_date) as d, COALESCE(SUM(total), 0) as amount')
      ->groupBy('d')
      ->orderBy('d')
      ->pluck('amount', 'd');

    $labels = [];
    $sales = [];
    foreach (CarbonPeriod::create($start, '1 day', $end) as $date) {
      $key = $date->format('Y-m-d');
      $labels[] = $date->format('M d');
      $sales[] = round((float) ($rows[$key] ?? 0), 2);
    }

    return ['labels' => $labels, 'sales' => $sales];
  }

  private function revenueVsExpenseChart(int $shopId): array
  {
    $end = Carbon::now()->endOfMonth();
    $start = Carbon::now()->subMonths(11)->startOfMonth();

    $salesRows = Order::query()
      ->where('shop_id', $shopId)
      ->whereBetween('order_date', [$start, $end])
      ->selectRaw("DATE_FORMAT(order_date, '%Y-%m') as m, COALESCE(SUM(total), 0) as amount")
      ->groupBy('m')
      ->pluck('amount', 'm');

    $expenseRows = Activity::query()
      ->where('shop_id', $shopId)
      ->whereBetween('date', [$start->toDateString(), $end->toDateString()])
      ->selectRaw("DATE_FORMAT(date, '%Y-%m') as m, COALESCE(SUM(activity_cost), 0) as amount")
      ->groupBy('m')
      ->pluck('amount', 'm');

    $shopExpenseRows = ShopExpense::query()
      ->where('shop_id', $shopId)
      ->whereBetween('expense_date', [$start->toDateString(), $end->toDateString()])
      ->selectRaw("DATE_FORMAT(expense_date, '%Y-%m') as m, COALESCE(SUM(amount), 0) as amount")
      ->groupBy('m')
      ->pluck('amount', 'm');

    $labels = [];
    $sales = [];
    $expenses = [];
    $profit = [];

    foreach (CarbonPeriod::create($start, '1 month', $end) as $month) {
      $key = $month->format('Y-m');
      $labels[] = $month->format('M Y');
      $s = round((float) ($salesRows[$key] ?? 0), 2);
      $e = round((float) ($expenseRows[$key] ?? 0) + (float) ($shopExpenseRows[$key] ?? 0), 2);
      $sales[] = $s;
      $expenses[] = $e;
      $profit[] = round($s - $e, 2);
    }

    return compact('labels') + ['sales' => $sales, 'expenses' => $expenses, 'profit' => $profit];
  }

  private function topProductsChart(int $shopId, Carbon $start, Carbon $end): array
  {
    $rows = OrderDetails::query()
      ->join('orders', function ($join) use ($shopId, $start, $end) {
        $join->on('orders.id', '=', 'order_details.order_id')
          ->where('orders.shop_id', '=', $shopId)
          ->whereBetween('orders.order_date', [$start, $end])
          ->whereNull('orders.deleted_at');
      })
      ->leftJoin('products', 'products.id', '=', 'order_details.product_id')
      ->selectRaw("COALESCE(products.product_name, CONCAT('Product #', order_details.product_id)) as name, SUM(order_details.quantity) as qty")
      ->groupBy('order_details.product_id', 'products.product_name')
      ->orderByDesc('qty')
      ->limit(10)
      ->get();

    return [
      'labels' => $rows->pluck('name')->all(),
      'quantities' => $rows->pluck('qty')->map(fn ($q) => (float) $q)->all(),
    ];
  }

  private function paymentMethodsChart(int $shopId, Carbon $start, Carbon $end): array
  {
    $rows = PaymentLog::query()
      ->join('orders', function ($join) use ($shopId, $start, $end) {
        $join->on('orders.id', '=', 'payment_logs.order_id')
          ->where('orders.shop_id', '=', $shopId)
          ->whereBetween('orders.order_date', [$start, $end])
          ->whereNull('orders.deleted_at');
      })
      ->whereNull('payment_logs.deleted_at')
      ->where('payment_logs.amount_paid', '>', 0)
      ->selectRaw('payment_logs.payment_method as method, COALESCE(SUM(payment_logs.amount_paid), 0) as total')
      ->groupBy('payment_logs.payment_method')
      ->get();

    $buckets = [
      'Cash' => 0.0,
      'Card' => 0.0,
      'Bank' => 0.0,
      'Online' => 0.0,
      'Other' => 0.0,
    ];

    foreach ($rows as $row) {
      $method = strtolower((string) $row->method);
      $amount = (float) $row->total;
      $label = match (true) {
        in_array($method, ['cash', 'handcash'], true) => 'Cash',
        in_array($method, ['card', 'debit', 'credit_card'], true) => 'Card',
        in_array($method, ['bank', 'cheque', 'check'], true) => 'Bank',
        in_array($method, ['online', 'upi', 'wallet', 'digital'], true) => 'Online',
        default => 'Other',
      };
      $buckets[$label] += $amount;
    }

    $buckets = array_filter($buckets, fn ($v) => $v > 0);
    if ($buckets === []) {
      return ['labels' => ['No payments'], 'amounts' => [0]];
    }

    return [
      'labels' => array_keys($buckets),
      'amounts' => array_values($buckets),
    ];
  }

  private function lowStockAlerts(int $shopId, int $limit = 8): array
  {
    return Product::withoutGlobalScopes()
      ->where('shop_id', $shopId)
      ->whereRaw('COALESCE(product_store, 0) <= COALESCE(low_stock_warning, 10)')
      ->orderBy('product_store')
      ->limit($limit)
      ->get(['id', 'product_name', 'product_code', 'product_store', 'low_stock_warning'])
      ->map(fn ($p) => [
        'id' => $p->id,
        'name' => $p->product_name,
        'code' => $p->product_code,
        'stock' => (float) $p->product_store,
        'threshold' => (int) ($p->low_stock_warning ?? 10),
        'url' => route('products.edit', $p->id),
      ])
      ->all();
  }

  private function recentSales(int $shopId, int $limit = 10): array
  {
    return Order::query()
      ->where('shop_id', $shopId)
      ->with(['customer:id,name,shopname'])
      ->latest('order_date')
      ->latest('id')
      ->limit($limit)
      ->get(['id', 'invoice_no', 'customer_id', 'total', 'order_status', 'order_date'])
      ->map(fn ($o) => [
        'id' => $o->id,
        'invoice_no' => $o->invoice_no ?: ('#' . $o->id),
        'customer' => $o->customer?->shopname ?: $o->customer?->name ?: 'Walk-in',
        'total' => (float) $o->total,
        'status' => $o->order_status,
        'date' => $o->order_date ? Carbon::parse($o->order_date)->format('M d, Y') : '—',
        'url' => route('order.orderDetails', $o->id),
      ])
      ->all();
  }

  private function pendingReceivables(int $shopId, int $limit = 8): array
  {
    $balances = AccountTransaction::query()
      ->where('shop_id', $shopId)
      ->where('account_type', AccountTransaction::ACCOUNT_TYPE_CUSTOMER)
      ->selectRaw("account_ref_id, SUM(CASE WHEN direction = 'debit' THEN amount ELSE -amount END) as balance")
      ->groupBy('account_ref_id')
      ->havingRaw("SUM(CASE WHEN direction = 'debit' THEN amount ELSE -amount END) > 0")
      ->orderByDesc('balance')
      ->limit($limit)
      ->get();

    if ($balances->isEmpty()) {
      return [];
    }

    $customers = Customer::withoutGlobalScopes()
      ->where('shop_id', $shopId)
      ->whereIn('id', $balances->pluck('account_ref_id'))
      ->get(['id', 'name', 'shopname'])
      ->keyBy('id');

    return $balances->map(function ($row) use ($customers) {
      $customer = $customers->get((int) $row->account_ref_id);

      return [
        'customer_id' => (int) $row->account_ref_id,
        'name' => $customer?->shopname ?: $customer?->name ?: ('Customer #' . $row->account_ref_id),
        'due' => round((float) $row->balance, 2),
        'url' => $customer ? route('customers.ledger', $customer->id) : '#',
      ];
    })->all();
  }

  private function topCustomers(int $shopId, Carbon $start, Carbon $end, int $limit = 5): array
  {
    $rows = Order::query()
      ->where('shop_id', $shopId)
      ->whereNotNull('customer_id')
      ->whereBetween('order_date', [$start, $end])
      ->selectRaw('customer_id, COALESCE(SUM(total), 0) as total_purchases, COUNT(*) as order_count')
      ->groupBy('customer_id')
      ->orderByDesc('total_purchases')
      ->limit($limit)
      ->get();

    if ($rows->isEmpty()) {
      return [];
    }

    $customers = Customer::withoutGlobalScopes()
      ->where('shop_id', $shopId)
      ->whereIn('id', $rows->pluck('customer_id'))
      ->get(['id', 'name', 'shopname'])
      ->keyBy('id');

    return $rows->map(function ($row) use ($customers) {
      $customer = $customers->get((int) $row->customer_id);

      return [
        'name' => $customer?->shopname ?: $customer?->name ?: ('Customer #' . $row->customer_id),
        'total' => round((float) $row->total_purchases, 2),
        'orders' => (int) $row->order_count,
      ];
    })->all();
  }

  private function recentInvoices(int $shopId, int $limit = 10): array
  {
    return $this->recentSales($shopId, $limit);
  }

  private function recentPurchases(int $shopId, int $limit = 10): array
  {
    return Purchase::query()
      ->where('shop_id', $shopId)
      ->with(['supplier:id,name'])
      ->latest('purchase_date')
      ->latest('id')
      ->limit($limit)
      ->get(['id', 'purchase_no', 'supplier_id', 'total', 'purchase_date'])
      ->map(fn ($p) => [
        'ref_no' => $p->purchase_no ?: ('#' . $p->id),
        'supplier' => $p->supplier?->name ?? '—',
        'amount' => (float) $p->total,
        'date' => $p->purchase_date ? Carbon::parse($p->purchase_date)->format('M d, Y') : '—',
        'url' => route('purchases.index'),
      ])
      ->all();
  }

  private function recentExpenses(int $shopId, int $limit = 10): array
  {
    $activities = Activity::query()
      ->where('shop_id', $shopId)
      ->with(['expense:id,expense_title'])
      ->latest('date')
      ->latest('id')
      ->limit($limit)
      ->get(['id', 'expense_id', 'activity_cost', 'date', 'description']);

    return $activities->map(fn ($a) => [
      'category' => $a->expense?->expense_title ?: ($a->description ?: 'Expense'),
      'amount' => (float) $a->activity_cost,
      'date' => $a->date ? Carbon::parse($a->date)->format('M d, Y') : '—',
      'url' => route('expenses.search'),
    ])->all();
  }

  /**
   * Orders count series for selected filter range (Overview chart).
   */
  private function ordersOverviewForRange(int $shopId, Carbon $start, Carbon $end): array
  {
    $days = $start->copy()->startOfDay()->diffInDays($end->copy()->endOfDay()) + 1;
    $isDaily = $days <= 31;
    $groupExpr = $isDaily ? 'DATE(order_date)' : "DATE_FORMAT(order_date, '%Y-%m')";

    $rows = Order::query()
      ->where('shop_id', $shopId)
      ->whereBetween('order_date', [$start->copy()->startOfDay(), $end->copy()->endOfDay()])
      ->selectRaw($groupExpr . ' as d, COUNT(*) as c')
      ->groupBy('d')
      ->orderBy('d')
      ->pluck('c', 'd');

    $labels = [];
    $orders = [];
    $periodStart = $isDaily ? $start->copy()->startOfDay() : $start->copy()->startOfMonth();
    $periodEnd = $isDaily ? $end->copy()->startOfDay() : $end->copy()->startOfMonth();
    $step = $isDaily ? '1 day' : '1 month';

    foreach (CarbonPeriod::create($periodStart, $step, $periodEnd) as $date) {
      $key = $isDaily ? $date->format('Y-m-d') : $date->format('Y-m');
      $labels[] = $isDaily ? $date->format('M d') : $date->format('M Y');
      $orders[] = (int) ($rows[$key] ?? 0);
    }

    return ['labels' => $labels, 'orders' => $orders, 'group_by' => $isDaily ? 'daily' : 'monthly'];
  }

  private function revenueVsCostForRange(int $shopId, Carbon $start, Carbon $end): array
  {
    $days = $start->copy()->startOfDay()->diffInDays($end->copy()->endOfDay()) + 1;
    $isDaily = $days <= 31;
    $groupExpr = $isDaily ? 'DATE(order_date)' : "DATE_FORMAT(order_date, '%Y-%m')";
    $groupExprOrders = $isDaily ? 'DATE(orders.order_date)' : "DATE_FORMAT(orders.order_date, '%Y-%m')";
    $startDt = $start->copy()->startOfDay();
    $endDt = $end->copy()->endOfDay();

    $revenueRows = Order::query()
      ->where('shop_id', $shopId)
      ->whereIn('order_status', ['complete', InterShopTransferStatus::COMPLETED])
      ->whereBetween('order_date', [$startDt, $endDt])
      ->selectRaw($groupExpr . ' as d, COALESCE(SUM(total), 0) as amount')
      ->groupBy('d')
      ->pluck('amount', 'd');

    $costRows = OrderDetails::query()
      ->join('orders', function ($join) use ($shopId, $startDt, $endDt) {
        $join->on('orders.id', '=', 'order_details.order_id')
          ->where('orders.shop_id', '=', $shopId)
          ->whereIn('orders.order_status', ['complete', InterShopTransferStatus::COMPLETED])
          ->whereNull('orders.deleted_at')
          ->whereBetween('orders.order_date', [$startDt, $endDt]);
      })
      ->leftJoin('products', 'products.id', '=', 'order_details.product_id')
      ->selectRaw('
        ' . $groupExprOrders . ' as d,
        COALESCE(SUM(order_details.quantity * COALESCE(NULLIF(order_details.cost_per_unit, 0), products.buying_price, 0)), 0) as amount
      ')
      ->groupBy('d')
      ->pluck('amount', 'd');

    $labels = [];
    $revenue = [];
    $cost = [];
    $profit = [];
    $periodStart = $isDaily ? $startDt->copy() : $startDt->copy()->startOfMonth();
    $periodEnd = $isDaily ? $endDt->copy() : $endDt->copy()->startOfMonth();
    $step = $isDaily ? '1 day' : '1 month';

    foreach (CarbonPeriod::create($periodStart, $step, $periodEnd) as $date) {
      $key = $isDaily ? $date->format('Y-m-d') : $date->format('Y-m');
      $r = round((float) ($revenueRows[$key] ?? 0), 2);
      $c = round((float) ($costRows[$key] ?? 0), 2);
      $labels[] = $isDaily ? $date->format('M d') : $date->format('M Y');
      $revenue[] = $r;
      $cost[] = $c;
      $profit[] = round($r - $c, 2);
    }

    return [
      'labels' => $labels,
      'revenue' => $revenue,
      'cost' => $cost,
      'profit' => $profit,
      'group_by' => $isDaily ? 'daily' : 'monthly',
    ];
  }

  /** @return array{start:\Carbon\Carbon,end:\Carbon\Carbon} */
  private function previousPeriod(Carbon $start, Carbon $end): array
  {
    $days = $start->diffInDays($end) + 1;
    $prevEnd = $start->copy()->subDay()->endOfDay();
    $prevStart = $prevEnd->copy()->subDays($days - 1)->startOfDay();

    return ['start' => $prevStart, 'end' => $prevEnd];
  }

  private function trendPct(float $current, float $previous): ?float
  {
    if ($previous == 0) {
      return $current != 0 ? 100.0 : null;
    }

    return round((($current - $previous) / abs($previous)) * 100, 1);
  }
}
