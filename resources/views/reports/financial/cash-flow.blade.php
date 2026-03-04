@extends('dashboard.body.main')

@section('specificpagestyles')
<style>
    @media print {
        @page { margin: 8mm; size: auto; }
        html, body { width: 100% !important; margin: 0 !important; padding: 0 !important; }
        .iq-sidebar, .iq-top-navbar, .iq-footer,
        .report-filter-card, .d-print-none { display: none !important; }
        .wrapper { display: block !important; width: 100% !important; }
        .content-page { padding: 0 !important; margin: 0 !important; width: 100% !important; max-width: none !important; }
        body, .wrapper { padding: 0 !important; margin: 0 !important; }
        body { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        .container-fluid { width: 100% !important; max-width: none !important; padding-left: 0 !important; padding-right: 0 !important; }
        .container-fluid .row { margin-left: 0 !important; margin-right: 0 !important; width: 100% !important; }
        .container-fluid .row .col-lg-12 { padding-left: 0 !important; padding-right: 0 !important; max-width: none !important; }
        .sales-kpi-row-1 { display: flex !important; flex-wrap: nowrap !important; break-inside: avoid; }
        .sales-kpi-row-1 .col-md-4 { flex: 0 0 33.333333% !important; max-width: 33.333333% !important; padding: 0 6px !important; }
        .sales-report-kpi, .sales-report-kpi.card { border: 1px solid #dee2e6 !important; box-shadow: none !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        .sales-report-kpi .card-body, .sales-report-kpi .iq-icon-box-2 { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        .card { border: 1px solid #dee2e6 !important; box-shadow: none !important; }
        .card-header.bg-primary { background: #0d6efd !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        .text-success, .text-danger { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        .sales-orders-table-wrap { width: 100% !important; max-width: none !important; margin-left: 0 !important; margin-right: 0 !important; padding-left: 0 !important; padding-right: 0 !important; box-sizing: border-box !important; }
        .sales-orders-table-wrap .card { width: 100% !important; max-width: none !important; }
        .sales-orders-table-wrap .card-body { padding: 8px !important; width: 100% !important; }
        .sales-orders-table-wrap .table-responsive { width: 100% !important; max-width: none !important; overflow: visible !important; display: block !important; }
        #cashFlowReportTable { width: 100% !important; min-width: 100% !important; max-width: 100% !important; table-layout: fixed !important; box-sizing: border-box !important; display: table !important; }
        #cashFlowReportTable th, #cashFlowReportTable td { border: 1px solid #dee2e6 !important; box-sizing: border-box !important; }
        #cashFlowReportTable th:nth-child(1), #cashFlowReportTable td:nth-child(1) { width: 25% !important; }
        #cashFlowReportTable th:nth-child(2), #cashFlowReportTable td:nth-child(2) { width: 35% !important; }
        #cashFlowReportTable th:nth-child(3), #cashFlowReportTable td:nth-child(3) { width: 20% !important; }
        #cashFlowReportTable th:nth-child(4), #cashFlowReportTable td:nth-child(4) { width: 20% !important; }
    }
</style>
@endsection

@section('container')
<div class="container-fluid">
    <div class="row">
        <div class="col-lg-12">
            <div class="d-flex flex-wrap align-items-center justify-content-between mb-4">
                <div>
                    <h4 class="mb-3">Cash Flow Report</h4>
                    <p class="mb-0 text-muted">Actual money movement (ledger only). Debit = Inflow, Credit = Outflow (cash/bank accounts only). Excludes unpaid sales/purchases.</p>
                </div>
                <div class="d-print-none">
                    <a href="{{ route('reports.index') }}" class="btn btn-secondary"><i class="ri-arrow-left-line mr-1"></i> Back to Reports</a>
                </div>
            </div>
        </div>

        <div class="col-lg-12 mb-3 d-print-none">
            <div class="card report-filter-card border-primary shadow-sm">
                <div class="card-header border-0 py-2">
                    <h6 class="mb-0 text-primary"><i class="ri-filter-3-line mr-1"></i> Filters</h6>
                </div>
                <div class="card-body pt-0">
                    <form action="{{ route('reports.financial.cash-flow') }}" method="GET">
                        <div class="row align-items-end">
                            <div class="col-md-3">
                                <label for="date_filter" class="form-label">Date Filter</label>
                                <select class="form-control" name="date_filter" id="date_filter" onchange="toggleCustomDates()">
                                    <option value="today" {{ ($dateRange['date_filter'] ?? '') == 'today' ? 'selected' : '' }}>Today</option>
                                    <option value="yesterday" {{ ($dateRange['date_filter'] ?? '') == 'yesterday' ? 'selected' : '' }}>Yesterday</option>
                                    <option value="this_week" {{ ($dateRange['date_filter'] ?? '') == 'this_week' ? 'selected' : '' }}>This Week</option>
                                    <option value="last_week" {{ ($dateRange['date_filter'] ?? '') == 'last_week' ? 'selected' : '' }}>Last Week</option>
                                    <option value="this_month" {{ ($dateRange['date_filter'] ?? '') == 'this_month' ? 'selected' : '' }}>This Month</option>
                                    <option value="last_month" {{ ($dateRange['date_filter'] ?? '') == 'last_month' ? 'selected' : '' }}>Last Month</option>
                                    <option value="this_year" {{ ($dateRange['date_filter'] ?? '') == 'this_year' ? 'selected' : '' }}>This Year</option>
                                    <option value="last_year" {{ ($dateRange['date_filter'] ?? '') == 'last_year' ? 'selected' : '' }}>Last Year</option>
                                    <option value="custom" {{ ($dateRange['date_filter'] ?? '') == 'custom' ? 'selected' : '' }}>Custom Range</option>
                                </select>
                            </div>
                            <div class="col-md-3" id="start_date_group" style="display: {{ ($dateRange['date_filter'] ?? '') == 'custom' ? 'block' : 'none' }};">
                                <label for="start_date" class="form-label">Start Date</label>
                                <input type="date" class="form-control" name="start_date" id="start_date" value="{{ $dateRange['start_date'] ?? '' }}">
                            </div>
                            <div class="col-md-3" id="end_date_group" style="display: {{ ($dateRange['date_filter'] ?? '') == 'custom' ? 'block' : 'none' }};">
                                <label for="end_date" class="form-label">End Date</label>
                                <input type="date" class="form-control" name="end_date" id="end_date" value="{{ $dateRange['end_date'] ?? '' }}">
                            </div>
                            @if(auth()->user()->shop_id == null)
                            <div class="col-md-3">
                                <label for="shop_id" class="form-label">Shop</label>
                                <select class="form-control" name="shop_id" id="shop_id">
                                    <option value="all" {{ ($shopFilter['selected_shop_id'] ?? '') == 'all' ? 'selected' : '' }}>All Shops</option>
                                    @foreach($shopFilter['shops'] ?? [] as $shop)
                                    <option value="{{ $shop->id }}" {{ ($shopFilter['selected_shop_id'] ?? '') == $shop->id ? 'selected' : '' }}>{{ $shop->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            @endif
                            <div class="col-md-3">
                                <button type="submit" class="btn btn-primary"><i class="ri-search-line mr-1"></i> Filter</button>
                                <button type="button" class="btn btn-outline-secondary btn-sm ml-2" onclick="window.print()"><i class="ri-printer-line mr-1"></i> Print</button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-lg-12 mb-3">
            <div class="row sales-kpi-row-1">
                <div class="col-md-4 mb-3 mb-md-0">
                    <div class="card card-block card-stretch card-height shadow-sm sales-report-kpi border-success h-100">
                        <div class="card-body d-flex align-items-center">
                            <div class="icon iq-icon-box-2 bg-success-light d-flex align-items-center justify-content-center mr-3 flex-shrink-0">
                                <i class="ri-arrow-down-circle-line text-success" style="font-size: 1.75rem;"></i>
                            </div>
                            <div class="flex-grow-1 min-w-0">
                                <p class="text-muted mb-0 small font-weight-500">Total Inflow (Debit)</p>
                                <h4 class="mb-0 font-weight-bold text-success">{{ number_format($totalInflow ?? 0, 2) }}</h4>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-4 mb-3 mb-md-0">
                    <div class="card card-block card-stretch card-height shadow-sm sales-report-kpi border-danger h-100">
                        <div class="card-body d-flex align-items-center">
                            <div class="icon iq-icon-box-2 bg-danger-light d-flex align-items-center justify-content-center mr-3 flex-shrink-0">
                                <i class="ri-arrow-up-circle-line text-danger" style="font-size: 1.75rem;"></i>
                            </div>
                            <div class="flex-grow-1 min-w-0">
                                <p class="text-muted mb-0 small font-weight-500">Total Outflow (Credit)</p>
                                <h4 class="mb-0 font-weight-bold text-danger">{{ number_format($totalOutflow ?? 0, 2) }}</h4>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-4 mb-3 mb-md-0">
                    <div class="card card-block card-stretch card-height shadow-sm sales-report-kpi h-100">
                        <div class="card-body d-flex align-items-center">
                            <div class="icon iq-icon-box-2 bg-primary-light d-flex align-items-center justify-content-center mr-3 flex-shrink-0">
                                <i class="ri-exchange-line text-primary" style="font-size: 1.75rem;"></i>
                            </div>
                            <div class="flex-grow-1 min-w-0">
                                <p class="text-muted mb-0 small font-weight-500">Net Cash Flow</p>
                                <h4 class="mb-0 font-weight-bold {{ ($netCashFlow ?? 0) >= 0 ? 'text-success' : 'text-danger' }}">{{ number_format($netCashFlow ?? 0, 2) }}</h4>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-12 sales-orders-table-wrap">
            <div class="card">
                <div class="card-header bg-primary text-white d-flex align-items-center"><h5 class="mb-0">Cash Flow by Date & Account</h5></div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-striped" id="cashFlowReportTable">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Account</th>
                                    <th class="text-right">Inflow</th>
                                    <th class="text-right">Outflow</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($byDateAccount ?? [] as $row)
                                <tr>
                                    <td>{{ \Carbon\Carbon::parse($row->date)->format('d M Y') }}</td>
                                    <td>{{ $row->account_name ?? '—' }}</td>
                                    <td class="text-right text-success">{{ number_format($row->inflow ?? 0, 2) }}</td>
                                    <td class="text-right text-danger">{{ number_format($row->outflow ?? 0, 2) }}</td>
                                </tr>
                                @empty
                                <tr><td colspan="4" class="text-center">No data for the selected period.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<script>
function toggleCustomDates() {
    var f = document.getElementById('date_filter').value;
    document.getElementById('start_date_group').style.display = f === 'custom' ? 'block' : 'none';
    document.getElementById('end_date_group').style.display = f === 'custom' ? 'block' : 'none';
}
</script>
@endsection
