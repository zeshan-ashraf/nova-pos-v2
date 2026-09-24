@php
    $d = $dashboard ?? [];
    $kpis = $d['kpis'] ?? [];
    $today = $d['today_overview'] ?? [];
    $sidebar = $d['sidebar'] ?? [];
    $tables = $d['tables'] ?? [];
    $currency = $d['currency'] ?? '';
    $kpiDefs = [
        ['key' => 'today_sales', 'title' => "Today's Sales", 'icon' => 'ri-shopping-cart-2-line', 'color' => 'primary', 'bg' => 'bg-primary-light'],
        ['key' => 'today_profit', 'title' => "Today's Profit", 'icon' => 'ri-line-chart-line', 'color' => 'success', 'bg' => 'bg-success-light'],
        ['key' => 'monthly_sales', 'title' => 'Monthly Sales', 'icon' => 'ri-calendar-line', 'color' => 'info', 'bg' => 'bg-info-light'],
        ['key' => 'pending_orders', 'title' => 'Pending Orders', 'icon' => 'ri-time-line', 'color' => 'warning', 'bg' => 'bg-warning-light', 'fmt' => 'int'],
        ['key' => 'low_stock_products', 'title' => 'Low Stock', 'icon' => 'ri-alert-line', 'color' => 'danger', 'bg' => 'bg-danger-light', 'fmt' => 'int'],
        ['key' => 'total_receivables', 'title' => 'Receivables', 'icon' => 'ri-user-received-line', 'color' => 'secondary', 'bg' => 'bg-secondary-light'],
        ['key' => 'total_payables', 'title' => 'Payables', 'icon' => 'ri-bank-card-line', 'color' => 'dark', 'bg' => 'bg-dark-light'],
        ['key' => 'cash_in_hand', 'title' => 'Cash in Hand', 'icon' => 'ri-wallet-3-line', 'color' => 'success', 'bg' => 'bg-success-light'],
    ];
    $todayDefs = [
        ['key' => 'invoices', 'label' => 'Invoices', 'icon' => 'ri-bill-line', 'statClass' => 'erp-today-stat-invoices'],
        ['key' => 'purchases', 'label' => 'Purchases', 'icon' => 'ri-shopping-bag-line', 'statClass' => 'erp-today-stat-purchases'],
        ['key' => 'expenses', 'label' => 'Expenses', 'icon' => 'ri-money-dollar-box-line', 'money' => true, 'statClass' => 'erp-today-stat-expenses'],
        ['key' => 'sale_returns', 'label' => 'Returns', 'icon' => 'ri-arrow-go-back-line', 'statClass' => 'erp-today-stat-returns'],
        ['key' => 'payments_received', 'label' => 'Payments In', 'icon' => 'ri-hand-coin-line', 'money' => true, 'statClass' => 'erp-today-stat-payments'],
    ];
@endphp

<div class="erp-dashboard" id="erpDashboard" data-data-url="{{ route('dashboard.data') }}">
<div class="container-fluid px-1 px-lg-2">

@if (session()->has('success'))
<div class="alert alert-success alert-dismissible fade show py-2" role="alert">
    {{ session('success') }}
    <button type="button" class="close" data-dismiss="alert"><span>&times;</span></button>
</div>
@endif

<div class="row align-items-center mb-3">
    <div class="col-md-5 mb-2 mb-md-0">
        <h4 class="mb-0 font-weight-bold">Dashboard</h4>
        <p class="text-muted mb-0 small">Welcome back, {{ auth()->user()->name }}</p>
    </div>
    <div class="col-md-7">
        <form method="GET" action="{{ route('dashboard') }}" id="dashboard-filter-form" class="card erp-filter-card p-2 mb-0">
            <div class="d-flex flex-wrap align-items-center">
                <div class="btn-group erp-filter-pills flex-wrap mr-2" role="group">
                    @foreach(['today' => 'Today', 'this_week' => 'Weekly', 'this_month' => 'Monthly'] as $val => $lbl)
                    <button type="submit" name="date_filter" value="{{ $val }}"
                        class="btn btn-sm {{ ($dateRange['date_filter'] ?? 'today') === $val ? 'btn-primary' : 'btn-outline-secondary' }}">{{ $lbl }}</button>
                    @endforeach
                    <button type="button" class="btn btn-sm {{ ($dateRange['date_filter'] ?? '') === 'custom' ? 'btn-primary' : 'btn-outline-secondary' }}" id="btnCustomRange">Custom</button>
                </div>
                <div id="customDateFields" class="d-flex flex-wrap align-items-center ml-auto" style="{{ ($dateRange['date_filter'] ?? '') === 'custom' ? '' : 'display:none!important' }}">
                    <input type="hidden" name="date_filter" value="custom">
                    <input type="date" name="start_date" class="form-control form-control-sm mr-1" style="width:130px" value="{{ $dateRange['start_date'] ?? '' }}">
                    <input type="date" name="end_date" class="form-control form-control-sm mr-1" style="width:130px" value="{{ $dateRange['end_date'] ?? '' }}">
                    <button type="submit" class="btn btn-sm btn-primary"><i class="ri-search-line"></i></button>
                </div>
                <a href="{{ route('dashboard') }}" class="btn btn-sm btn-light">Reset</a>
            </div>
        </form>
    </div>
