@extends('dashboard.body.main')

@section('specificpagestyles')
<style>
    /*
     * Super Admin dashboard — Materio-like: soft shadows, large radius, purple accent, airy spacing.
     * Change theme: edit variables on .sa-dashboard-page
     */
    .sa-dashboard-page {
        --sa-primary: #8C57FF;
        --sa-primary-soft: rgba(140, 87, 255, 0.12);
        --sa-page-bg: #f5f5f9;
        --sa-card-bg: #ffffff;
        --sa-text-muted: #8592a3;
        --sa-text: #433c50;
        --sa-radius: 14px;
        --sa-radius-sm: 10px;
        --sa-shadow: 0 2px 6px 0 rgba(67, 89, 113, 0.12);
        --sa-shadow-lg: 0 4px 18px 0 rgba(67, 89, 113, 0.14);
        background: var(--sa-page-bg);
        margin: -0.5rem -1rem 0;
        padding: 1.25rem 1rem 2rem;
    }
    @media (min-width: 992px) {
        .sa-dashboard-page { margin: -0.5rem -1.5rem 0; padding: 1.5rem 1.5rem 2.5rem; }
    }

    .sa-dashboard-page .sa-page-heading h4 {
        color: var(--sa-text);
        font-weight: 700;
        font-size: 1.35rem;
        letter-spacing: -0.02em;
    }
    .sa-dashboard-page .sa-page-heading p {
        color: var(--sa-text-muted);
        font-size: 0.9rem;
    }

    .sa-dashboard-section + .sa-dashboard-section {
        margin-top: 1.75rem;
    }
    .sa-section-title {
        font-size: 0.75rem;
        font-weight: 600;
        letter-spacing: 0.08em;
        text-transform: uppercase;
        color: var(--sa-text-muted);
        margin-bottom: 1rem !important;
    }

    .sa-dashboard-page .card:not(.report-filter-card):not(.sa-insight-tile) {
        border: none !important;
        border-radius: var(--sa-radius) !important;
        background: var(--sa-card-bg);
        box-shadow: var(--sa-shadow) !important;
        overflow: hidden;
    }
    .sa-dashboard-page .card:not(.report-filter-card):not(.sa-insight-tile) .card-header {
        background: transparent !important;
        border-bottom: 1px solid rgba(67, 89, 113, 0.08) !important;
        padding: 1rem 1.25rem !important;
        font-weight: 600;
        color: var(--sa-text);
    }
    .sa-dashboard-page .card:not(.report-filter-card):not(.sa-insight-tile) .card-body {
        padding: 1.25rem 1.35rem !important;
    }

    /* Filter box: keep original Bootstrap / app styling (not Materio card overrides) */
    .sa-dashboard-page .report-filter-card .card-header {
        border: 0;
        padding-top: 0.5rem;
        padding-bottom: 0.5rem;
    }
    .sa-dashboard-page .report-filter-card .card-body {
        padding-top: 0;
    }

    .sa-dashboard-card-title {
        font-size: 0.8125rem;
        font-weight: 600;
        color: var(--sa-text-muted);
        margin-bottom: 0.35rem;
        text-transform: none;
        letter-spacing: 0;
    }
    .sa-dashboard-metric-value {
        font-size: 1.5rem;
        font-weight: 700;
        color: var(--sa-text);
        letter-spacing: -0.02em;
    }
    .sa-dashboard-subtext {
        font-size: 0.8125rem;
        color: var(--sa-text-muted);
        margin-top: 0.35rem;
    }

    /* KPI icon badges (rounded squares) */
    .sa-kpi-icon-badge {
        width: 52px;
        height: 52px;
        border-radius: var(--sa-radius-sm);
        display: flex;
        align-items: center;
        justify-content: center;
        margin-left: auto;
        margin-right: auto;
        font-size: 1.25rem;
    }
    .sa-kpi-icon-badge--success { background: rgba(40, 199, 111, 0.12); color: #28c76f; }
    .sa-kpi-icon-badge--warning { background: rgba(255, 159, 67, 0.15); color: #ff9f43; }
    .sa-kpi-icon-badge--neutral { background: rgba(133, 146, 163, 0.12); color: #8592a3; }
    .sa-kpi-icon-badge--info { background: rgba(0, 207, 232, 0.12); color: #00cfe8; }
    .sa-kpi-icon-badge--danger { background: rgba(234, 84, 85, 0.12); color: #ea5455; }
    .sa-kpi-icon-badge--primary { background: var(--sa-primary-soft); color: var(--sa-primary); }

    /* Consolidated KPI row: compact, icon on the right, crisp border */
    #consolidated-kpis > .col-12:first-child .sa-section-title {
        margin-bottom: 0.65rem !important;
    }
    #consolidated-kpis .col.mb-3,
    #consolidated-kpis .col-md-4.mb-3 {
        margin-bottom: 0.65rem !important;
    }
    #consolidated-kpis .card.hover-shadow {
        border: 1px solid rgba(30, 41, 59, 0.14) !important;
        box-shadow:
            0 1px 2px rgba(15, 23, 42, 0.05),
            0 2px 10px rgba(67, 89, 113, 0.06) !important;
    }
    #consolidated-kpis .card.hover-shadow:hover {
        border-color: rgba(30, 41, 59, 0.22) !important;
    }
    #consolidated-kpis .sa-kpi-card-body {
        padding: 0.65rem 0.8rem 0.55rem !important;
    }
    #consolidated-kpis .sa-kpi-layout {
        min-height: 3.25rem;
    }
    #consolidated-kpis .sa-kpi-icon-badge {
        width: 42px;
        height: 42px;
        margin: 0 !important;
        font-size: 1.05rem;
        border-radius: 10px;
        border: 1px solid rgba(30, 41, 59, 0.07);
        box-shadow: 0 1px 2px rgba(15, 23, 42, 0.04) inset;
    }
    #consolidated-kpis .sa-dashboard-card-title {
        font-size: 0.78rem;
        font-weight: 600;
        margin-bottom: 0.2rem !important;
        line-height: 1.25;
    }
    #consolidated-kpis .sa-dashboard-metric-value {
        font-size: 1.28rem !important;
        line-height: 1.2;
    }
    #consolidated-kpis .sa-dashboard-subtext {
        margin-top: 0.2rem !important;
        font-size: 0.72rem !important;
        line-height: 1.3;
    }

    .sa-dashboard-page .hover-shadow {
        transition: transform 0.2s ease, box-shadow 0.2s ease;
    }
    .sa-dashboard-page .hover-shadow:hover {
        transform: translateY(-2px);
        box-shadow: var(--sa-shadow-lg) !important;
    }

    .kpi-trend { font-size: 0.85rem; margin-left: 4px; font-weight: 600; }
    .kpi-trend.up { color: #28c76f; }
    .kpi-trend.down { color: #ea5455; }

    .activity-item {
        font-size: 0.875rem;
        padding: 0.5rem 0.75rem;
        border-radius: var(--sa-radius-sm);
        margin-bottom: 0.35rem;
        background: rgba(255, 255, 255, 0.6);
    }
    .sa-activity-card .ticker-toggle-btn {
        border: 0;
        background: rgba(67, 89, 113, 0.1);
        color: var(--sa-text) !important;
        width: 32px;
        height: 32px;
        border-radius: 8px;
        cursor: pointer;
        display: inline-flex;
        align-items: center;
        justify-content: center;
    }
    .sa-activity-card .ticker-toggle-btn:hover {
        background: rgba(67, 89, 113, 0.16);
    }

    /* Insights row — solid semantic tiles (Bootstrap-style), icon right, dark edge, no progress UI */
    .sa-insight-tile {
        border-radius: 8px;
        overflow: hidden;
        border: 1px solid rgba(0, 0, 0, 0.22) !important;
        box-shadow: 0 2px 6px rgba(0, 0, 0, 0.1);
        transition: transform 0.15s ease, box-shadow 0.15s ease;
    }
    .sa-insight-tile:hover {
        transform: translateY(-1px);
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.14);
    }
    .sa-insight-tile--info {
        background: #17a2b8;
        color: #fff;
    }
    .sa-insight-tile--success {
        background: #28a745;
        color: #fff;
    }
    .sa-insight-tile--warning {
        background: #ffc107;
        color: #212529;
        border-color: rgba(0, 0, 0, 0.28) !important;
    }
    .sa-insight-tile--danger {
        background: #dc3545;
        color: #fff;
    }
    .sa-insight-tile-body {
        padding: 0.6rem 0.7rem 0.5rem !important;
    }
    .sa-insight-label {
        font-size: 0.68rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.06em;
        opacity: 0.92;
        margin-bottom: 0.25rem;
        line-height: 1.2;
    }
    .sa-insight-tile--warning .sa-insight-label {
        color: #343a40;
        opacity: 0.88;
    }
    .sa-insight-name {
        font-size: 1.05rem;
        font-weight: 700;
        line-height: 1.25;
        margin-bottom: 0.15rem;
        word-break: break-word;
    }
    .sa-insight-tile--warning .sa-insight-name {
        color: #212529;
    }
    .sa-insight-meta {
        font-size: 0.74rem;
        font-weight: 500;
        opacity: 0.9;
        display: block;
    }
    .sa-insight-tile--warning .sa-insight-meta {
        color: #495057;
    }
    .sa-insight-icon {
        width: 40px;
        height: 40px;
        border-radius: 8px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.1rem;
        flex-shrink: 0;
        background: rgba(255, 255, 255, 0.22);
    }
    .sa-insight-tile--warning .sa-insight-icon {
        background: rgba(0, 0, 0, 0.1);
        color: #212529;
    }
    @keyframes saInsightFlash {
        0%, 100% { opacity: 1; }
        50% { opacity: 0.55; }
    }
    .sa-insight-meta.sa-insight-flash {
        animation: saInsightFlash 0.75s ease;
    }

    /* Activity ticker — match other card headers (light bar, compact) */
    .sa-activity-card .card-header {
        background: #f5f5f9 !important;
        border-bottom: 1px solid rgba(67, 89, 113, 0.1) !important;
        color: var(--sa-text);
        padding-top: 0.5rem !important;
        padding-bottom: 0.5rem !important;
    }
    .sa-activity-card .card-header h6 {
        color: var(--sa-text) !important;
        font-weight: 700;
    }

    /* Tables inside cards */
    .sa-dashboard-page .table-responsive {
        border-radius: 0 0 var(--sa-radius) var(--sa-radius);
    }
    #shop-performance-table thead th,
    #inventory-snapshot-table thead th {
        background: #f5f5f9 !important;
        color: var(--sa-text-muted);
        font-weight: 600;
        font-size: 0.75rem;
        text-transform: uppercase;
        letter-spacing: 0.04em;
        border-bottom: 1px solid rgba(67, 89, 113, 0.08) !important;
        padding: 0.85rem 1rem !important;
    }
    #shop-performance-table tbody td,
    #inventory-snapshot-table tbody td {
        padding: 0.75rem 1rem !important;
        vertical-align: middle;
        border-color: rgba(67, 89, 113, 0.06) !important;
    }
    #shop-performance-table tbody tr:hover td,
    #shop-performance-table tbody tr:hover th,
    #inventory-snapshot-table tbody tr:hover td,
    #inventory-snapshot-table tbody tr:hover th {
        background-color: rgba(140, 87, 255, 0.07) !important;
    }

    .sa-chart-wrap {
        min-height: 260px;
        position: relative;
    }

    .sa-icon-accent { color: var(--sa-primary) !important; }

    /* Striped rows — align with app default (#eef3f8 odd); previous “soft” override hid stripes */
    .content-page .sa-dashboard-page .table.table-striped:not(.table-borderless):not(.table-dark) tbody tr:nth-of-type(odd) td,
    .content-page .sa-dashboard-page .table.table-striped:not(.table-borderless):not(.table-dark) tbody tr:nth-of-type(odd) th {
        background-color: #eef3f8 !important;
    }
    .content-page .sa-dashboard-page .table.table-striped:not(.table-borderless):not(.table-dark) tbody tr:nth-of-type(even) td,
    .content-page .sa-dashboard-page .table.table-striped:not(.table-borderless):not(.table-dark) tbody tr:nth-of-type(even) th {
        background-color: #fff !important;
    }
    .content-page .sa-dashboard-page .table.table-striped:not(.table-borderless):not(.table-dark) tbody tr:hover td,
    .content-page .sa-dashboard-page .table.table-striped:not(.table-borderless):not(.table-dark) tbody tr:hover th {
        background-color: rgba(140, 87, 255, 0.1) !important;
    }

    .sa-dashboard-page #kpi-error-alert {
        border: none;
        border-radius: var(--sa-radius-sm);
        box-shadow: var(--sa-shadow);
    }

    #consolidated-kpis .card.kpi-up { animation: kpiPulseUp 0.5s ease; }
    #consolidated-kpis .card.kpi-down { animation: kpiPulseDown 0.5s ease; }
    @keyframes kpiPulseUp {
        0% { box-shadow: var(--sa-shadow); }
        50% { box-shadow: 0 4px 20px rgba(40, 199, 111, 0.25); }
        100% { box-shadow: var(--sa-shadow); }
    }
    @keyframes kpiPulseDown {
        0% { box-shadow: var(--sa-shadow); }
        50% { box-shadow: 0 4px 20px rgba(234, 84, 85, 0.22); }
        100% { box-shadow: var(--sa-shadow); }
    }
