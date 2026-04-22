@extends('dashboard.body.main')

@section('specificpagestyles')
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<link href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" rel="stylesheet" />
<style>
/* Keep filter dropdowns inside columns to prevent overlap */
#orders-filter-form .form-control,
#orders-filter-form .select2-container { max-width: 100%; box-sizing: border-box; }
#orders-filter-form .row [class^="col-"] { min-width: 0; }

/* Orders list KPI — fixed max width so tiles don’t stretch across half the viewport */
.orders-page-kpis .orders-kpi-strip {
    display: flex;
    flex-wrap: wrap;
    align-items: stretch;
    gap: 0.75rem 1rem;
}
.orders-page-kpis .orders-kpi-col {
    flex: 0 1 auto;
    width: 100%;
    max-width: 100%;
}
/* Two cards per row (strip column-gap is 1rem) */
@media (min-width: 576px) and (max-width: 991.98px) {
    .orders-page-kpis .orders-kpi-col {
        flex: 1 1 calc((100% - 1rem) / 2);
        min-width: 0;
        max-width: calc((100% - 1rem) / 2);
    }
}
/* Four KPIs in one row, equal width */
@media (min-width: 992px) {
    .orders-page-kpis .orders-kpi-strip {
        flex-wrap: nowrap;
    }
    .orders-page-kpis .orders-kpi-col {
        flex: 1 1 0;
        min-width: 0;
        max-width: none;
    }
}