</div>

<div class="row" id="kpiRow">
@foreach($kpiDefs as $def)
@php $k = $kpis[$def['key']] ?? ['value' => 0, 'trend_pct' => null, 'trend_label' => '']; @endphp
<div class="col-6 col-md-4 col-xl-3 mb-3">
    <div class="card erp-kpi-card border-{{ $def['color'] }} h-100">
        <div class="card-body py-3 px-3 d-flex justify-content-between align-items-start">
            <div class="pr-2">
                <p class="text-muted mb-1 small">{{ $def['title'] }}</p>
                <div class="kpi-value erp-kpi-value text-{{ $def['color'] }}" data-count-to="{{ ($def['fmt'] ?? '') === 'int' ? (int)$k['value'] : $k['value'] }}" data-fmt="{{ $def['fmt'] ?? 'money' }}">
                    @if(($def['fmt'] ?? '') === 'int'){{ number_format((int)$k['value']) }}@else{{ $currency }}{{ number_format((float)$k['value'], 2) }}@endif
                </div>
                <div class="kpi-trend mt-1">
                    @if(isset($k['trend_pct']) && $k['trend_pct'] !== null)
                    <span class="{{ $k['trend_pct'] >= 0 ? 'text-success' : 'text-danger' }}">
                        <i class="ri-arrow-{{ $k['trend_pct'] >= 0 ? 'up' : 'down' }}-s-line"></i>{{ abs($k['trend_pct']) }}%
                    </span>
                    @endif
                    <span class="text-muted">{{ $k['trend_label'] ?? '' }}</span>
                </div>
            </div>
            <div class="kpi-icon {{ $def['bg'] }} text-{{ $def['color'] }}"><i class="{{ $def['icon'] }}"></i></div>
        </div>
    </div>
</div>
@endforeach
</div>

@include('dashboard.partials.erp-recent-tables', ['tables' => $tables, 'currency' => $currency])

<div class="row mb-3">
    <div class="col-12">
        <div class="card erp-today-overview-card"><div class="card-body p-0"><div class="row no-gutters" id="todayOverview">
        @foreach($todayDefs as $t)
        <div class="col erp-today-stat {{ $t['statClass'] ?? '' }}">
            <div class="val" data-today-key="{{ $t['key'] }}">
                @if(!empty($t['money'])){{ $currency }}{{ number_format((float)($today[$t['key']] ?? 0), 2) }}@else{{ number_format((int)($today[$t['key']] ?? 0)) }}@endif
            </div>
            <div class="lbl"><i class="{{ $t['icon'] }} mr-1"></i>{{ $t['label'] }}</div>
        </div>
        @endforeach
        </div></div></div>
    </div>
</div>

