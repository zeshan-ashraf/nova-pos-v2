@extends('dashboard.body.main')

@section('specificpagestyles')
<style>
    .sa-dashboard-section + .sa-dashboard-section {
        margin-top: 1.5rem;
    }
    .sa-dashboard-card-title {
        font-size: 0.9rem;
        text-transform: uppercase;
        letter-spacing: 0.04em;
        color: #6c757d;
        margin-bottom: 0.25rem;
    }
    .sa-dashboard-metric-value {
        font-size: 1.4rem;
        font-weight: 600;
    }
    .sa-dashboard-subtext {
        font-size: 0.8rem;
        color: #6c757d;
    }
    .sa-dashboard-section .card.shadow-sm,
    .container-fluid .sa-dashboard-section .card {
        box-shadow: 0 10px 30px rgba(0,0,0,0.15) !important;
    }
    .hover-shadow:hover {
        transform: translateY(-3px);
        box-shadow: 0 14px 40px rgba(0,0,0,0.18) !important;
        transition: all 0.3s ease;
    }
    .sa-dashboard-section .card.hover-shadow .sa-dashboard-card-title {
        font-size: 0.95rem;
        margin-bottom: 5px;
    }
    .sa-dashboard-section .card.hover-shadow .sa-dashboard-metric-value {
        margin-bottom: 5px;
    }
    .kpi-trend { font-size: 0.85rem; margin-left: 4px; }
    .kpi-trend.up { color: #28a745; }
    .kpi-trend.down { color: #dc3545; }
    #consolidated-kpis .card.kpi-up { animation: kpiPulseUp 0.5s ease; }
    #consolidated-kpis .card.kpi-down { animation: kpiPulseDown 0.5s ease; }
    @keyframes kpiPulseUp {
        0% { box-shadow: 0 10px 30px rgba(0,0,0,0.15); }
        50% { box-shadow: 0 10px 30px rgba(40,167,69,0.35); }
        100% { box-shadow: 0 10px 30px rgba(0,0,0,0.15); }
    }
    @keyframes kpiPulseDown {
        0% { box-shadow: 0 10px 30px rgba(0,0,0,0.15); }
        50% { box-shadow: 0 10px 30px rgba(220,53,69,0.25); }
        100% { box-shadow: 0 10px 30px rgba(0,0,0,0.15); }
    }
</style>
@endsection

@section('container')
<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="d-flex flex-wrap align-items-center justify-content-between mb-3">
                <div>
                    <h4 class="mb-1">Super Admin Comparison Dashboard</h4>
                    <p class="text-muted mb-0">Executive overview across all child shops (view-only)</p>
                </div>
            </div>
        </div>
    </div>

    {{-- 1️⃣ Global Filter Section (Top Bar) --}}
    <div class="row sa-dashboard-section">
        <div class="col-12">
            <div class="card border">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h6 class="mb-0 text-uppercase">Global Filters</h6>
                </div>
                <div class="card-body">
                    <form id="sa_filter_form" class="form-row align-items-end">
                        <div class="form-group col-md-3">
                            <label for="sa_date_range" class="mb-1">Date Filter</label>
                            <select id="sa_date_range" class="form-control" name="date_filter" onchange="toggleSaCustomDates()">
                                <option value="today" selected>Today</option>
                                <option value="yesterday">Yesterday</option>
                                <option value="this_week">This Week</option>
                                <option value="last_week">Last Week</option>
                                <option value="this_month">This Month</option>
                                <option value="last_month">Last Month</option>
                                <option value="this_year">This Year</option>
                                <option value="last_year">Last Year</option>
                                <option value="custom">Custom Range</option>
                            </select>
                        </div>
                        <div class="form-group col-md-3" id="sa_start_date_group" style="display:none;">
                            <label for="sa_start_date" class="mb-1">Start Date</label>
                            <input type="date" id="sa_start_date" class="form-control" name="start_date" placeholder="YYYY-MM-DD">
                        </div>
                        <div class="form-group col-md-3" id="sa_end_date_group" style="display:none;">
                            <label for="sa_end_date" class="mb-1">End Date</label>
                            <input type="date" id="sa_end_date" class="form-control" name="end_date" placeholder="YYYY-MM-DD">
                        </div>
                        <div class="form-group col-md-3">
                            <label for="sa_shops" class="mb-1">Shop</label>
                            <select id="sa_shops" class="form-control" name="shop_id">
                                <option value="all">All Stores</option>
                                @foreach($shops ?? [] as $shop)
                                    <option value="{{ $shop->id }}">{{ $shop->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="form-group col-md-12 mt-2">
                            <button type="button" id="sa_apply_filters_btn" class="btn btn-primary">
                                Apply Filters
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    {{-- 2️⃣ Consolidated KPI Cards (All Shops Combined) --}}
    <div id="consolidated-kpis" class="row sa-dashboard-section">
        <div class="col-12">
            <h6 class="text-uppercase mb-3 font-weight-bold">Consolidated KPIs (All Selected Shops)</h6>
        </div>

        <!-- Total Sales -->
        <div class="col-12 col-sm-6 col-lg mb-3">
            <div class="card h-100 shadow-sm border-0 hover-shadow" id="card-totalSales">
                <div class="card-body text-center position-relative">
                    <div id="kpi-spinner-totalSales" class="position-absolute top-50 start-50 translate-middle">
                        <span class="spinner-border spinner-border-sm text-success" role="status"></span>
                    </div>
                    <div class="d-flex justify-content-center mb-2">
                        <i class="fas fa-dollar-sign fa-2x text-success"></i>
                    </div>
                    <div class="sa-dashboard-card-title font-weight-bold">Total Sales</div>
                    <div class="d-flex justify-content-center align-items-center flex-wrap">
                        <span id="totalSales" class="sa-dashboard-metric-value h3 font-weight-bold text-success">…</span>
                        <span id="totalSalesTrend" class="kpi-trend" style="display:none;"></span>
                    </div>
                    <div class="sa-dashboard-subtext text-muted">All shops, selected period</div>
                </div>
            </div>
        </div>

        <!-- Total COGS -->
        <div class="col-12 col-sm-6 col-lg mb-3">
            <div class="card h-100 shadow-sm border-0 hover-shadow" id="card-totalCOGS">
                <div class="card-body text-center position-relative">
                    <div id="kpi-spinner-totalCOGS" class="position-absolute top-50 start-50 translate-middle">
                        <span class="spinner-border spinner-border-sm text-warning" role="status"></span>
                    </div>
                    <div class="d-flex justify-content-center mb-2">
                        <i class="fas fa-boxes fa-2x text-warning"></i>
                    </div>
                    <div class="sa-dashboard-card-title font-weight-bold">Total COGS</div>
                    <div class="d-flex justify-content-center align-items-center flex-wrap">
                        <span id="totalCOGS" class="sa-dashboard-metric-value h3 font-weight-bold text-warning">…</span>
                        <span id="totalCOGSTrend" class="kpi-trend" style="display:none;"></span>
                    </div>
                    <div class="sa-dashboard-subtext text-muted">Cost of goods sold</div>
                </div>
            </div>
        </div>

        <!-- Total Purchases -->
        <div class="col-12 col-sm-6 col-lg mb-3">
            <div class="card h-100 shadow-sm border-0 hover-shadow" id="card-totalPurchases">
                <div class="card-body text-center position-relative">
                    <div id="kpi-spinner-totalPurchases" class="position-absolute top-50 start-50 translate-middle">
                        <span class="spinner-border spinner-border-sm text-secondary" role="status"></span>
                    </div>
                    <div class="d-flex justify-content-center mb-2">
                        <i class="fas fa-shopping-cart fa-2x text-secondary"></i>
                    </div>
                    <div class="sa-dashboard-card-title font-weight-bold">Total Purchases</div>
                    <div class="d-flex justify-content-center align-items-center flex-wrap">
                        <span id="totalPurchases" class="sa-dashboard-metric-value h3 font-weight-bold text-secondary">…</span>
                        <span id="totalPurchasesTrend" class="kpi-trend" style="display:none;"></span>
                    </div>
                    <div class="sa-dashboard-subtext text-muted">Selected period</div>
                </div>
            </div>
        </div>

        <!-- Total Discounts -->
        <div class="col-12 col-sm-6 col-lg mb-3">
            <div class="card h-100 shadow-sm border-0 hover-shadow" id="card-totalDiscounts">
                <div class="card-body text-center position-relative">
                    <div id="kpi-spinner-totalDiscounts" class="position-absolute top-50 start-50 translate-middle">
                        <span class="spinner-border spinner-border-sm text-info" role="status"></span>
                    </div>
                    <div class="d-flex justify-content-center mb-2">
                        <i class="fas fa-tag fa-2x text-info"></i>
                    </div>
                    <div class="sa-dashboard-card-title font-weight-bold">Total Discounts</div>
                    <div class="d-flex justify-content-center align-items-center flex-wrap">
                        <span id="totalDiscounts" class="sa-dashboard-metric-value h3 font-weight-bold text-info">…</span>
                        <span id="totalDiscountsTrend" class="kpi-trend" style="display:none;"></span>
                    </div>
                    <div class="sa-dashboard-subtext text-muted">Invoice + line discounts</div>
                </div>
            </div>
        </div>

        <!-- Total Expense -->
        <div class="col-12 col-sm-6 col-lg mb-3">
            <div class="card h-100 shadow-sm border-0 hover-shadow" id="card-totalExpense">
                <div class="card-body text-center position-relative">
                    <div id="kpi-spinner-totalExpense" class="position-absolute top-50 start-50 translate-middle">
                        <span class="spinner-border spinner-border-sm text-danger" role="status"></span>
                    </div>
                    <div class="d-flex justify-content-center mb-2">
                        <i class="fas fa-money-bill-wave fa-2x text-danger"></i>
                    </div>
                    <div class="sa-dashboard-card-title font-weight-bold">Total Expense</div>
                    <div class="d-flex justify-content-center align-items-center flex-wrap">
                        <span id="totalExpense" class="sa-dashboard-metric-value h3 font-weight-bold text-danger">…</span>
                        <span id="totalExpenseTrend" class="kpi-trend" style="display:none;"></span>
                    </div>
                    <div class="sa-dashboard-subtext text-muted">Selected period</div>
                </div>
            </div>
        </div>

        {{-- Second row: Gross Profit, Net Profit, Profit Margin --}}
        <div class="w-100"></div>
        <!-- Gross Profit -->
        <div class="col-12 col-md-4 mb-3">
            <div class="card h-100 shadow-sm border-0 hover-shadow" id="card-grossProfit">
                <div class="card-body text-center position-relative">
                    <div id="kpi-spinner-grossProfit" class="position-absolute top-50 start-50 translate-middle">
                        <span class="spinner-border spinner-border-sm text-primary" role="status"></span>
                    </div>
                    <div class="d-flex justify-content-center mb-2">
                        <i class="fas fa-chart-line fa-2x text-primary"></i>
                    </div>
                    <div class="sa-dashboard-card-title font-weight-bold">Gross Profit</div>
                    <div class="d-flex justify-content-center align-items-center flex-wrap">
                        <span id="grossProfit" class="sa-dashboard-metric-value h3 font-weight-bold text-primary">…</span>
                        <span id="grossProfitTrend" class="kpi-trend" style="display:none;"></span>
                    </div>
                    <div class="sa-dashboard-subtext text-muted">Sales − COGS</div>
                </div>
            </div>
        </div>

        <!-- Net Profit -->
        <div class="col-12 col-md-4 mb-3">
            <div class="card h-100 shadow-sm border-0 hover-shadow" id="card-netProfit">
                <div class="card-body text-center position-relative">
                    <div id="kpi-spinner-netProfit" class="position-absolute top-50 start-50 translate-middle">
                        <span class="spinner-border spinner-border-sm text-success" role="status"></span>
                    </div>
                    <div class="d-flex justify-content-center mb-2">
                        <i class="fas fa-coins fa-2x text-success"></i>
                    </div>
                    <div class="sa-dashboard-card-title font-weight-bold">Net Profit</div>
                    <div class="d-flex justify-content-center align-items-center flex-wrap">
                        <span id="netProfit" class="sa-dashboard-metric-value h3 font-weight-bold text-success">…</span>
                        <span id="netProfitTrend" class="kpi-trend" style="display:none;"></span>
                    </div>
                    <div class="sa-dashboard-subtext text-muted">After discounts</div>
                </div>
            </div>
        </div>

        <!-- Profit Margin % -->
        <div class="col-12 col-md-4 mb-3">
            <div class="card h-100 shadow-sm border-0 hover-shadow" id="card-profitMargin">
                <div class="card-body text-center position-relative">
                    <div id="kpi-spinner-profitMargin" class="position-absolute top-50 start-50 translate-middle">
                        <span class="spinner-border spinner-border-sm text-primary" role="status"></span>
                    </div>
                    <div class="d-flex justify-content-center mb-2">
                        <i class="fas fa-percentage fa-2x text-primary"></i>
                    </div>
                    <div class="sa-dashboard-card-title font-weight-bold">Profit Margin %</div>
                    <div class="d-flex justify-content-center align-items-center flex-wrap">
                        <span id="profitMargin" class="sa-dashboard-metric-value h3 font-weight-bold text-primary">…</span>
                        <span id="profitMarginTrend" class="kpi-trend" style="display:none;"></span>
                    </div>
                    <div class="sa-dashboard-subtext text-muted">Net profit ÷ sales</div>
                </div>
            </div>
        </div>
    </div>
    <div id="kpi-error-alert" class="alert alert-danger mt-2" style="display: none;" role="alert"></div>

    {{-- 3️⃣ Child Shop Comparison Table --}}
    <div class="row sa-dashboard-section">
        <div class="col-12">
            <div class="card border">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h6 class="mb-0 text-uppercase">Child Shop Performance Comparison</h6>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table mb-0 table-striped table-hover">
                            <thead class="thead-light">
                                <tr>
                                    <th>Shop Name</th>
                                    <th>Sales</th>
                                    <th>Total Orders</th>
                                    <th>COGS</th>
                                    <th>Gross Profit</th>
                                    <th>Invoice Discount</th>
                                    <th>Net Profit</th>
                                    <th>Margin %</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($shops ?? [] as $shop)
                                    <tr>
                                        <td>{{ $shop->name ?? 'Shop Name' }}</td>
                                        <td>{{ number_format(0, 2) }}</td>
                                        <td>{{ number_format(0, 0) }}</td>
                                        <td>{{ number_format(0, 2) }}</td>
                                        <td>{{ number_format(0, 2) }}</td>
                                        <td>{{ number_format(0, 2) }}</td>
                                        <td>{{ number_format(0, 2) }}%</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="8" class="text-center text-muted py-4">
                                            Comparison data will appear here once implemented.
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- 4️⃣ Comparison Charts Section --}}
    <div class="row sa-dashboard-section">
        <div class="col-12">
            <h6 class="text-uppercase mb-2">Visual Comparisons</h6>
        </div>
        <div class="col-lg-6 mb-3">
            <div class="card border h-100">
                <div class="card-header">
                    <h6 class="mb-0 text-uppercase">Sales Comparison</h6>
                </div>
                <div class="card-body">
                    <canvas id="salesComparisonChart" height="160"></canvas>
                </div>
            </div>
        </div>
        <div class="col-lg-6 mb-3">
            <div class="card border h-100">
                <div class="card-header">
                    <h6 class="mb-0 text-uppercase">Net Profit Comparison</h6>
                </div>
                <div class="card-body">
                    <canvas id="profitComparisonChart" height="160"></canvas>
                </div>
            </div>
        </div>
    </div>

    {{-- 5️⃣ Top / Bottom Insight Cards --}}
    <div class="row sa-dashboard-section">
        <div class="col-12">
            <h6 class="text-uppercase mb-2">Insights</h6>
        </div>
        <div class="col-md-3 mb-3">
            <div class="card border h-100">
                <div class="card-body">
                    <div class="sa-dashboard-card-title">Top Selling Shop</div>
                    <div class="sa-dashboard-metric-value">—</div>
                    <div class="sa-dashboard-subtext">Highest total sales</div>
                </div>
            </div>
        </div>
        <div class="col-md-3 mb-3">
            <div class="card border h-100">
                <div class="card-body">
                    <div class="sa-dashboard-card-title">Highest Profit Shop</div>
                    <div class="sa-dashboard-metric-value">—</div>
                    <div class="sa-dashboard-subtext">Highest net profit</div>
                </div>
            </div>
        </div>
        <div class="col-md-3 mb-3">
            <div class="card border h-100">
                <div class="card-body">
                    <div class="sa-dashboard-card-title">Best Margin Shop</div>
                    <div class="sa-dashboard-metric-value">—</div>
                    <div class="sa-dashboard-subtext">Best profit margin %</div>
                </div>
            </div>
        </div>
        <div class="col-md-3 mb-3">
            <div class="card border h-100">
                <div class="card-body">
                    <div class="sa-dashboard-card-title">Lowest Performing Shop</div>
                    <div class="sa-dashboard-metric-value">—</div>
                    <div class="sa-dashboard-subtext">Based on net profit</div>
                </div>
            </div>
        </div>
    </div>

    {{-- 6️⃣ Purchase Comparison Table --}}
    <div class="row sa-dashboard-section">
        <div class="col-12">
            <div class="card border">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h6 class="mb-0 text-uppercase">Purchase Comparison</h6>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table mb-0 table-striped table-hover">
                            <thead class="thead-light">
                                <tr>
                                    <th>Shop Name</th>
                                    <th>Total Purchases</th>
                                    <th>Purchase to Sales Ratio</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($shops ?? [] as $shop)
                                    <tr>
                                        <td>{{ $shop->name ?? 'Shop Name' }}</td>
                                        <td>{{ number_format(0, 2) }}</td>
                                        <td>{{ number_format(0, 2) }}%</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="3" class="text-center text-muted py-4">
                                            Purchase comparison will appear here once implemented.
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- 7️⃣ Inventory Snapshot Section --}}
    <div class="row sa-dashboard-section mb-4">
        <div class="col-12">
            <div class="card border">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h6 class="mb-0 text-uppercase">Inventory Snapshot</h6>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table mb-0 table-striped table-hover">
                            <thead class="thead-light">
                                <tr>
                                    <th>Shop Name</th>
                                    <th>Stock Value</th>
                                    <th>Low Stock Count</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($shops ?? [] as $shop)
                                    <tr>
                                        <td>{{ $shop->name ?? 'Shop Name' }}</td>
                                        <td>{{ number_format(0, 2) }}</td>
                                        <td>{{ number_format(0, 0) }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="3" class="text-center text-muted py-4">
                                            Inventory snapshot will appear here once implemented.
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@section('specificpagescripts')
<script>
(function() {
    'use strict';

    var KPI_MAP = [
        { key: 'total_sales', id: 'totalSales', cardId: 'card-totalSales', trendId: 'totalSalesTrend', suffix: '' },
        { key: 'total_cogs', id: 'totalCOGS', cardId: 'card-totalCOGS', trendId: 'totalCOGSTrend', suffix: '' },
        { key: 'total_purchases', id: 'totalPurchases', cardId: 'card-totalPurchases', trendId: 'totalPurchasesTrend', suffix: '' },
        { key: 'gross_profit', id: 'grossProfit', cardId: 'card-grossProfit', trendId: 'grossProfitTrend', suffix: '' },
        { key: 'total_discounts', id: 'totalDiscounts', cardId: 'card-totalDiscounts', trendId: 'totalDiscountsTrend', suffix: '' },
        { key: 'total_expense', id: 'totalExpense', cardId: 'card-totalExpense', trendId: 'totalExpenseTrend', suffix: '' },
        { key: 'net_profit', id: 'netProfit', cardId: 'card-netProfit', trendId: 'netProfitTrend', suffix: '' },
        { key: 'profit_margin', id: 'profitMargin', cardId: 'card-profitMargin', trendId: 'profitMarginTrend', suffix: '%' }
    ];

    function showKpiSpinners(clearValues) {
        KPI_MAP.forEach(function(m) {
            var spinner = document.getElementById('kpi-spinner-' + m.id);
            var valueEl = document.getElementById(m.id);
            if (spinner) spinner.style.display = '';
            if (clearValues && valueEl) valueEl.textContent = '…';
        });
        var err = document.getElementById('kpi-error-alert');
        if (err) err.style.display = 'none';
    }

    function hideKpiSpinners() {
        KPI_MAP.forEach(function(m) {
            var spinner = document.getElementById('kpi-spinner-' + m.id);
            if (spinner) spinner.style.display = 'none';
        });
    }

    function animateValue(el, from, to, suffix, duration) {
        if (!el) return;
        var isPct = suffix === '%';
        var start = typeof from === 'number' ? from : parseFloat(String(from).replace(/[^0-9.-]/g, '')) || 0;
        var end = typeof to === 'number' ? to : parseFloat(String(to).replace(/[^0-9.-]/g, '')) || 0;
        var startTime = null;
        function step(timestamp) {
            if (!startTime) startTime = timestamp;
            var progress = Math.min((timestamp - startTime) / duration, 1);
            var ease = progress < 0.5 ? 2 * progress * progress : 1 - Math.pow(-2 * progress + 2, 2) / 2;
            var current = start + (end - start) * ease;
            var display = isPct ? current.toFixed(2) + '%' : current.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
            el.textContent = display;
            if (progress < 1) requestAnimationFrame(step);
        }
        requestAnimationFrame(step);
    }

    function getCurrentKpiValue(valueEl, isPct) {
        if (!valueEl) return NaN;
        var text = (valueEl.textContent || '').trim().replace(/,/g, '');
        if (text === '' || text === '…') return NaN;
        var n = parseFloat(String(text).replace(/[^0-9.-]/g, ''));
        return isNaN(n) ? NaN : n;
    }

    function valuesEqual(newVal, current, isPct) {
        if (isNaN(current)) return false;
        var a = Number(newVal);
        var b = Number(current);
        var eps = isPct ? 0.01 : 0.009;
        return Math.abs(a - b) < eps;
    }

    function updateTrend(trendSelector, newValue, oldValue, trendPct, cardId) {
        var el = typeof trendSelector === 'string' ? document.getElementById(trendSelector) : trendSelector;
        var card = cardId ? document.getElementById(cardId) : null;
        if (!el) return;
        el.textContent = '';
        el.className = 'kpi-trend';
        var newNum = Number(newValue);
        var oldNum = Number(oldValue);
        if (isNaN(oldNum) || newNum === oldNum) {
            el.style.display = 'none';
            return;
        }
        var pct = (trendPct != null && !isNaN(trendPct)) ? trendPct : (Math.abs(oldNum) < 1e-9 ? (newNum !== 0 ? 100 : 0) : ((newNum - oldNum) / Math.abs(oldNum)) * 100);
        var isUp = newNum > oldNum;
        el.classList.add(isUp ? 'up' : 'down');
        el.innerHTML = (isUp ? '<i class="fas fa-arrow-up"></i> ' : '<i class="fas fa-arrow-down"></i> ') + Math.abs(pct).toFixed(1) + '%';
        el.style.display = '';
        if (card) {
            card.classList.remove('kpi-up', 'kpi-down');
            card.classList.add(isUp ? 'kpi-up' : 'kpi-down');
            setTimeout(function() { card.classList.remove('kpi-up', 'kpi-down'); }, 600);
        }
    }

    function updateKpiValues(data) {
        hideKpiSpinners();
        if (!data) return;
        KPI_MAP.forEach(function(m) {
            var valueEl = document.getElementById(m.id);
            if (!valueEl) return;
            var newVal = data[m.key];
            if (newVal == null) newVal = 0;
            var newNum = Number(newVal);
            var isPct = m.suffix === '%';
            var current = getCurrentKpiValue(valueEl, isPct);
            var trendEl = document.getElementById(m.trendId);
            if (valuesEqual(newNum, current, isPct)) {
                if (trendEl) trendEl.style.display = 'none';
                return;
            }
            var from = isNaN(current) ? 0 : current;
            animateValue(valueEl, from, newNum, m.suffix, 600);
            var trendKey = m.key + '_trend_pct';
            updateTrend(m.trendId, newNum, current, data[trendKey], m.cardId);
        });
    }

    function showKpiError(message) {
        hideKpiSpinners();
        KPI_MAP.forEach(function(m) {
            var valueEl = document.getElementById(m.id);
            var trendEl = document.getElementById(m.trendId);
            if (valueEl) valueEl.textContent = m.suffix ? '0.00%' : '0.00';
            if (trendEl) { trendEl.textContent = ''; trendEl.className = 'kpi-trend'; trendEl.style.display = 'none'; }
        });
        var err = document.getElementById('kpi-error-alert');
        if (err) {
            err.textContent = message || 'Failed to load KPIs.';
            err.style.display = 'block';
        }
    }

    function toggleSaCustomDates() {
        var dateFilterEl = document.getElementById('sa_date_range');
        var startGroup = document.getElementById('sa_start_date_group');
        var endGroup = document.getElementById('sa_end_date_group');
        var startInput = document.getElementById('sa_start_date');
        var endInput = document.getElementById('sa_end_date');
        if (!dateFilterEl || !startGroup || !endGroup) return;
        if (dateFilterEl.value === 'custom') {
            startGroup.style.display = 'block';
            endGroup.style.display = 'block';
            var today = new Date().toISOString().slice(0, 10);
            if (startInput && !startInput.value) startInput.value = today;
            if (endInput && !endInput.value) endInput.value = today;
        } else {
            startGroup.style.display = 'none';
            endGroup.style.display = 'none';
        }
    }

    function getFilterParams() {
        var dateFilter = (document.getElementById('sa_date_range') && document.getElementById('sa_date_range').value) || 'today';
        var startDate = (document.getElementById('sa_start_date') && document.getElementById('sa_start_date').value) || '';
        var endDate = (document.getElementById('sa_end_date') && document.getElementById('sa_end_date').value) || '';
        var shopSelect = document.getElementById('sa_shops');
        var shopIds = [];
        if (shopSelect && shopSelect.value && shopSelect.value !== 'all') {
            var id = parseInt(shopSelect.value, 10);
            if (!isNaN(id)) shopIds.push(id);
        }
        return { date_filter: dateFilter, start_date: startDate, end_date: endDate, shop_ids: shopIds };
    }

    function fetchKPIs(clearValues) {
        showKpiSpinners(!!clearValues);
        var params = getFilterParams();
        var query = new URLSearchParams();
        query.set('date_filter', params.date_filter);
        if (params.start_date) query.set('start_date', params.start_date);
        if (params.end_date) query.set('end_date', params.end_date);
        if (params.shop_ids.length === 1) {
            query.set('shop_id', params.shop_ids[0]);
        } else if (params.shop_ids.length > 1) {
            params.shop_ids.forEach(function(id) { query.append('shop_ids[]', id); });
        }
        var url = '{{ route("super-admin.dashboard.kpis") }}?' + query.toString();

        fetch(url, {
            method: 'GET',
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
        })
        .then(function(res) {
            if (!res.ok) throw new Error('HTTP ' + res.status);
            return res.json();
        })
        .then(function(data) {
            updateKpiValues(data);
        })
        .catch(function(err) {
            showKpiError(err && err.message ? err.message : 'Failed to load KPIs.');
        });
    }

    document.addEventListener('DOMContentLoaded', function() {
        toggleSaCustomDates();
        var btn = document.getElementById('sa_apply_filters_btn');
        if (btn) btn.addEventListener('click', function() { fetchKPIs(true); });
        fetchKPIs(true);
        setInterval(function() { fetchKPIs(false); }, 5000);
    });

    window.toggleSaCustomDates = toggleSaCustomDates;
})();
</script>
@endsection