/* Orders list KPI tiles — crisp edge: slate border + soft lift (no dark fill) */
.orders-page-kpis .orders-kpi-card {
    position: relative;
    border: 1px solid rgba(30, 41, 59, 0.16);
    border-radius: 14px;
    background: #fff;
    width: 100%;
    box-shadow:
        0 1px 0 rgba(255, 255, 255, 0.9) inset,
        0 1px 2px rgba(15, 23, 42, 0.05),
        0 4px 12px rgba(67, 89, 113, 0.07);
    overflow: hidden;
    transition: border-color 0.2s ease, box-shadow 0.2s ease;
}
.orders-page-kpis .orders-kpi-card:hover {
    border-color: rgba(30, 41, 59, 0.26);
    box-shadow:
        0 1px 0 rgba(255, 255, 255, 0.9) inset,
        0 2px 4px rgba(15, 23, 42, 0.06),
        0 8px 20px rgba(67, 89, 113, 0.1);
}
.orders-page-kpis .orders-kpi-card .card-body {
    padding: 0.85rem 1rem 0.75rem;
}
.orders-page-kpis .orders-kpi-top {
    display: flex;
    align-items: flex-start;
    gap: 0.65rem;
    margin-bottom: 0.5rem;
}
.orders-page-kpis .orders-kpi-main {
    flex: 1;
    min-width: 0;
}
.orders-page-kpis .orders-kpi-icon-wrap {
    width: 48px;
    height: 48px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.2rem;
    flex-shrink: 0;
    border: 1px solid rgba(30, 41, 59, 0.08);
    box-shadow: 0 1px 2px rgba(15, 23, 42, 0.04) inset;
}
.orders-page-kpis .orders-kpi-card--primary .orders-kpi-icon-wrap {
    background: rgba(13, 110, 253, 0.1);
    color: #0a58ca;
    border-color: rgba(13, 110, 253, 0.22);
}
.orders-page-kpis .orders-kpi-card--success .orders-kpi-icon-wrap {
    background: rgba(25, 135, 84, 0.1);
    color: #146c43;
    border-color: rgba(25, 135, 84, 0.22);
}
.orders-page-kpis .orders-kpi-card--info .orders-kpi-icon-wrap {
    background: rgba(13, 202, 240, 0.12);
    color: #0aa2c0;
    border-color: rgba(13, 202, 240, 0.28);
}
.orders-page-kpis .orders-kpi-card--warning .orders-kpi-icon-wrap {
    background: rgba(255, 193, 7, 0.15);
    color: #cc9a06;
    border-color: rgba(255, 193, 7, 0.35);
}
.orders-page-kpis .orders-kpi-value {
    font-size: 1.6rem;
    font-weight: 700;
    line-height: 1.15;
    color: #1e293b;
    letter-spacing: -0.02em;
    font-variant-numeric: tabular-nums;
    word-break: break-word;
}
.orders-page-kpis .orders-kpi-label {
    font-size: 0.875rem;
    font-weight: 600;
    color: #566a7f;
    margin-bottom: 0.25rem;
    line-height: 1.3;
}
.orders-page-kpis .orders-kpi-meta {
    font-size: 0.78rem;
    color: #8592a3;
    line-height: 1.35;
    max-width: 36em;
}
.orders-page-kpis .orders-kpi-accent {
    position: absolute;
    left: 0;
    right: 0;
    bottom: 0;
    height: 3px;
    border-radius: 0 0 14px 14px;
}
.orders-page-kpis .orders-kpi-card--primary .orders-kpi-accent {
    background: linear-gradient(90deg, #0d6efd, #4dabf7);
}
.orders-page-kpis .orders-kpi-card--success .orders-kpi-accent {
    background: linear-gradient(90deg, #198754, #51cf66);
}
.orders-page-kpis .orders-kpi-card--info .orders-kpi-accent {
    background: linear-gradient(90deg, #17a2b8, #3dd5f3);
}
.orders-page-kpis .orders-kpi-card--warning .orders-kpi-accent {
    background: linear-gradient(90deg, #ffc107, #ffda6a);
}

/* Per-customer row — AdminLTE-style small boxes (solid fill + ghost icon; no footer) */
.orders-by-customer-section .orders-customer-card-strip {
    display: flex;
    flex-wrap: wrap;
    align-items: stretch;
    gap: 0.75rem 1rem;
}
.orders-by-customer-section .orders-customer-card-col {
    flex: 1 1 220px;
    min-width: min(200px, 100%);
    max-width: 100%;
}
@media (min-width: 768px) {
    .orders-by-customer-section .orders-customer-card-col {
        max-width: min(320px, 100%);
    }
}
.orders-by-customer-section .orders-customer-box {
    position: relative;
    display: block;
    width: 100%;
    border-radius: 0.25rem;
    overflow: hidden;
    box-shadow: 0 1px 1px rgba(0, 0, 0, 0.12);
    text-decoration: none;
    color: #fff;
    transition: filter 0.15s ease, box-shadow 0.15s ease;
}
.orders-by-customer-section a.orders-customer-box:hover {
    text-decoration: none;
    filter: brightness(1.07);
    box-shadow: 0 2px 6px rgba(0, 0, 0, 0.15);
}
.orders-by-customer-section .orders-customer-box-body {
    position: relative;
    padding: 0.65rem 1rem 0.85rem;
    z-index: 1;
}
.orders-by-customer-section .orders-customer-box-bgicon {
    position: absolute;
    right: 0.35rem;
    top: 0.35rem;
    font-size: 4.25rem;
    line-height: 1;
    opacity: 0.2;
    color: inherit;
    z-index: 0;
    pointer-events: none;
}
.orders-by-customer-section .orders-customer-box-value {
    position: relative;
    z-index: 1;
    font-size: 1rem;
    font-weight: 700;
    line-height: 1.15;
    letter-spacing: -0.02em;
    font-variant-numeric: tabular-nums;
    margin-bottom: 0.35rem;
    text-shadow: 0 1px 0 rgba(0, 0, 0, 0.08);
}
.orders-by-customer-section .orders-customer-box-title {
    position: relative;
    z-index: 1;
    font-size: 0.95rem;
    font-weight: 600;
    line-height: 1.3;
    word-break: break-word;
    opacity: 0.95;
}
.orders-by-customer-section .orders-customer-box-meta {
    position: relative;
    z-index: 1;
    font-size: 0.8rem;
    opacity: 0.88;
    margin-top: 0.25rem;
}
/* Classic dashboard palette (cycles); walk-in uses neutral */
.orders-by-customer-section .orders-customer-box--info { background-color: #17a2b8; }
.orders-by-customer-section .orders-customer-box--success { background-color: #28a745; }
/* Amber: dark text for contrast (AdminLTE-style) */
.orders-by-customer-section .orders-customer-box--warning {
    background-color: #ffc107;
    color: #212529;
    text-shadow: none;
}
.orders-by-customer-section .orders-customer-box--warning .orders-customer-box-value {
    text-shadow: none;
}
.orders-by-customer-section .orders-customer-box--danger { background-color: #dc3545; }
.orders-by-customer-section .orders-customer-box--secondary { background-color: #6c757d; }

/* Bank payment tooltip (orders/all) */
.tooltip.bank-payment-tooltip .tooltip-inner {
    background: #000000;
    color: #ffffff;
    border-radius: 10px;
    padding: 0.5rem 0.7rem;
    font-weight: 500;
    font-size: 0.78rem;
    box-shadow: 0 10px 22px rgba(16, 24, 40, 0.24);
    white-space: pre-line;
}
.tooltip.bank-payment-tooltip.bs-tooltip-top .arrow::before {
    border-top-color: #000000;
}
.tooltip.bank-payment-tooltip.bs-tooltip-bottom .arrow::before {
    border-bottom-color: #000000;
}
.tooltip.bank-payment-tooltip.bs-tooltip-left .arrow::before {
    border-left-color: #000000;
}
.tooltip.bank-payment-tooltip.bs-tooltip-right .arrow::before {
    border-right-color: #000000;
}
</style>
@endsection

@section('container')
<div class="container-fluid">
    <div class="row">
        <div class="col-lg-12">
            @if (session()->has('success'))
                <div class="alert text-white bg-success" role="alert">
                    <div class="iq-alert-text">{{ session('success') }}</div>
                    <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                    <i class="ri-close-line"></i>
                    </button>
                </div>
            @endif
            @if (session()->has('error'))
                <div class="alert text-white bg-danger" role="alert">
                    <div class="iq-alert-text">{{ session('error') }}</div>
                    <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                    <i class="ri-close-line"></i>
                    </button>
                </div>
            @endif
            @if (!empty($debugSql))
                <div class="alert alert-secondary mb-3" role="alert">
                    <strong>Debug SQL (add ?debug_sql=1 to URL):</strong>
                    <pre class="mb-0 mt-2 small text-dark" style="white-space: pre-wrap; word-break: break-all; max-height: 200px; overflow: auto;">{{ $debugSql }}</pre>
                </div>
            @endif
            <div class="d-flex flex-wrap align-items-center justify-content-between mb-4">
                <div>
                    <h4 class="mb-3">Orders List</h4>
                </div>
                <div>
                    <a href="{{ route('order.index') }}" class="btn btn-danger add-list" title="Clear all filters and search"><i class="las la-trash mr-3"></i>Clear filters</a>
                </div>
            </div>
        </div>

        @php
            $dateRange = $dateRange ?? [];
            $dateFilter = $dateRange['date_filter'] ?? 'today';
        @endphp
        <!-- Filter Section (same UI as reports/sales/summary) -->
        <div class="col-lg-12 mb-3">
            <div class="card report-filter-card border-primary shadow-sm">
                <div class="card-header border-0 py-2">
                    <h6 class="mb-0 text-primary"><i class="ri-filter-3-line mr-1"></i> Filters</h6>
                </div>
                <div class="card-body pt-0">
                    <form action="{{ route('order.index') }}" method="get" id="orders-filter-form">
                        <input type="hidden" name="row" value="{{ request('row', '50') }}">
                        <input type="hidden" name="search" value="{{ request('search') }}">
                        {{-- Row 1: Date filter, Customer, Product, Invoice No --}}
                        <div class="row align-items-end mb-3">
                            <div class="col-md-3 mb-2 mb-md-0">
                                <label for="date_filter" class="form-label">Date Filter</label>
                                <select class="form-control" name="date_filter" id="date_filter" onchange="toggleOrderCustomDates()">
                                    <option value="all" {{ $dateFilter == 'all' ? 'selected' : '' }}>All</option>
                                    <option value="today" {{ $dateFilter == 'today' ? 'selected' : '' }}>Today</option>
                                    <option value="yesterday" {{ $dateFilter == 'yesterday' ? 'selected' : '' }}>Yesterday</option>
                                    <option value="this_week" {{ $dateFilter == 'this_week' ? 'selected' : '' }}>This Week</option>
                                    <option value="last_week" {{ $dateFilter == 'last_week' ? 'selected' : '' }}>Last Week</option>
                                    <option value="this_month" {{ $dateFilter == 'this_month' ? 'selected' : '' }}>This Month</option>
                                    <option value="last_month" {{ $dateFilter == 'last_month' ? 'selected' : '' }}>Last Month</option>
                                    <option value="this_year" {{ $dateFilter == 'this_year' ? 'selected' : '' }}>This Year</option>
                                    <option value="last_year" {{ $dateFilter == 'last_year' ? 'selected' : '' }}>Last Year</option>
                                    <option value="custom" {{ $dateFilter == 'custom' ? 'selected' : '' }}>Custom Range</option>
                                </select>
                            </div>
                            <div class="col-md-3 mb-2 mb-md-0">
                                <label for="customer_id" class="form-label">Customer</label>
                                <select name="customer_id" id="customer_id" class="form-control customer-select" style="width: 100%;">
                                    <option value="">— All —</option>
                                    @foreach ($customers ?? [] as $customer)
                                        <option value="{{ $customer->id }}" {{ request('customer_id') == $customer->id ? 'selected' : '' }}>{{ $customer->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-3 mb-2 mb-md-0">
                                <label for="product_id" class="form-label">Product</label>
                                <select name="product_id" id="product_id" class="form-control product-filter-select" style="width: 100%;">
                                    <option value="">— All —</option>
                                    @if (!empty($selectedProduct))
                                        <option value="{{ $selectedProduct->id }}" selected>
                                            {{ ($selectedProduct->product_code ? $selectedProduct->product_code . ' - ' : '') . ($selectedProduct->product_name ?? ('Product #' . $selectedProduct->id)) }}
                                        </option>
                                    @endif
                                </select>
                            </div>
                            <div class="col-md-3 mb-2 mb-md-0">
                                <label for="invoice_no" class="form-label">Invoice No</label>
                                <input type="text" class="form-control" name="invoice_no" id="invoice_no" placeholder="Partial match" value="{{ request('invoice_no') }}">
                            </div>
                        </div>
                        {{-- Row 2: Start/End (when custom), Payment type, Min total, Max total, Filter buttons --}}
                        <div class="row align-items-end">
                            <div class="col-md-2 mb-2 mb-md-0" id="order_start_date_group" style="display: {{ $dateFilter == 'custom' ? 'block' : 'none' }};">
                                <label for="start_date" class="form-label">Start Date</label>
                                <input type="date" class="form-control" name="start_date" id="start_date" value="{{ $dateRange['start_date'] ?? '' }}">
                            </div>
                            <div class="col-md-2 mb-2 mb-md-0" id="order_end_date_group" style="display: {{ $dateFilter == 'custom' ? 'block' : 'none' }};">
                                <label for="end_date" class="form-label">End Date</label>
                                <input type="date" class="form-control" name="end_date" id="end_date" value="{{ $dateRange['end_date'] ?? '' }}">
                            </div>
                            <div class="col-md-2 mb-2 mb-md-0">
                                <label for="payment_type" class="form-label">Payment Type</label>
                                <select class="form-control" name="payment_type" id="payment_type" style="width: 100%;">
                                    <option value="">— All —</option>
                                    <option value="Credit" {{ request('payment_type') === 'Credit' ? 'selected' : '' }}>Credit</option>
                                    <option value="Cash" {{ request('payment_type') === 'Cash' ? 'selected' : '' }}>Cash</option>
                                    <option value="Bank" {{ request('payment_type') === 'Bank' ? 'selected' : '' }}>Bank</option>
                                </select>
                            </div>
                            <div class="col-md-2 mb-2 mb-md-0">
                                <label for="total_min" class="form-label">Min Total</label>
                                <input type="number" step="0.01" min="0" class="form-control" name="total_min" id="total_min" placeholder="0" value="{{ request('total_min') }}">
                            </div>
                            <div class="col-md-2 mb-2 mb-md-0">
                                <label for="total_max" class="form-label">Max Total</label>
                                <input type="number" step="0.01" min="0" class="form-control" name="total_max" id="total_max" placeholder="—" value="{{ request('total_max') }}">
                            </div>
                            <div class="col-md-2 mb-2 mb-md-0 d-flex align-items-end flex-wrap">
                                <button type="submit" class="btn btn-primary px-3 py-2 mr-2 mb-2 mb-md-0">
                                    <i class="ri-search-line mr-1"></i> Filter
                                </button>
                                <a href="{{ route('order.index') }}" class="btn btn-outline-secondary px-3 py-2" title="Clear all filters">Clear</a>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        {{-- Row + Search in default place (below filter card) --}}
        <div class="col-lg-12 mb-3">
            <form action="{{ route('order.index') }}" method="get" id="orders-filter-form">
                @foreach (request()->except(['row', 'search', 'page']) as $key => $value)
                    @if (is_array($value))
                        @foreach ($value as $v)
                            <input type="hidden" name="{{ $key }}[]" value="{{ $v }}">
                        @endforeach
                    @else
                        <input type="hidden" name="{{ $key }}" value="{{ $value }}">
                    @endif
                @endforeach
                <div class="d-flex flex-wrap align-items-center justify-content-between">
                    <div class="form-group row mb-0">
                        <label for="row" class="col-sm-3 align-self-center col-form-label col-form-label-sm">Row:</label>
                        <div class="col-sm-9">
                            <select class="form-control form-control-sm" name="row" onchange="this.form.submit()">
                                <option value="10" {{ request('row') == '10' ? 'selected' : '' }}>10</option>
                                <option value="25" {{ request('row') == '25' ? 'selected' : '' }}>25</option>
                                <option value="50" {{ request('row', '50') == '50' ? 'selected' : '' }}>50</option>
                                <option value="100" {{ request('row') == '100' ? 'selected' : '' }}>100</option>
                            </select>
                        </div>
                    </div>
                    <div class="form-group row mb-0">
                        <label class="control-label col-sm-3 align-self-center col-form-label col-form-label-sm" for="search">Search:</label>
                        <div class="col-sm-8">
                            <div class="input-group input-group-sm flex-nowrap">
                                <input type="text" id="search" class="form-control" name="search" placeholder="Search order" value="{{ request('search') }}" style="min-width: 200px;">
                                <div class="input-group-append">
                                    <button type="submit" class="input-group-text bg-primary"><i class="las la-search"></i></button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </form>
        </div>

        {{-- Stats (after filters, same filter applies) --}}
        @php
            $stats = $orderStats ?? [
                'total_orders' => 0,
                'total_amount' => 0,
                'avg_order_value' => 0,
                'largest_order' => 0,
            ];
        @endphp
        <div class="col-lg-12 mb-3 orders-page-kpis">
            <div class="orders-kpi-strip">
                <div class="orders-kpi-col">
                    <div class="card orders-kpi-card orders-kpi-card--primary h-100">
                        <div class="card-body">
                            <div class="orders-kpi-top">
                                <div class="orders-kpi-icon-wrap" aria-hidden="true">
                                    <i class="fas fa-shopping-cart"></i>
                                </div>
                                <div class="orders-kpi-main">
                                    <div class="orders-kpi-value">{{ number_format($stats['total_orders']) }}</div>
                                </div>
                            </div>
                            <div class="orders-kpi-label">Total orders</div>
                            <div class="orders-kpi-meta">Matching current filters</div>
                        </div>
                        <div class="orders-kpi-accent" aria-hidden="true"></div>
                    </div>
                </div>
                <div class="orders-kpi-col">
                    <div class="card orders-kpi-card orders-kpi-card--success h-100">
                        <div class="card-body">
                            <div class="orders-kpi-top">
                                <div class="orders-kpi-icon-wrap" aria-hidden="true">
                                    <i class="fas fa-money-bill-wave"></i>
                                </div>
                                <div class="orders-kpi-main">
                                    <div class="orders-kpi-value">{{ number_format($stats['total_amount'], 2) }}</div>
                                </div>
                            </div>
                            <div class="orders-kpi-label">Total amount</div>
                            <div class="orders-kpi-meta">Sum of invoice totals</div>
                        </div>
                        <div class="orders-kpi-accent" aria-hidden="true"></div>
                    </div>
                </div>
                <div class="orders-kpi-col">
                    <div class="card orders-kpi-card orders-kpi-card--info h-100">
                        <div class="card-body">
                            <div class="orders-kpi-top">
                                <div class="orders-kpi-icon-wrap" aria-hidden="true">
                                    <i class="fas fa-balance-scale"></i>
                                </div>
                                <div class="orders-kpi-main">
                                    <div class="orders-kpi-value">{{ number_format($stats['avg_order_value'], 2) }}</div>
                                </div>
                            </div>
                            <div class="orders-kpi-label">Avg order value</div>
                            <div class="orders-kpi-meta">Total amount ÷ order count</div>
                        </div>
                        <div class="orders-kpi-accent" aria-hidden="true"></div>
                    </div>
                </div>
                <div class="orders-kpi-col">
                    <div class="card orders-kpi-card orders-kpi-card--warning h-100">
                        <div class="card-body">
                            <div class="orders-kpi-top">
                                <div class="orders-kpi-icon-wrap" aria-hidden="true">
                                    <i class="fas fa-arrow-up"></i>
                                </div>
                                <div class="orders-kpi-main">
                                    <div class="orders-kpi-value">{{ number_format($stats['largest_order'], 2) }}</div>
                                </div>
                            </div>
                            <div class="orders-kpi-label">Largest order</div>
                            <div class="orders-kpi-meta">Max invoice total in range</div>
                        </div>
                        <div class="orders-kpi-accent" aria-hidden="true"></div>
                    </div>
                </div>
            </div>
        </div>

        @php
            $customerOrderTotals = $customer_order_totals ?? collect();
        @endphp
        @if ($customerOrderTotals->isNotEmpty())
            <div class="col-lg-12 mb-3 orders-by-customer-section">
                <h6 class="mb-2 font-weight-semibold text-secondary">
                    <i class="ri-user-3-line mr-1"></i> Totals by customer
                </h6>
                <p class="small text-muted mb-2">Same filters as above; click a card to filter the list by that customer.</p>
                <div class="orders-customer-card-strip">
                    @foreach ($customerOrderTotals as $custRow)
                        @php
                            $cid = $custRow['customer_id'] ?? null;
                            $filterUrl = $cid !== null
                                ? route('order.index', array_merge(request()->except('page'), ['customer_id' => $cid]))
                                : null;
                            $palette = ['orders-customer-box--info', 'orders-customer-box--success', 'orders-customer-box--warning', 'orders-customer-box--danger'];
                            $boxClass = $cid === null ? 'orders-customer-box--secondary' : $palette[$loop->index % 4];
                            $bgIcons = ['fa-user', 'fa-shopping-cart', 'fa-store', 'fa-file-invoice'];
                            $bgIcon = $cid === null ? 'fa-walking' : $bgIcons[$loop->index % 4];
                        @endphp
                        <div class="orders-customer-card-col">
                            @if ($filterUrl)
                                <a href="{{ $filterUrl }}" class="orders-customer-box {{ $boxClass }} h-100">
                            @else
                                <div class="orders-customer-box {{ $boxClass }} h-100">
                            @endif
                                    <div class="orders-customer-box-body">
                                        <div class="orders-customer-box-bgicon" aria-hidden="true"><i class="fas {{ $bgIcon }}"></i></div>
                                        <div class="orders-customer-box-value">{{ number_format($custRow['total_sum'] ?? 0, 2) }}</div>
                                        <div class="orders-customer-box-title">{{ $custRow['name'] }}</div>
                                        <div class="orders-customer-box-meta">{{ number_format($custRow['order_count'] ?? 0) }} {{ ($custRow['order_count'] ?? 0) === 1 ? 'order' : 'orders' }}</div>
                                    </div>
                            @if ($filterUrl)
                                </a>
                            @else
                                </div>
                            @endif
                        </div>
                    @endforeach
                </div>
            </div>
        @endif

        <div class="col-lg-12">
            <div class="table-responsive rounded mb-3">
                <table class="table mb-0">
                    <thead class="bg-white text-uppercase">
                        <tr class="ligth ligth-data">
                            <th>No.</th>
                            <th>Invoice No</th>
                            <th>
                                @php
                                    $col = 'customer.name'; $label = 'Name';
                                    $dir = (request('sort') === $col && request('direction') === 'asc') ? 'desc' : 'asc';
                                    $url = route('order.index', array_merge(request()->query(), ['sort' => $col, 'direction' => $dir]));
                                @endphp
                                <a href="{{ $url }}" class="table-sortable-th">{{ $label }} @if(request('sort') === $col)<i class="ri-arrow-{{ request('direction') === 'asc' ? 'up' : 'down' }}-line ml-1"></i>@endif</a>
                            </th>
                            <th>
                                @php
                                    $col = 'order_date'; $label = 'Order date';
                                    $dir = (request('sort') === $col && request('direction') === 'asc') ? 'desc' : 'asc';
                                    $url = route('order.index', array_merge(request()->query(), ['sort' => $col, 'direction' => $dir]));
                                @endphp
                                <a href="{{ $url }}" class="table-sortable-th">{{ $label }} @if(request('sort') === $col)<i class="ri-arrow-{{ request('direction') === 'asc' ? 'up' : 'down' }}-line ml-1"></i>@endif</a>
                            </th>
                            <th>
                                @php
                                    $col = 'total'; $label = 'Total';
                                    $dir = (request('sort') === $col && request('direction') === 'asc') ? 'desc' : 'asc';
                                    $url = route('order.index', array_merge(request()->query(), ['sort' => $col, 'direction' => $dir]));
                                @endphp
                                <a href="{{ $url }}" class="table-sortable-th">{{ $label }} @if(request('sort') === $col)<i class="ri-arrow-{{ request('direction') === 'asc' ? 'up' : 'down' }}-line ml-1"></i>@endif</a>
                            </th>
                            <th>
                                @php
                                    $col = 'pay'; $label = 'Pay';
                                    $dir = (request('sort') === $col && request('direction') === 'asc') ? 'desc' : 'asc';
                                    $url = route('order.index', array_merge(request()->query(), ['sort' => $col, 'direction' => $dir]));
                                @endphp
                                <a href="{{ $url }}" class="table-sortable-th">{{ $label }} @if(request('sort') === $col)<i class="ri-arrow-{{ request('direction') === 'asc' ? 'up' : 'down' }}-line ml-1"></i>@endif</a>
                            </th>
                            <th>Payment</th>
                            <th>Status</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody class="ligth-body">
                        @foreach ($orders as $order)
                        <tr>
                            <td>{{ (($orders->currentPage() * $orders->perPage()) - $orders->perPage()) + $loop->iteration }}</td>
                            <td>{{ $order->invoice_no }}</td>
                            <td>{{ $order->customer?->name ?? $order->customer?->shopname ?? '—' }}</td>
                            <td>{{ $order->order_date }}</td>
                            <td>{{ number_format($order->total ?? 0, 2) }}</td>
                            <td>{{ strtolower($order->payment_status ?? '') === 'credit' ? number_format(0, 2) : number_format($order->pay ?? 0, 2) }}</td>
                            <td>
                                @php
                                    $paymentStatus = strtolower((string) ($order->payment_status ?? ''));
                                    $paymentBankBreakdown = $paymentBankBreakdowns[$order->id] ?? [];
                                    $showBankTooltip = in_array($paymentStatus, ['bank', 'cheque'], true) && !empty($paymentBankBreakdown);
                                    $bankTooltipLines = collect($paymentBankBreakdown)->map(function ($row) {
                                        $name = $row['name'] ?? 'Bank';
                                        $amount = number_format((float) ($row['amount'] ?? 0), 2);
                                        return $name . ' (' . $amount . ')';
                                    })->all();
                                    $bankTooltipText = "Banks:\n" . implode("\n", $bankTooltipLines);
                                @endphp
                                @if($showBankTooltip)
                                    <span
                                        class="bank-payment-pill"
                                        data-toggle="tooltip"
                                        data-placement="right"
                                        title="{{ $bankTooltipText }}"
                                    >
                                        {{ $order->payment_status }}
                                        <i class="ri-bank-line ml-1"></i>
                                    </span>
                                @else
                                    {{ $order->payment_status }}
                                @endif
                            </td>
                            <td>
                                <span class="badge
                                    @if($order->order_status == 'complete')
                                        badge-success
                                    @elseif($order->order_status == 'pending')
                                        badge-danger
                                    @else
                                        badge-secondary
                                    @endif">
                                    {{ $order->order_status }}
                                </span>
                            </td>

                            <td>
                                <div class="d-flex align-items-center list-action">
                                    <a class="btn btn-sm btn-info mr-2" data-toggle="tooltip" data-placement="top" title="" data-original-title="Details" href="{{ route('order.orderDetails', $order->id) }}">
                                        Details
                                    </a>
                                    <a class="btn btn-sm btn-warning mr-2" data-toggle="tooltip" data-placement="top" title="" data-original-title="Edit Invoice" href="{{ route('order.edit', $order->id) }}">
                                        Edit
                                    </a>
                                    <div class="btn-group mr-2">
                                        <button type="button" class="btn btn-sm btn-success dropdown-toggle" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                                            Print
                                        </button>
                                        <div class="dropdown-menu">
                                            <a class="dropdown-item" href="{{ route('order.printA4', $order->id) }}" target="_blank">
                                                <i class="fas fa-file-alt"></i> A4 Invoice
                                            </a>
                                            <a class="dropdown-item" href="{{ route('order.printReceipt', $order->id) }}" target="_blank">
                                                <i class="fas fa-receipt"></i> Receipt (3.5")
                                            </a>
                                        </div>
                                    </div>
                                    <button type="button" class="btn btn-sm btn-danger mr-2 border-none" data-toggle="tooltip" data-placement="top" title="" data-original-title="Delete" onclick="showDeleteModal({{ $order->id }})">
                                        <i class="ri-delete-bin-line mr-0"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            {{ $orders->appends(request()->query())->links() }}
        </div>

    </div>
    <!-- Page end  -->
</div>

<!-- Delete Confirmation Modal -->
<div class="modal fade" id="deleteOrderModal" tabindex="-1" role="dialog" aria-labelledby="deleteOrderModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title" id="deleteOrderModalLabel">Confirm Order Deletion</h5>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <div class="alert alert-warning" role="alert">
                    <strong><i class="ri-alert-line"></i> Warning:</strong> This action cannot be undone. The following will happen:
                    <ul class="mb-0 mt-2">
                        <li><strong>Stock will be reversed</strong> - All products in this order will have their stock quantities restored</li>
                        <li><strong>Payments will be removed</strong> - All payment records attached to this order will be deleted</li>
                        <li><strong>Customer credit will be adjusted</strong> - Pending amounts and payment credits will be reversed</li>
                    </ul>
                </div>
                
                <div class="card">
                    <div class="card-body">
                        <h6 class="card-title mb-3">Order Information:</h6>
                        <div class="row">
                            <div class="col-md-6">
                                <table class="table table-sm table-borderless mb-0">
                                    <tr>
                                        <td width="50%"><strong>Invoice No:</strong></td>
                                        <td id="modal-invoice-no">-</td>
                                    </tr>
                                    <tr>
                                        <td><strong>Customer:</strong></td>
                                        <td id="modal-customer-name">-</td>
                                    </tr>
                                    <tr>
                                        <td><strong>Order Date:</strong></td>
                                        <td id="modal-order-date">-</td>
                                    </tr>
                                    <tr>
                                        <td><strong>Order Status:</strong></td>
                                        <td id="modal-order-status">-</td>
                                    </tr>
                                    <tr>
                                        <td><strong>Total Amount:</strong></td>
                                        <td id="modal-total">-</td>
                                    </tr>
                                    <tr>
                                        <td><strong>Paid Amount:</strong></td>
                                        <td id="modal-pay">-</td>
                                    </tr>
                                </table>
                            </div>
                            <div class="col-md-6">
                                <table class="table table-sm table-borderless mb-0">
                                    <tr>
                                        <td width="50%"><strong>Due Amount:</strong></td>
                                        <td id="modal-due">-</td>
                                    </tr>
                                    <tr>
                                        <td><strong>Number of Products:</strong></td>
                                        <td id="modal-total-products">-</td>
                                    </tr>
                                    <tr>
                                        <td><strong>Total Stock to Reverse:</strong></td>
                                        <td><span class="badge badge-info" id="modal-total-stock">-</span></td>
                                    </tr>
                                    <tr>
                                        <td><strong>Number of Payments:</strong></td>
                                        <td><span class="badge badge-warning" id="modal-total-payments">-</span></td>
                                    </tr>
                                    <tr>
                                        <td><strong>Total Payment Amount:</strong></td>
                                        <td><span class="badge badge-success" id="modal-total-payment-amount">-</span></td>
                                    </tr>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                <form id="deleteOrderForm" method="POST" style="display: inline;">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="btn btn-danger">
                        <i class="ri-delete-bin-line mr-1"></i> Delete Order
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
function showDeleteModal(orderId) {
    // Show loading state
    $('#deleteOrderModal').modal('show');
    $('#deleteOrderForm').attr('action', `/orders/${orderId}`);
    
    // Reset modal content
    $('#modal-invoice-no').text('Loading...');
    $('#modal-customer-name').text('Loading...');
    $('#modal-order-date').text('Loading...');
    $('#modal-order-status').text('Loading...');
    $('#modal-total').text('Loading...');
    $('#modal-pay').text('Loading...');
    $('#modal-due').text('Loading...');
    $('#modal-total-products').text('Loading...');
    $('#modal-total-stock').text('Loading...');
    $('#modal-total-payments').text('Loading...');
    $('#modal-total-payment-amount').text('Loading...');
    
    // Fetch order information
    $.ajax({
        url: `/orders/${orderId}/delete-info`,
        method: 'GET',
        success: function(response) {
            $('#modal-invoice-no').text(response.order.invoice_no || '-');
            $('#modal-customer-name').text(response.order.customer_name || '-');
            $('#modal-order-date').text(response.order.order_date || '-');
            $('#modal-order-status').html(`<span class="badge ${response.order.order_status === 'complete' ? 'badge-success' : response.order.order_status === 'pending' ? 'badge-danger' : 'badge-secondary'}">${response.order.order_status}</span>`);
            $('#modal-total').text(formatCurrency(response.order.total || 0));
            $('#modal-pay').text(formatCurrency(response.order.pay || 0));
            $('#modal-due').text(formatCurrency(response.order.due || 0));
            $('#modal-total-products').text(response.total_products || 0);
            $('#modal-total-stock').text(response.total_stock_to_reverse || 0);
            $('#modal-total-payments').text(response.total_payments || 0);
            $('#modal-total-payment-amount').text(formatCurrency(response.total_payment_amount || 0));
        },
        error: function(xhr) {
            alert('Failed to load order information. Please try again.');
            $('#deleteOrderModal').modal('hide');
        }
    });
}

function formatCurrency(amount) {
    return new Intl.NumberFormat('en-US', {
        minimumFractionDigits: 0,
        maximumFractionDigits: 0
    }).format(amount);
}
</script>

@endsection

@section('specificpagescripts')
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script>
    function toggleOrderCustomDates() {
        var v = document.getElementById('date_filter').value;
        var startGroup = document.getElementById('order_start_date_group');
        var endGroup = document.getElementById('order_end_date_group');
        if (startGroup) startGroup.style.display = v === 'custom' ? 'block' : 'none';
        if (endGroup) endGroup.style.display = v === 'custom' ? 'block' : 'none';
    }
    document.addEventListener('DOMContentLoaded', function() {
        toggleOrderCustomDates();
        $('.customer-select').select2({
            theme: 'bootstrap-5',
            placeholder: '— All —',
            allowClear: true,
            width: '100%'
        });

        $('[data-toggle="tooltip"]').not('.bank-payment-pill').tooltip();
        $('.bank-payment-pill').tooltip('dispose').tooltip({
            template: '<div class="tooltip bank-payment-tooltip" role="tooltip"><div class="arrow"></div><div class="tooltip-inner"></div></div>',
            container: 'body',
            html: false,
            placement: 'right',
            trigger: 'hover focus'
        });

        $('.product-filter-select').select2({
            theme: 'bootstrap-5',
            placeholder: '— All —',
            allowClear: true,
            width: '100%',
            minimumInputLength: 2,
            ajax: {
                url: '{{ route("api.products.search") }}',
                dataType: 'json',
                delay: 250,
                data: function (params) {
                    return {
                        q: params.term,
                        page: params.page || 1
                    };
                },
                processResults: function (data, params) {
                    params.page = params.page || 1;
                    return {
                        results: (data.results || []).map(function(item) {
                            return { id: item.id, text: item.text };
                        }),
                        pagination: data.pagination || { more: false }
                    };
                },
                cache: true
            }
        });
    });
</script>
@if(session('open_print_tab') && session('print_order_id'))
<script>
    (function() {
        // Get order_id from session (passed via PHP)
        const orderId = {{ session('print_order_id') }};
        
        // Open invoice download page in new tab
        const printUrl = '{{ route("order.invoiceDownload", ":id") }}'.replace(':id', orderId) + '?print=1';
        window.open(printUrl, '_blank');
    })();
</script>
@endif
@endsection