</style>
@endsection

@section('container')
<div class="sa-dashboard-page">
<div class="container-fluid px-2 px-lg-3">
    <div class="row">
        <div class="col-12">
            <div class="sa-page-heading mb-4">
                <h4 class="mb-1">Super Admin Comparison Dashboard</h4>
                <p class="mb-0">Executive overview across all child shops (view-only)</p>
            </div>
        </div>
    </div>

    {{-- 1️⃣ Global Filter Section (Top Bar) --}}
    <div class="row sa-dashboard-section">
        <div class="col-12">
            <div class="card report-filter-card border-primary shadow-sm">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h6 class="mb-0 text-primary"><i class="ri-filter-3-line mr-1"></i> Filters</h6>
                </div>
                <div class="card-body">
                    <form id="sa_filter_form" class="form-row align-items-end">
                        <div class="form-group col-md-3">
                            <label for="sa_date_range" class="mb-1">Date Filter</label>
                            <select id="sa_date_range" class="form-control" name="date_filter">
                                <option value="today" selected>Today</option>
                                <option value="yesterday">Yesterday</option>
                                <option value="this_week">This Week</option>
                                <option value="last_week">Last Week</option>
                                <option value="this_month">This Month</option>
                                <option value="last_month">Last Month</option>
                                <option value="this_year">This Year</option>
                                <option value="last_year">Last Year</option>
                                <option value="all_time">From Start to Today</option>
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
                                <i class="ri-search-line mr-1"></i> Filter
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    {{-- 2️⃣ Insights — solid color tiles (above consolidated KPIs) --}}
    <div class="row sa-dashboard-section sa-insights-row">
        <div class="col-12">
            <h6 class="sa-section-title mb-2">Insights</h6>
        </div>
        <div class="col-6 col-lg-3 mb-2">
            <div class="card sa-insight-tile sa-insight-tile--info h-100 border-0">
                <div class="card-body sa-insight-tile-body">
                    <div class="d-flex align-items-start justify-content-between gap-2">
                        <div class="text-left flex-grow-1 min-w-0 pr-1">
                            <div class="sa-insight-label">Top selling shop</div>
                            <div id="topSellingShop" class="sa-insight-name text-white">-</div>
                            <small id="topSellingValue" class="sa-insight-meta text-white">PKR 0.00</small>
                        </div>
                        <div class="sa-insight-icon text-white" aria-hidden="true"><i class="fas fa-trophy"></i></div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-6 col-lg-3 mb-2">
            <div class="card sa-insight-tile sa-insight-tile--success h-100 border-0">
                <div class="card-body sa-insight-tile-body">
                    <div class="d-flex align-items-start justify-content-between gap-2">
                        <div class="text-left flex-grow-1 min-w-0 pr-1">
                            <div class="sa-insight-label">Highest profit shop</div>
                            <div id="highestProfitShop" class="sa-insight-name text-white">-</div>
                            <small id="highestProfitValue" class="sa-insight-meta text-white">PKR 0.00</small>
                        </div>
                        <div class="sa-insight-icon text-white" aria-hidden="true"><i class="fas fa-coins"></i></div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-6 col-lg-3 mb-2">
            <div class="card sa-insight-tile sa-insight-tile--warning h-100 border-0">
                <div class="card-body sa-insight-tile-body">
                    <div class="d-flex align-items-start justify-content-between gap-2">
                        <div class="text-left flex-grow-1 min-w-0 pr-1">
                            <div class="sa-insight-label">Best margin shop</div>
                            <div id="bestMarginShop" class="sa-insight-name">-</div>
                            <small id="bestMarginValue" class="sa-insight-meta">0.00%</small>
                        </div>
                        <div class="sa-insight-icon" aria-hidden="true"><i class="fas fa-percentage"></i></div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-6 col-lg-3 mb-2">
            <div class="card sa-insight-tile sa-insight-tile--danger h-100 border-0">
                <div class="card-body sa-insight-tile-body">
                    <div class="d-flex align-items-start justify-content-between gap-2">
                        <div class="text-left flex-grow-1 min-w-0 pr-1">
                            <div class="sa-insight-label">Lowest performing</div>
                            <div id="lowestShop" class="sa-insight-name text-white">-</div>
                            <small id="lowestValue" class="sa-insight-meta text-white">PKR 0.00</small>
                        </div>
                        <div class="sa-insight-icon text-white" aria-hidden="true"><i class="fas fa-exclamation-triangle"></i></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- 3️⃣ Consolidated KPI Cards (All Shops Combined) --}}
    <div id="consolidated-kpis" class="row sa-dashboard-section">
        <div class="col-12">
            <h6 class="sa-section-title">Consolidated KPIs (All Selected Shops)</h6>
        </div>

        <!-- Total Sales -->
        <div class="col-12 col-sm-6 col-lg mb-3">
            <div class="card h-100 hover-shadow" id="card-totalSales">
                <div class="card-body sa-kpi-card-body position-relative">
                    <div id="kpi-spinner-totalSales" class="position-absolute top-50 start-50 translate-middle">
                        <span class="spinner-border spinner-border-sm text-success" role="status"></span>
                    </div>
                    <div class="sa-kpi-layout d-flex align-items-start justify-content-between gap-2">
                        <div class="sa-kpi-main text-left flex-grow-1 min-w-0">
                            <div class="sa-dashboard-card-title">Total Sales</div>
                            <div class="d-flex align-items-baseline flex-wrap">
                                <span id="totalSales" class="sa-dashboard-metric-value font-weight-bold text-success mb-0">…</span>
                                <span id="totalSalesTrend" class="kpi-trend" style="display:none;"></span>
                            </div>
                            <div class="sa-dashboard-subtext text-muted mb-0">All shops, selected period</div>
                        </div>
                        <div class="sa-kpi-icon-badge sa-kpi-icon-badge--success flex-shrink-0" aria-hidden="true">
                            <i class="fas fa-dollar-sign"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Total COGS -->
        <div class="col-12 col-sm-6 col-lg mb-3">
            <div class="card h-100 hover-shadow" id="card-totalCOGS">
                <div class="card-body sa-kpi-card-body position-relative">
                    <div id="kpi-spinner-totalCOGS" class="position-absolute top-50 start-50 translate-middle">
                        <span class="spinner-border spinner-border-sm text-warning" role="status"></span>
                    </div>
                    <div class="sa-kpi-layout d-flex align-items-start justify-content-between gap-2">
                        <div class="sa-kpi-main text-left flex-grow-1 min-w-0">
                            <div class="sa-dashboard-card-title">Total COGS</div>
                            <div class="d-flex align-items-baseline flex-wrap">
                                <span id="totalCOGS" class="sa-dashboard-metric-value font-weight-bold text-warning mb-0">…</span>
                                <span id="totalCOGSTrend" class="kpi-trend" style="display:none;"></span>
                            </div>
                            <div class="sa-dashboard-subtext text-muted mb-0">Cost of goods sold</div>
                        </div>
                        <div class="sa-kpi-icon-badge sa-kpi-icon-badge--warning flex-shrink-0" aria-hidden="true">
                            <i class="fas fa-boxes"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Total Purchases -->
        <div class="col-12 col-sm-6 col-lg mb-3">
            <div class="card h-100 hover-shadow" id="card-totalPurchases">
                <div class="card-body sa-kpi-card-body position-relative">
                    <div id="kpi-spinner-totalPurchases" class="position-absolute top-50 start-50 translate-middle">
                        <span class="spinner-border spinner-border-sm text-secondary" role="status"></span>
                    </div>
                    <div class="sa-kpi-layout d-flex align-items-start justify-content-between gap-2">
                        <div class="sa-kpi-main text-left flex-grow-1 min-w-0">
                            <div class="sa-dashboard-card-title">Total Purchases</div>
                            <div class="d-flex align-items-baseline flex-wrap">
                                <span id="totalPurchases" class="sa-dashboard-metric-value font-weight-bold text-secondary mb-0">…</span>
                                <span id="totalPurchasesTrend" class="kpi-trend" style="display:none;"></span>
                            </div>
                            <div class="sa-dashboard-subtext text-muted mb-0">Selected period</div>
                        </div>
                        <div class="sa-kpi-icon-badge sa-kpi-icon-badge--neutral flex-shrink-0" aria-hidden="true">
                            <i class="fas fa-shopping-cart"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Total Discounts -->
        <div class="col-12 col-sm-6 col-lg mb-3">
            <div class="card h-100 hover-shadow" id="card-totalDiscounts">
                <div class="card-body sa-kpi-card-body position-relative">
                    <div id="kpi-spinner-totalDiscounts" class="position-absolute top-50 start-50 translate-middle">
                        <span class="spinner-border spinner-border-sm text-info" role="status"></span>
                    </div>
                    <div class="sa-kpi-layout d-flex align-items-start justify-content-between gap-2">
                        <div class="sa-kpi-main text-left flex-grow-1 min-w-0">
                            <div class="sa-dashboard-card-title">Total Discounts</div>
                            <div class="d-flex align-items-baseline flex-wrap">
                                <span id="totalDiscounts" class="sa-dashboard-metric-value font-weight-bold text-info mb-0">…</span>
                                <span id="totalDiscountsTrend" class="kpi-trend" style="display:none;"></span>
                            </div>
                            <div class="sa-dashboard-subtext text-muted mb-0">Invoice + line discounts</div>
                        </div>
                        <div class="sa-kpi-icon-badge sa-kpi-icon-badge--info flex-shrink-0" aria-hidden="true">
                            <i class="fas fa-tag"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Total Expense -->
        <div class="col-12 col-sm-6 col-lg mb-3">
            <div class="card h-100 hover-shadow" id="card-totalExpense">
                <div class="card-body sa-kpi-card-body position-relative">
                    <div id="kpi-spinner-totalExpense" class="position-absolute top-50 start-50 translate-middle">
                        <span class="spinner-border spinner-border-sm text-danger" role="status"></span>
                    </div>
                    <div class="sa-kpi-layout d-flex align-items-start justify-content-between gap-2">
                        <div class="sa-kpi-main text-left flex-grow-1 min-w-0">
                            <div class="sa-dashboard-card-title">Total Expense</div>
                            <div class="d-flex align-items-baseline flex-wrap">
                                <span id="totalExpense" class="sa-dashboard-metric-value font-weight-bold text-danger mb-0">…</span>
                                <span id="totalExpenseTrend" class="kpi-trend" style="display:none;"></span>
                            </div>
                            <div class="sa-dashboard-subtext text-muted mb-0">Selected period</div>
                        </div>
                        <div class="sa-kpi-icon-badge sa-kpi-icon-badge--danger flex-shrink-0" aria-hidden="true">
                            <i class="fas fa-money-bill-wave"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Second row: Gross Profit, Net Profit, Profit Margin --}}
        <div class="w-100"></div>
        <!-- Gross Profit -->
        <div class="col-12 col-md-4 mb-3">
            <div class="card h-100 hover-shadow" id="card-grossProfit">
                <div class="card-body sa-kpi-card-body position-relative">
                    <div id="kpi-spinner-grossProfit" class="position-absolute top-50 start-50 translate-middle">
                        <span class="spinner-border spinner-border-sm text-primary" role="status"></span>
                    </div>
                    <div class="sa-kpi-layout d-flex align-items-start justify-content-between gap-2">
                        <div class="sa-kpi-main text-left flex-grow-1 min-w-0">
                            <div class="sa-dashboard-card-title">Gross Profit</div>
                            <div class="d-flex align-items-baseline flex-wrap">
                                <span id="grossProfit" class="sa-dashboard-metric-value font-weight-bold text-primary mb-0">…</span>
                                <span id="grossProfitTrend" class="kpi-trend" style="display:none;"></span>
                            </div>
                            <div class="sa-dashboard-subtext text-muted mb-0">Sales − COGS</div>
                        </div>
                        <div class="sa-kpi-icon-badge sa-kpi-icon-badge--primary flex-shrink-0" aria-hidden="true">
                            <i class="fas fa-chart-line"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Net Profit -->
        <div class="col-12 col-md-4 mb-3">
            <div class="card h-100 hover-shadow" id="card-netProfit">
                <div class="card-body sa-kpi-card-body position-relative">
                    <div id="kpi-spinner-netProfit" class="position-absolute top-50 start-50 translate-middle">
                        <span class="spinner-border spinner-border-sm text-success" role="status"></span>
                    </div>
                    <div class="sa-kpi-layout d-flex align-items-start justify-content-between gap-2">
                        <div class="sa-kpi-main text-left flex-grow-1 min-w-0">
                            <div class="sa-dashboard-card-title">Net Profit</div>
                            <div class="d-flex align-items-baseline flex-wrap">
                                <span id="netProfit" class="sa-dashboard-metric-value font-weight-bold text-success mb-0">…</span>
                                <span id="netProfitTrend" class="kpi-trend" style="display:none;"></span>
                            </div>
                            <div class="sa-dashboard-subtext text-muted mb-0">After discounts</div>
                        </div>
                        <div class="sa-kpi-icon-badge sa-kpi-icon-badge--success flex-shrink-0" aria-hidden="true">
                            <i class="fas fa-coins"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Profit Margin % -->
        <div class="col-12 col-md-4 mb-3">
            <div class="card h-100 hover-shadow" id="card-profitMargin">
                <div class="card-body sa-kpi-card-body position-relative">
                    <div id="kpi-spinner-profitMargin" class="position-absolute top-50 start-50 translate-middle">
                        <span class="spinner-border spinner-border-sm text-primary" role="status"></span>
                    </div>
                    <div class="sa-kpi-layout d-flex align-items-start justify-content-between gap-2">
                        <div class="sa-kpi-main text-left flex-grow-1 min-w-0">
                            <div class="sa-dashboard-card-title">Profit Margin %</div>
                            <div class="d-flex align-items-baseline flex-wrap">
                                <span id="profitMargin" class="sa-dashboard-metric-value font-weight-bold text-primary mb-0">…</span>
                                <span id="profitMarginTrend" class="kpi-trend" style="display:none;"></span>
                            </div>
                            <div class="sa-dashboard-subtext text-muted mb-0">Net profit ÷ sales</div>
                        </div>
                        <div class="sa-kpi-icon-badge sa-kpi-icon-badge--primary flex-shrink-0" aria-hidden="true">
                            <i class="fas fa-percentage"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div id="kpi-error-alert" class="alert alert-danger mt-2" style="display: none;" role="alert"></div>

    {{-- 4️⃣ Live Activity Ticker --}}
    <div class="row sa-dashboard-section">
        <div class="col-12">
            <div class="card sa-activity-card mb-2">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h6 class="mb-0 text-uppercase">Live Activity Ticker</h6>
                    <button
                        type="button"
                        id="activityTickerToggle"
                        class="ticker-toggle-btn"
                        data-toggle="collapse"
                        data-target="#activityTickerCollapse"
                        aria-expanded="true"
                        aria-controls="activityTickerCollapse"
                    >
                        <i class="fas fa-chevron-up"></i>
                    </button>
                </div>
                <div id="activityTickerCollapse" class="collapse show">
                <div class="card-body py-2">
                    <div id="activityTicker" class="d-flex flex-column"></div>
                </div>
                </div>
            </div>
        </div>
    </div>

    {{-- 5️⃣ Child Shop Comparison Table --}}
    <div class="row sa-dashboard-section">
        <div class="col-12">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h6 class="mb-0">Child Shop Performance Comparison</h6>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table id="shop-performance-table" class="table mb-0 table-striped table-hover">
                            <thead>
                                <tr>
                                    <th>Shop Name</th>
                                    <th class="text-right"><i class="fas fa-dollar-sign mr-1"></i>Sales</th>
                                    <th class="text-right"><i class="fas fa-shopping-cart mr-1"></i>Orders</th>
                                    <th class="text-right"><i class="fas fa-box mr-1"></i>COGS</th>
                                    <th class="text-right"><i class="fas fa-chart-line mr-1"></i>Gross Profit</th>
                                    <th class="text-right"><i class="fas fa-tag mr-1"></i>Invoice Discount</th>
                                    <th class="text-right"><i class="fas fa-coins mr-1"></i>Net Profit</th>
                                    <th class="text-right"><i class="fas fa-percentage mr-1"></i>Margin %</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($shops ?? [] as $shop)
                                    <tr>
                                        <td>{{ $shop->name ?? 'Shop Name' }}</td>
                                        <td id="sales_{{ $shop->id }}" class="text-right">{{ number_format(0, 2) }}</td>
                                        <td id="orders_{{ $shop->id }}" class="text-right">{{ number_format(0, 0) }}</td>
                                        <td id="cogs_{{ $shop->id }}" class="text-right">{{ number_format(0, 2) }}</td>
                                        <td id="gross_{{ $shop->id }}" class="text-right">{{ number_format(0, 2) }}</td>
                                        <td id="discount_{{ $shop->id }}" class="text-right">{{ number_format(0, 2) }}</td>
                                        <td id="net_{{ $shop->id }}" class="text-right">0.00</td>
                                        <td id="margin_{{ $shop->id }}" class="text-right">0.00%</td>
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

    {{-- 6️⃣ Comparison Charts Section --}}
    <div class="row sa-dashboard-section">
        <div class="col-12">
            <h6 class="sa-section-title">Visual Comparisons</h6>
        </div>
        <div class="col-lg-6 mb-3">
            <div class="card h-100">
                <div class="card-header">
                    <h6 class="mb-0">Sales Comparison</h6>
                </div>
                <div class="card-body sa-chart-wrap">
                    <canvas id="salesComparisonChart" height="160"></canvas>
                </div>
            </div>
        </div>
        <div class="col-lg-6 mb-3">
            <div class="card h-100">
                <div class="card-header">
                    <h6 class="mb-0">Net Profit Comparison</h6>
                </div>
                <div class="card-body sa-chart-wrap">
                    <canvas id="profitComparisonChart" height="160"></canvas>
                </div>
            </div>
        </div>
    </div>

    {{-- 7️⃣ Inventory Snapshot Section --}}
    <div class="row sa-dashboard-section mb-4">
        <div class="col-12">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h6 class="mb-0">Inventory Snapshot</h6>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table id="inventory-snapshot-table" class="table mb-0 table-striped table-hover">
                            <thead class="thead-light">
                                <tr>
                                    <th>Shop Name</th>
                                    <th class="text-right"><i class="fas fa-box-open mr-1 text-muted"></i>Stock Value</th>
                                    <th class="text-right"><i class="fas fa-exclamation-circle mr-1 text-muted"></i>Low Stock Count</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($shops ?? [] as $shop)
                                    <tr>
                                        <td>{{ $shop->name ?? 'Shop Name' }}</td>
                                        <td id="stock_{{ $shop->id }}" class="text-right">{{ number_format(0, 2) }}</td>
                                        <td id="lowstock_{{ $shop->id }}" class="text-right">{{ number_format(0, 0) }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="3" class="text-center text-muted py-4">
                                            No shops to show.
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
</div>
@endsection

@section('specificpagescripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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
    var lastActivityTime = null;
    var previousShopStats = {};
    var previousInsights = {};
    var previousInventory = {};
    var salesChart = null;
    var profitChart = null;
    var previousChartData = {
        sales: [],
        profit: []
    };

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

    var SA_ERP_LAUNCH = @json($erp_launch_date ?? config('app.erp_launch_date', '2025-11-01'));

    function toggleSaCustomDates() {
        var dateFilterEl = document.getElementById('sa_date_range');
        var startGroup = document.getElementById('sa_start_date_group');
        var endGroup = document.getElementById('sa_end_date_group');
        var startInput = document.getElementById('sa_start_date');
        var endInput = document.getElementById('sa_end_date');
        if (!dateFilterEl || !startGroup || !endGroup) return;

        var v = dateFilterEl.value;

        if (v === 'all_time') {
            var launch = SA_ERP_LAUNCH || '2025-11-01';
            var today = new Date().toISOString().slice(0, 10);
            startGroup.style.display = 'block';
            endGroup.style.display = 'block';
            if (startInput) startInput.value = launch;
            if (endInput) endInput.value = today;
            $('#sa_start_date, #sa_end_date').prop('readonly', true);
            fetchKPIs(true);
            fetchShopPerformance();
            fetchInsights();
            fetchChartData();
            fetchInventorySnapshot();
            return;
        }

        $('#sa_start_date, #sa_end_date').prop('readonly', false);

        if (v === 'custom') {
            startGroup.style.display = 'block';
            endGroup.style.display = 'block';
            var todayStr = new Date().toISOString().slice(0, 10);
            if (startInput && !startInput.value) startInput.value = todayStr;
            if (endInput && !endInput.value) endInput.value = todayStr;
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

    function escapeHtml(value) {
        return $('<div>').text(value || '').html();
    }

    function renderActivity(activity) {
        var color = activity.type === 'sale' ? 'success' : 'danger';
        var icon = activity.type === 'sale' ? 'fa-arrow-up' : 'fa-arrow-down';
        var typeLabel = String(activity.type || '').toUpperCase();
        var shop = escapeHtml(activity.shop || 'Unknown Shop');
        var amount = Number(activity.amount || 0).toLocaleString();

        var html = '\
            <div class="activity-item text-' + color + '" style="display:none;">\
                <i class="fas ' + icon + '"></i>\
                ' + typeLabel + ' -\
                <strong>' + shop + '</strong> -\
                PKR ' + amount + '\
            </div>\
        ';

        $('#activityTicker').prepend(html);
        $('#activityTicker .activity-item:first').slideDown(300);

        if ($('#activityTicker .activity-item').length > 10) {
            $('#activityTicker .activity-item:last').remove();
        }
    }

    function fetchActivities() {
        $.get('{{ route("super-admin.dashboard.activities") }}', function(data) {
            if (!Array.isArray(data) || !data.length) {
                return;
            }

            data.slice().reverse().forEach(function(activity) {
                if (lastActivityTime && activity.time <= lastActivityTime) {
                    return;
                }
                renderActivity(activity);
            });

            lastActivityTime = data[0].time;
        });
    }

    function formatStat(field, value) {
        var num = Number(value || 0);
        if (field === 'orders') {
            return num.toLocaleString('en-US', { maximumFractionDigits: 0 });
        }
        if (field === 'margin') {
            return num.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + '%';
        }
        return num.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    function applyMetricTone(field, selector, value) {
        var el = $(selector);
        if (!el.length) return;
        el.removeClass('text-success text-danger text-warning');

        if (field === 'net') {
            el.addClass(Number(value) >= 0 ? 'text-success' : 'text-danger');
            return;
        }

        if (field === 'margin') {
            var margin = Number(value || 0);
            if (margin > 20) {
                el.addClass('text-success');
            } else if (margin < 10) {
                el.addClass('text-danger');
            } else {
                el.addClass('text-warning');
            }
        }
    }

    function animateNumber(selector, field, value) {
        var el = $(selector);
        if (!el.length) return;

        var isMargin = field === 'margin';
        var isOrders = field === 'orders';
        var currentRaw = (el.text() || '0').replace(/,/g, '').replace('%', '');
        var start = parseFloat(currentRaw);
        if (isNaN(start)) start = 0;
        var end = Number(value || 0);

        $({ n: start }).animate({ n: end }, {
            duration: 500,
            step: function(now) {
                if (isOrders) {
                    el.text(Math.round(now).toLocaleString('en-US'));
                } else if (isMargin) {
                    el.text(now.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + '%');
                } else {
                    el.text(now.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 }));
                }
            },
            complete: function() {
                el.text(formatStat(field, end));
            }
        });
    }

    function updateCell(field, shop, prev) {
        var selector = '#' + field + '_' + shop.shop_id;
        var newVal = Number(shop[field] || 0);
        var oldVal = Number(prev[field] || 0);
        if (newVal === oldVal) {
            applyMetricTone(field, selector, newVal);
            return;
        }

        animateNumber(selector, field, newVal);

        var el = $(selector);
        var indicator = newVal > oldVal
            ? ' <span class="text-success small ml-1">▲</span>'
            : ' <span class="text-danger small ml-1">▼</span>';
        el.find('.delta-indicator').remove();
        el.append('<span class="delta-indicator">' + indicator + '</span>');

        el.addClass(newVal > oldVal ? 'text-success' : 'text-danger');
        setTimeout(function() {
            el.find('.delta-indicator').remove();
            el.removeClass('text-success text-danger');
            applyMetricTone(field, selector, newVal);
        }, 800);
    }

    function fetchShopPerformance() {
        var filters = {
            date_filter: $('#sa_date_range').val(),
            start_date: $('#sa_start_date').val(),
            end_date: $('#sa_end_date').val(),
            shops: $('#sa_shops').val()
        };

        $.get('{{ route("dashboard.shop-performance") }}', filters, function(res) {
            if (!res || !Array.isArray(res.shops)) return;

            res.shops.forEach(function(shop) {
                var prev = previousShopStats[shop.shop_id] || {};

                updateCell('sales', shop, prev);
                updateCell('orders', shop, prev);
                updateCell('cogs', shop, prev);
                updateCell('gross', shop, prev);
                updateCell('discount', shop, prev);
                updateCell('net', shop, prev);
                updateCell('margin', shop, prev);

                previousShopStats[shop.shop_id] = shop;
            });
        });
    }

    function chartCurrency(value) {
        return 'PKR ' + Number(value || 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    function updateSalesChart(labels, data) {
        if (JSON.stringify(previousChartData.sales) === JSON.stringify(data)) return;
        previousChartData.sales = data.slice();

        if (salesChart) {
            salesChart.data.labels = labels;
            salesChart.data.datasets[0].data = data;
            salesChart.update();
            return;
        }

        var ctx = document.getElementById('salesComparisonChart');
        if (!ctx || typeof Chart === 'undefined') return;

        salesChart = new Chart(ctx, {
            type: 'bar',
            data: {
                labels: labels,
                datasets: [{
                    label: 'Sales',
                    data: data,
                    backgroundColor: 'rgba(140, 87, 255, 0.75)',
                    borderRadius: 8,
                    borderSkipped: false
                }]
            },
            options: {
                indexAxis: 'y',
                animation: { duration: 800 },
                scales: {
                    x: {
                        grid: { color: 'rgba(67, 89, 113, 0.08)' },
                        ticks: { color: '#8592a3', font: { size: 11 } }
                    },
                    y: {
                        grid: { display: false },
                        ticks: { color: '#433c50', font: { size: 11 } }
                    }
                },
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        backgroundColor: 'rgba(67, 89, 113, 0.92)',
                        cornerRadius: 8,
                        callbacks: {
                            label: function(context) { return chartCurrency(context.parsed.x); }
                        }
                    }
                },
                responsive: true,
                maintainAspectRatio: false
            }
        });
    }

    function updateProfitChart(labels, data) {
        if (JSON.stringify(previousChartData.profit) === JSON.stringify(data)) return;
        previousChartData.profit = data.slice();

        var colors = data.map(function(val) {
            return Number(val) >= 0 ? 'rgba(40, 199, 111, 0.75)' : 'rgba(234, 84, 85, 0.75)';
        });

        if (profitChart) {
            profitChart.data.labels = labels;
            profitChart.data.datasets[0].data = data;
            profitChart.data.datasets[0].backgroundColor = colors;
            profitChart.update();
            return;
        }

        var ctx = document.getElementById('profitComparisonChart');
        if (!ctx || typeof Chart === 'undefined') return;

        profitChart = new Chart(ctx, {
            type: 'bar',
            data: {
                labels: labels,
                datasets: [{
                    label: 'Net Profit',
                    data: data,
                    backgroundColor: colors,
                    borderRadius: 8,
                    borderSkipped: false
                }]
            },
            options: {
                animation: { duration: 800 },
                scales: {
                    x: {
                        grid: { display: false },
                        ticks: { color: '#433c50', font: { size: 11 }, maxRotation: 45 }
                    },
                    y: {
                        grid: { color: 'rgba(67, 89, 113, 0.08)' },
                        ticks: { color: '#8592a3', font: { size: 11 } }
                    }
                },
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        backgroundColor: 'rgba(67, 89, 113, 0.92)',
                        cornerRadius: 8,
                        callbacks: {
                            label: function(context) { return chartCurrency(context.parsed.y); }
                        }
                    }
                },
                responsive: true,
                maintainAspectRatio: false
            }
        });
    }

    function fetchChartData() {
        var filters = {
            date_filter: $('#sa_date_range').val(),
            start_date: $('#sa_start_date').val(),
            end_date: $('#sa_end_date').val(),
            shops: $('#sa_shops').val()
        };

        $.get('{{ route("dashboard.shop-performance") }}', filters, function(res) {
            if (!res || !Array.isArray(res.shops)) return;

            var salesSorted = res.shops.slice().sort(function(a, b) {
                return Number(b.sales || 0) - Number(a.sales || 0);
            });
            var profitSorted = res.shops.slice().sort(function(a, b) {
                return Number(b.net || b.net_profit || 0) - Number(a.net || a.net_profit || 0);
            });

            var salesLabels = [];
            var salesData = [];
            salesSorted.forEach(function(shop) {
                salesLabels.push(shop.shop_name);
                salesData.push(Number(shop.sales || 0));
            });

            var profitLabels = [];
            var profitData = [];
            profitSorted.forEach(function(shop) {
                profitLabels.push(shop.shop_name);
                profitData.push(Number(shop.net || shop.net_profit || 0));
            });

            updateSalesChart(salesLabels, salesData);
            updateProfitChart(profitLabels, profitData);
        });
    }

    function animateInsightValue(selector, value, isPercent) {
        var el = $(selector);
        if (!el.length) return;
        var currentRaw = (el.text() || '0').replace(/,/g, '').replace('PKR', '').replace('%', '').trim();
        var start = parseFloat(currentRaw);
        if (isNaN(start)) start = 0;
        var end = Number(value || 0);

        $({ n: start }).animate({ n: end }, {
            duration: 500,
            step: function(now) {
                if (isPercent) {
                    el.text(now.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + '%');
                } else {
                    el.text('PKR ' + now.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 }));
                }
            },
            complete: function() {
                if (isPercent) {
                    el.text(end.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + '%');
                } else {
                    el.text('PKR ' + end.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 }));
                }
            }
        });
    }

    function updateInsight(prefix, newData, oldData) {
        if (!newData) return;
        oldData = oldData || {};
        var valueChanged = Number(newData.value || 0) !== Number(oldData.value || 0);
        var nameChanged = (newData.name || '-') !== (oldData.name || '-');
        if (!valueChanged && !nameChanged) return;

        $('#' + prefix + 'Shop').text((newData.name || '-') + (prefix === 'topSelling' ? ' 👑' : ''));
        animateInsightValue('#' + prefix + 'Value', Number(newData.value || 0), prefix === 'bestMargin');

        var el = $('#' + prefix + 'Value');
        el.addClass('sa-insight-flash');
        setTimeout(function() { el.removeClass('sa-insight-flash'); }, 800);
    }

    function fetchInsights() {
        var filters = {
            date_filter: $('#sa_date_range').val(),
            start_date: $('#sa_start_date').val(),
            end_date: $('#sa_end_date').val(),
            shops: $('#sa_shops').val()
        };

        $.get('{{ route("dashboard.insights") }}', filters, function(data) {
            if (!data) return;

            updateInsight('topSelling', data.top_selling, previousInsights.top_selling);
            updateInsight('highestProfit', data.highest_profit, previousInsights.highest_profit);
            updateInsight('bestMargin', data.best_margin, previousInsights.best_margin);
            updateInsight('lowest', data.lowest, previousInsights.lowest);

            previousInsights = data;
        });
    }

    function applyLowStockTone(selector, count) {
        var el = $(selector);
        if (!el.length) return;
        el.removeClass('text-success text-danger text-warning');
        var n = Number(count || 0);
        if (n > 10) {
            el.addClass('text-danger');
        } else if (n >= 5) {
            el.addClass('text-warning');
        } else {
            el.addClass('text-success');
        }
    }

    function animateInventoryNumber(selector, newVal, isInteger) {
        var el = $(selector);
        if (!el.length) return;
        var currentRaw = (el.text() || '0').replace(/,/g, '');
        var start = parseFloat(currentRaw);
        if (isNaN(start)) start = 0;
        var end = Number(newVal);
        $({ n: start }).animate({ n: end }, {
            duration: 500,
            step: function(now) {
                if (isInteger) {
                    el.text(Math.round(now).toLocaleString('en-US'));
                } else {
                    el.text(now.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 }));
                }
            },
            complete: function() {
                if (isInteger) {
                    el.text(Math.round(end).toLocaleString('en-US'));
                } else {
                    el.text(end.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 }));
                }
            }
        });
    }

    function updateInventoryCell(field, shop, prev) {
        prev = prev || {};
        var id = '#' + field + '_' + shop.shop_id;
        var newVal = field === 'stock' ? shop.stock_value : shop.low_stock;
        var oldVal = field === 'stock' ? prev.stock_value : prev.low_stock;
        if (Number(newVal) === Number(oldVal)) {
            if (field === 'lowstock') {
                applyLowStockTone(id, newVal);
            }
            return;
        }
        animateInventoryNumber(id, newVal, field === 'lowstock');
        var el = $(id);
        if (field === 'lowstock') {
            el.addClass('text-danger');
        } else {
            el.addClass('text-success');
        }
        setTimeout(function() {
            el.removeClass('text-danger text-success');
            if (field === 'lowstock') {
                applyLowStockTone(id, newVal);
            } else if (Number(newVal) > Number(oldVal)) {
                el.addClass('text-success');
            }
        }, 800);
    }

    function fetchInventorySnapshot() {
        var filters = {
            date_filter: $('#sa_date_range').val(),
            start_date: $('#sa_start_date').val(),
            end_date: $('#sa_end_date').val(),
            shops: $('#sa_shops').val()
        };
        $.get('{{ route("dashboard.inventory-snapshot") }}', filters, function(res) {
            if (!res || !Array.isArray(res.shops)) return;
            res.shops.forEach(function(shop) {
                var prev = previousInventory[shop.shop_id] || {};
                updateInventoryCell('stock', shop, prev);
                updateInventoryCell('lowstock', shop, prev);
                previousInventory[shop.shop_id] = shop;
            });
        });
    }

    document.addEventListener('DOMContentLoaded', function() {
        $('#sa_date_range').on('change', toggleSaCustomDates);

        $('#activityTickerCollapse').on('shown.bs.collapse', function() {
            $('#activityTickerToggle i').removeClass('fa-chevron-down').addClass('fa-chevron-up');
        });
        $('#activityTickerCollapse').on('hidden.bs.collapse', function() {
            $('#activityTickerToggle i').removeClass('fa-chevron-up').addClass('fa-chevron-down');
        });

        toggleSaCustomDates();
        var btn = document.getElementById('sa_apply_filters_btn');
        if (btn) btn.addEventListener('click', function() {
            previousShopStats = {};
            previousInsights = {};
            previousInventory = {};
            previousChartData = { sales: [], profit: [] };
            fetchKPIs(true);
            fetchShopPerformance();
            fetchInsights();
            fetchChartData();
            fetchInventorySnapshot();
        });
        fetchKPIs(true);
        fetchActivities();
        fetchShopPerformance();
        fetchInsights();
        fetchChartData();
        fetchInventorySnapshot();
        setInterval(function() { fetchKPIs(false); }, 5000);
        setInterval(fetchActivities, 5000);
        setInterval(fetchShopPerformance, 5000);
        setInterval(fetchInsights, 5000);
        setInterval(fetchChartData, 5000);
        setInterval(fetchInventorySnapshot, 5000);
    });

    window.toggleSaCustomDates = toggleSaCustomDates;
})();
</script>
@endsection

