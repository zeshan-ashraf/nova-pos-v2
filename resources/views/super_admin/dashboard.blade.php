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
                    <form class="form-row align-items-end">
                        <div class="form-group col-md-3">
                            <label for="sa_date_range" class="mb-1">Date Range</label>
                            <select id="sa_date_range" class="form-control">
                                <option value="today">Today</option>
                                <option value="this_week">This Week</option>
                                <option value="this_month" selected>This Month</option>
                                <option value="this_year">This Year</option>
                                <option value="custom">Custom Range</option>
                            </select>
                        </div>
                        <div class="form-group col-md-3">
                            <label for="sa_start_date" class="mb-1">Start Date</label>
                            <input type="date" id="sa_start_date" class="form-control" placeholder="YYYY-MM-DD">
                        </div>
                        <div class="form-group col-md-3">
                            <label for="sa_end_date" class="mb-1">End Date</label>
                            <input type="date" id="sa_end_date" class="form-control" placeholder="YYYY-MM-DD">
                        </div>
                        <div class="form-group col-md-3">
                            <label for="sa_shops" class="mb-1">Shops</label>
                            <select id="sa_shops" class="form-control" multiple>
                                {{-- Placeholder options; will be populated dynamically later --}}
                                <option>All Shops</option>
                                <option>Shop A</option>
                                <option>Shop B</option>
                                <option>Shop C</option>
                            </select>
                        </div>
                        <div class="form-group col-md-12 mt-2">
                            <button type="button" class="btn btn-primary">
                                Apply Filters
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    {{-- 2️⃣ Consolidated KPI Cards (All Shops Combined) --}}
    <div class="row sa-dashboard-section">
        <div class="col-12">
            <h6 class="text-uppercase mb-2">Consolidated KPIs (All Selected Shops)</h6>
        </div>
        <div class="col-md-4 col-xl-2 mb-3">
            <div class="card border h-100">
                <div class="card-body">
                    <div class="sa-dashboard-card-title">Total Sales</div>
                    <div class="sa-dashboard-metric-value">{{ number_format(0, 2) }}</div>
                    <div class="sa-dashboard-subtext">All shops, selected period</div>
                </div>
            </div>
        </div>
        <div class="col-md-4 col-xl-2 mb-3">
            <div class="card border h-100">
                <div class="card-body">
                    <div class="sa-dashboard-card-title">Total COGS</div>
                    <div class="sa-dashboard-metric-value">{{ number_format(0, 2) }}</div>
                    <div class="sa-dashboard-subtext">Cost of goods sold</div>
                </div>
            </div>
        </div>
        <div class="col-md-4 col-xl-2 mb-3">
            <div class="card border h-100">
                <div class="card-body">
                    <div class="sa-dashboard-card-title">Gross Profit</div>
                    <div class="sa-dashboard-metric-value">{{ number_format(0, 2) }}</div>
                    <div class="sa-dashboard-subtext">Sales − COGS</div>
                </div>
            </div>
        </div>
        <div class="col-md-4 col-xl-2 mb-3">
            <div class="card border h-100">
                <div class="card-body">
                    <div class="sa-dashboard-card-title">Total Discounts</div>
                    <div class="sa-dashboard-metric-value">{{ number_format(0, 2) }}</div>
                    <div class="sa-dashboard-subtext">Invoice + line discounts</div>
                </div>
            </div>
        </div>
        <div class="col-md-4 col-xl-2 mb-3">
            <div class="card border h-100">
                <div class="card-body">
                    <div class="sa-dashboard-card-title">Net Profit</div>
                    <div class="sa-dashboard-metric-value">{{ number_format(0, 2) }}</div>
                    <div class="sa-dashboard-subtext">After discounts</div>
                </div>
            </div>
        </div>
        <div class="col-md-4 col-xl-2 mb-3">
            <div class="card border h-100">
                <div class="card-body">
                    <div class="sa-dashboard-card-title">Profit Margin %</div>
                    <div class="sa-dashboard-metric-value">{{ number_format(0, 2) }}%</div>
                    <div class="sa-dashboard-subtext">Net profit ÷ sales</div>
                </div>
            </div>
        </div>
    </div>

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
                                    <th>
                                        Shop Name
                                    </th>
                                    <th>
                                        Sales
                                    </th>
                                    <th>
                                        COGS
                                    </th>
                                    <th>
                                        Gross Profit
                                    </th>
                                    <th>
                                        Invoice Discount
                                    </th>
                                    <th>
                                        Line Discount
                                    </th>
                                    <th>
                                        Net Profit
                                    </th>
                                    <th>
                                        Margin %
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($shops ?? [] as $shop)
                                    <tr>
                                        <td>{{ $shop->name ?? 'Shop Name' }}</td>
                                        <td>{{ number_format(0, 2) }}</td>
                                        <td>{{ number_format(0, 2) }}</td>
                                        <td>{{ number_format(0, 2) }}</td>
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