<div class="row">
<div class="col-xl-8">
    <div class="row">
        <div class="col-12 mb-3"><div class="card"><div class="card-header py-2 erp-widget-header-sales-trend"><h6 class="mb-0 font-weight-bold">Sales Trend <small>(30 days)</small></h6></div><div class="card-body pt-1"><div id="chartSalesTrend" class="chart-wrap"></div></div></div></div>
        <div class="col-lg-7 mb-3"><div class="card h-100"><div class="card-header py-2 erp-widget-header-revenue-expense"><h6 class="mb-0 font-weight-bold">Revenue vs Expense</h6></div><div class="card-body pt-1"><div id="chartRevenueExpense" class="chart-wrap"></div></div></div></div>
        <div class="col-lg-5 mb-3"><div class="card h-100"><div class="card-header py-2 erp-widget-header-payment-methods"><h6 class="mb-0 font-weight-bold">Payment Methods</h6></div><div class="card-body pt-1"><div id="chartPaymentMethods" class="chart-wrap" style="min-height:280px"></div></div></div></div>
        <div class="col-12 mb-3"><div class="card"><div class="card-header py-2 d-flex justify-content-between erp-widget-header-revenue-cost"><h6 class="mb-0 font-weight-bold">Revenue vs Cost</h6><small>{{ ucfirst($revenue_vs_cost['group_by'] ?? 'daily') }} · filtered</small></div><div class="card-body pt-1"><div id="chartRevenueCost" class="chart-wrap"></div></div></div></div>
        <div class="col-12 mb-3"><div class="card"><div class="card-header py-2 erp-widget-header-top-products"><h6 class="mb-0 font-weight-bold">Top Selling Products</h6></div><div class="card-body pt-1"><div id="chartTopProducts" class="chart-wrap" style="min-height:320px"></div></div></div></div>
    </div>
</div>
<div class="col-xl-4">
    <div class="card mb-3"><div class="card-header py-2 d-flex justify-content-between erp-widget-header-low-stock"><h6 class="mb-0 font-weight-bold"><i class="ri-alert-line mr-1"></i>Low Stock</h6><a href="{{ route('order.stockManage') }}" class="small">Inventory</a></div>
    <div class="card-body py-2 erp-widget-list">@forelse($sidebar['low_stock'] ?? [] as $item)<div class="d-flex justify-content-between py-1 border-bottom"><div><div class="small font-weight-semibold">{{ Str::limit($item['name'], 28) }}</div><div class="text-muted" style="font-size:0.7rem">{{ $item['code'] }}</div></div><span class="badge badge-danger">{{ $item['stock'] }}/{{ $item['threshold'] }} {{ ($item['unit'] ?? 'piece') === 'kg' ? 'kg' : 'pieces' }}</span></div>@empty<div class="erp-empty-state"><i class="ri-checkbox-circle-line"></i>Stock OK</div>@endforelse</div></div>
    <div class="card mb-3"><div class="card-header py-2 erp-widget-header-recent-sales"><h6 class="mb-0 font-weight-bold">Recent Sales</h6></div><div class="card-body py-2 erp-widget-list">@forelse($sidebar['recent_sales'] ?? [] as $sale)<a href="{{ $sale['url'] }}" class="d-flex justify-content-between py-1 border-bottom text-body"><div><div class="small font-weight-bold">{{ $sale['invoice_no'] }}</div><div class="text-muted" style="font-size:0.7rem">{{ $sale['customer'] }}</div></div><div class="text-right"><div class="small font-weight-bold">{{ $currency }}{{ number_format($sale['total'], 2) }}</div><div class="text-muted" style="font-size:0.7rem">{{ $sale['date'] }}</div></div></a>@empty<div class="erp-empty-state"><i class="ri-inbox-line"></i>No sales</div>@endforelse</div></div>
    <div class="card mb-3"><div class="card-header py-2"><h6 class="mb-0 font-weight-bold">Pending Receivables</h6></div><div class="card-body py-2 erp-widget-list">@forelse($sidebar['pending_receivables'] ?? [] as $row)<a href="{{ $row['url'] }}" class="d-flex justify-content-between py-1 border-bottom text-body"><span class="small">{{ Str::limit($row['name'], 24) }}</span><span class="small font-weight-bold text-danger">{{ $currency }}{{ number_format($row['due'], 2) }}</span></a>@empty<div class="erp-empty-state"><i class="ri-check-double-line"></i>No dues</div>@endforelse</div></div>
    <div class="card mb-3"><div class="card-header py-2 erp-widget-header-top-customers"><h6 class="mb-0 font-weight-bold">Top Customers</h6></div><div class="card-body py-2 erp-widget-list">@forelse($sidebar['top_customers'] ?? [] as $c)<div class="d-flex justify-content-between py-1 border-bottom"><span class="small">{{ Str::limit($c['name'], 26) }}</span><span class="small font-weight-bold">{{ $currency }}{{ number_format($c['total'], 2) }}</span></div>@empty<div class="erp-empty-state"><i class="ri-user-line"></i>No data</div>@endforelse</div></div>
</div>
</div>

</div>
</div>
<script type="application/json" id="dashboardInitialData">@json($d)</script>
