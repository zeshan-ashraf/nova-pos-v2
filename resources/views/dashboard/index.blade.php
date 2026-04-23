@extends('dashboard.body.main')

@section('specificpagestyles')
<style>
/* Reuse Super Admin dashboard visual container */
.sa-dashboard-page {
    --sa-page-bg: #f5f5f9;
    background: var(--sa-page-bg);
    margin: -0.5rem -1rem 0;
    padding: 1.25rem 1rem 2rem;
}
@media (min-width: 992px) {
    .sa-dashboard-page {
        margin: -0.5rem -1.5rem 0;
        padding: 1.5rem 1.5rem 2.5rem;
    }
}
#dashboard-filter-form .form-control {
    width: 100%;
    max-width: 100%;
    box-sizing: border-box;
}
#dashboard-filter-form .form-group {
    min-width: 0;
    margin-bottom: 0.5rem;
}
.dashboard-filter-card.card-stretch,
.dashboard-filter-card.card-height,
.dashboard-filter-card .card-body {
    height: auto !important;
    min-height: 0 !important;
}
.dashboard-filter-card {
    overflow: visible !important;
}

/* Match super-admin filter-card look */
.sa-dashboard-page .dashboard-filter-card.report-filter-card {
    background: #fff !important;
    border: 1px solid rgba(13, 110, 253, 0.35) !important;
    border-left: 3px solid #0d6efd !important;
    border-right: 1px solid rgba(13, 110, 253, 0.35) !important;
    box-shadow: 0 0.125rem 0.5rem rgba(67, 89, 113, 0.12) !important;
}
.sa-dashboard-page .report-filter-card .card-header {
    background: #fff !important;
    border: 0;
    padding-top: 0.5rem;
    padding-bottom: 0.5rem;
}
.sa-dashboard-page .report-filter-card .card-body {
    background: #fff !important;
    padding-top: 0;
}

/* Match custom.css navbar treatment used by super-admin route */
.iq-top-navbar .navbar .d-flex.align-items-center {
    flex: 1 1 auto !important;
    min-width: 0;
}
.navbar-quick-link {
    padding: 0.4rem 0.75rem !important;
    font-size: 12px !important;
    white-space: nowrap;
    border-radius: 0.25rem;
    font-weight: bold;
    color: #000 !important;
}
.navbar-list li > a {
    font-size: 12px !important;
    line-height: 12px !important;
}
@media (max-width: 991.98px) {
    #dashboard-filter-form .btn {
        width: 100%;
        margin-right: 0 !important;
        margin-bottom: 0.5rem;
    }
}
</style>
@endsection

@section('container')
<div class="sa-dashboard-page">
<div class="container-fluid px-2 px-lg-3">
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
        </div>
        <div class="col-lg-12 mb-3">
            <div class="card card-block card-stretch card-height dashboard-filter-card report-filter-card border-primary shadow-sm">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h6 class="mb-0 text-primary"><i class="ri-filter-3-line mr-1"></i> Filters</h6>
                </div>
                <div class="card-body">
                    <form method="GET" action="{{ route('dashboard') }}" id="dashboard-filter-form">
                        <div class="form-row align-items-end">
                            <div class="form-group col-md-3">
                                <label class="mb-1" for="date_filter">Date Filter</label>
                                <select class="form-control" name="date_filter" id="date_filter" onchange="toggleDashboardCustomDates()">
                                    <option value="today" {{ ($dateRange['date_filter'] ?? 'today') === 'today' ? 'selected' : '' }}>Today</option>
                                    <option value="yesterday" {{ ($dateRange['date_filter'] ?? '') === 'yesterday' ? 'selected' : '' }}>Yesterday</option>
                                    <option value="this_week" {{ ($dateRange['date_filter'] ?? '') === 'this_week' ? 'selected' : '' }}>This Week</option>
                                    <option value="last_week" {{ ($dateRange['date_filter'] ?? '') === 'last_week' ? 'selected' : '' }}>Last Week</option>
                                    <option value="this_month" {{ ($dateRange['date_filter'] ?? '') === 'this_month' ? 'selected' : '' }}>This Month</option>
                                    <option value="last_month" {{ ($dateRange['date_filter'] ?? '') === 'last_month' ? 'selected' : '' }}>Last Month</option>
                                    <option value="this_year" {{ ($dateRange['date_filter'] ?? '') === 'this_year' ? 'selected' : '' }}>This Year</option>
                                    <option value="last_year" {{ ($dateRange['date_filter'] ?? '') === 'last_year' ? 'selected' : '' }}>Last Year</option>
                                    <option value="all_time" {{ ($dateRange['date_filter'] ?? '') === 'all_time' ? 'selected' : '' }}>All Time</option>
                                    <option value="custom" {{ ($dateRange['date_filter'] ?? '') === 'custom' ? 'selected' : '' }}>Custom</option>
                                </select>
                            </div>
                            <div class="form-group col-md-3" id="dashboard_start_date_group" style="display: none;">
                                <label class="mb-1" for="start_date">Start Date</label>
                                <input type="date" class="form-control" id="start_date" name="start_date" value="{{ $dateRange['start_date'] ?? '' }}">
                            </div>
                            <div class="form-group col-md-3" id="dashboard_end_date_group" style="display: none;">
                                <label class="mb-1" for="end_date">End Date</label>
                                <input type="date" class="form-control" id="end_date" name="end_date" value="{{ $dateRange['end_date'] ?? '' }}">
                            </div>
                            <div class="form-group col-md-3">
                                <button type="submit" class="btn btn-primary mr-2"><i class="ri-search-line mr-1"></i> Filter</button>
                                <a href="{{ route('dashboard') }}" class="btn btn-light">Clear</a>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        <div class="col-lg-4">
            <div class="card card-transparent card-block card-stretch card-height border-none">
                <div class="card-body p-0 mt-lg-2 mt-0">
                    <h3 class="mb-3">Hi {{ auth()->user()->name }}, Good Morning</h3>
                    <p class="mb-0 mr-4">Your dashboard gives you views of key performance or business process.</p>
                </div>
            </div>
        </div>
        <div class="col-lg-8">
            <div class="row">
                <div class="col-lg-4 col-md-4">
                    <div class="card card-block card-stretch card-height">
                        <div class="card-body">
                            <div class="d-flex align-items-center mb-4 card-total-sale">
                                <div class="icon iq-icon-box-2 bg-info-light">
                                    <img src="../assets/images/product/1.png" class="img-fluid" alt="image">
                                </div>
                                <div>
                                    <p class="mb-2">Total Due</p>
                                    <h4>{{ number_format((float) ($customer_total_due ?? 0), 2) }}</h4>
                                </div>
                            </div>
                            <div class="iq-progress-bar mt-2">
                                <span class="bg-info iq-progress progress-1" data-percent="85">
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-lg-4 col-md-4">
                    <div class="card card-block card-stretch card-height">
                        <div class="card-body">
                            <div class="d-flex align-items-center mb-4 card-total-sale">
                                <div class="icon iq-icon-box-2 bg-danger-light">
                                    <img src="../assets/images/product/2.png" class="img-fluid" alt="image">
                                </div>
                                <div>
                                    <p class="mb-2">Total Sales</p>
                                    <h4>{{ number_format((float) ($total_sales ?? 0), 2) }}</h4>
                                </div>
                            </div>
                            <div class="iq-progress-bar mt-2">
                                <span class="bg-danger iq-progress progress-1" data-percent="70">
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-lg-4 col-md-4">
                    <div class="card card-block card-stretch card-height">
                        <div class="card-body">
                            <div class="d-flex align-items-center mb-4 card-total-sale">
                                <div class="icon iq-icon-box-2 bg-success-light">
                                    <img src="../assets/images/product/3.png" class="img-fluid" alt="image">
                                </div>
                                <div>
                                    <p class="mb-2">Profit</p>
                                    <h4>{{ number_format((float) ($profit ?? 0), 2) }}</h4>
                                </div>
                            </div>
                            <div class="iq-progress-bar mt-2">
                                <span class="bg-success iq-progress progress-1" data-percent="75">
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-lg-6">
            <div class="card card-block card-stretch card-height">
                <div class="card-header d-flex justify-content-between">
                    <div class="header-title">
                        <h4 class="card-title">Overview</h4>
                    </div>
                </div>
                <div class="card-body">
                    <div id="layout1-chart1"></div>
                </div>
            </div>
        </div>
        <div class="col-lg-6">
            <div class="card card-block card-stretch card-height">
                <div class="card-header d-flex align-items-center justify-content-between">
                    <div class="header-title">
                        <h4 class="card-title">Revenue Vs Cost</h4>
                    </div>
                    @php
                        $currentQuery = request()->query();
                        $dailyQuery = array_merge($currentQuery, ['group_by' => 'daily']);
                        $monthlyQuery = array_merge($currentQuery, ['group_by' => 'monthly']);
                        $selectedGroupBy = $revenue_vs_cost['group_by'] ?? 'daily';
                    @endphp
                    <div class="btn-group btn-group-sm" role="group" aria-label="Revenue chart grouping">
                        <a href="{{ route('dashboard', $dailyQuery) }}"
                           class="btn {{ $selectedGroupBy === 'daily' ? 'btn-primary' : 'btn-outline-primary' }}">
                            Daily
                        </a>
                        <a href="{{ route('dashboard', $monthlyQuery) }}"
                           class="btn {{ $selectedGroupBy === 'monthly' ? 'btn-primary' : 'btn-outline-primary' }}">
                            Monthly
                        </a>
                    </div>
                </div>
                <div class="card-body">
                    <div id="layout1-chart-2-revenue-cost" style="min-height: 360px;"></div>
                </div>
            </div>
        </div>

        <div class="col-lg-8">
            <div class="card card-block card-stretch card-height">
                <div class="card-header d-flex align-items-center justify-content-between">
                    <div class="header-title">
                        <h4 class="card-title">Top Products</h4>
                    </div>
                    <div class="card-header-toolbar d-flex align-items-center">
                        <div class="dropdown">
                            <span class="dropdown-toggle dropdown-bg btn" id="dropdownMenuButton006"
                                data-toggle="dropdown">
                                This Month<i class="ri-arrow-down-s-line ml-1"></i>
                            </span>
                            <div class="dropdown-menu dropdown-menu-right shadow-none"
                                aria-labelledby="dropdownMenuButton006">
                                <a class="dropdown-item" href="#">Year</a>
                                <a class="dropdown-item" href="#">Month</a>
                                <a class="dropdown-item" href="#">Week</a>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="card-body">
                    <ul class="list-unstyled row top-product mb-0">
                    @foreach ($products as $product)
                        <li class="col-lg-3">
                            <div class="card card-block card-stretch card-height mb-0">
                                <div class="card-body">
                                    <div class="bg-warning-light rounded">
                                        <img src="{{ $product->product_image ? asset('storage/products/'.$product->product_image) : asset('assets/images/product/default.webp') }}" class="style-img img-fluid m-auto p-3" alt="image">
                                    </div>
                                    <div class="style-text text-left mt-3">
                                        <h5 class="mb-1">{{ $product->product_name }}</h5>
                                        <p class="mb-0">{{ $product->product_store }} Item</p>
                                    </div>
                                </div>
                            </div>
                        </li>
                    @endforeach
                    </ul>
                </div>
            </div>
        </div>
        <div class="col-lg-4">
            <div class="card card-transparent card-block card-stretch mb-4">
                <div class="card-header d-flex align-items-center justify-content-between p-0">
                    <div class="header-title">
                        <h4 class="card-title mb-0">New Products</h4>
                    </div>
                    <div class="card-header-toolbar d-flex align-items-center">
                        <div><a href="#" class="btn btn-primary view-btn font-size-14">View All</a></div>
                    </div>
                </div>
            </div>
            @foreach ($new_products as $product)
            <div class="card card-block card-stretch card-height-helf">
                <div class="card-body card-item-right">
                    <div class="d-flex align-items-top">
                        <div class="bg-warning-light rounded">
                            <img src="../assets/images/product/04.png" class="style-img img-fluid m-auto" alt="image">
                        </div>
                        <div class="style-text text-left">
                            <h5 class="mb-2">{{ $product->product_name }}</h5>
                            <p class="mb-2">Stock : {{ $product->product_store }}</p>
                            <p class="mb-0">Price : {{ $product->selling_price }}</p>
                        </div>
                    </div>
                </div>
            </div>
            @endforeach
        </div>
    </div>
    <!-- Page end  -->
</div>
</div>
@endsection

@section('specificpagescripts')
<!-- Table Treeview JavaScript -->
<script src="{{ asset('assets/js/table-treeview.js') }}"></script>
<!-- Chart Custom JavaScript -->
<script src="{{ asset('assets/js/customizer.js') }}"></script>
<script>
function toggleDashboardCustomDates() {
    var v = document.getElementById('date_filter').value;
    var startGroup = document.getElementById('dashboard_start_date_group');
    var endGroup = document.getElementById('dashboard_end_date_group');
    if (startGroup) startGroup.style.display = v === 'custom' ? 'block' : 'none';
    if (endGroup) endGroup.style.display = v === 'custom' ? 'block' : 'none';
}
document.addEventListener('DOMContentLoaded', toggleDashboardCustomDates);

document.addEventListener('DOMContentLoaded', function() {
    @php
        $ordersOverviewChartData = $orders_overview ?? [
            'labels' => [],
            'orders' => [],
        ];
    @endphp
    var overviewData = @json($ordersOverviewChartData);
    var overviewEl = document.querySelector('#layout1-chart1');
    if (overviewEl && typeof ApexCharts !== 'undefined') {
        var overviewLabels = Array.isArray(overviewData.labels) ? overviewData.labels : [];
        var overviewOrders = Array.isArray(overviewData.orders) ? overviewData.orders : [];
        var overviewOptions = {
            series: [{ name: 'Orders', data: overviewOrders }],
            chart: {
                type: 'area',
                height: 360,
                toolbar: { show: false }
            },
            stroke: {
                curve: 'smooth',
                width: 3
            },
            dataLabels: { enabled: false },
            colors: ['#5A8DEE'],
            fill: {
                type: 'gradient',
                gradient: {
                    shadeIntensity: 1,
                    opacityFrom: 0.35,
                    opacityTo: 0.08,
                    stops: [0, 100]
                }
            },
            xaxis: { categories: overviewLabels },
            yaxis: {
                labels: {
                    formatter: function(v) { return Math.round(Number(v || 0)).toLocaleString(); }
                }
            },
            tooltip: {
                y: { formatter: function(v) { return Math.round(Number(v || 0)).toLocaleString(); } }
            },
            noData: { text: 'No orders for selected date range' },
            grid: { borderColor: '#f1f1f1' }
        };
        new ApexCharts(overviewEl, overviewOptions).render();
    }

    var el = document.querySelector('#layout1-chart-2-revenue-cost');
    if (!el || typeof ApexCharts === 'undefined') return;

    @php
        $revenueVsCostChartData = $revenue_vs_cost ?? [
            'labels' => [],
            'revenue' => [],
            'cost' => [],
            'profit' => [],
        ];
    @endphp
    var chartData = @json($revenueVsCostChartData);
    var labels = Array.isArray(chartData.labels) ? chartData.labels : [];
    var revenue = Array.isArray(chartData.revenue) ? chartData.revenue : [];
    var cost = Array.isArray(chartData.cost) ? chartData.cost : [];
    var profit = Array.isArray(chartData.profit) ? chartData.profit : [];

    var options = {
        series: [
            { name: 'Revenue', data: revenue },
            { name: 'Cost', data: cost },
            { name: 'Profit', data: profit }
        ],
        chart: {
            type: 'line',
            height: 360,
            toolbar: { show: false }
        },
        stroke: {
            curve: 'smooth',
            width: 3
        },
        colors: ['#28a745', '#dc3545', '#0d6efd'],
        xaxis: {
            categories: labels
        },
        yaxis: {
            labels: {
                formatter: function(v) { return Number(v || 0).toLocaleString(); }
            }
        },
        tooltip: {
            y: {
                formatter: function(v) { return Number(v || 0).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 }); }
            }
        },
        noData: {
            text: 'No data for selected date range'
        },
        grid: {
            borderColor: '#f1f1f1'
        },
        legend: {
            position: 'top'
        }
    };

    var chart = new ApexCharts(el, options);
    chart.render();
});
</script>
@endsection
