@extends('dashboard.body.main')

@section('container')
@php
    $fmt = function ($n) {
        $n = (float) ($n ?? 0);
        if ($n < 0) {
            return '(' . number_format(abs($n), 2) . ')';
        }
        return number_format($n, 2);
    };
    $revenue = (float) ($revenue ?? 0);
    $cogs = (float) ($cogs ?? 0);
    $expenses = (float) ($expenses ?? 0);
    $grossProfit = (float) ($grossProfit ?? 0);
    $netProfit = (float) ($netProfit ?? 0);
    $netRevenue = $revenue;
@endphp
<div class="container-fluid reports-profit-loss">
    <div class="row">
        <div class="col-lg-12">
            <div class="d-flex flex-wrap align-items-center justify-content-between mb-4 pl-report-header">
                <div>
                    <h4 class="mb-1 pl-report-title">Profit & Loss Report</h4>
                    <p class="mb-0 text-muted small pl-report-period">{{ $dateRange['start_date'] ?? '' }} to {{ $dateRange['end_date'] ?? '' }}</p>
                </div>
                <div>
                    <a href="{{ route('reports.financial.profit-loss-line-detail', request()->only(['date_filter', 'start_date', 'end_date', 'shop_id'])) }}" class="btn btn-outline-primary btn-sm mr-2">View line-level detail</a>
                    <a href="{{ route('reports.index') }}" class="btn btn-secondary btn-sm">Back to Reports</a>
                </div>
            </div>
        </div>

        <div class="col-lg-12 mb-4">
            <div class="border rounded p-3 bg-white">
                <form action="{{ route('reports.financial.profit-loss') }}" method="GET">
                    <div class="row align-items-end">
                        <div class="col-md-3">
                            <label for="date_filter" class="form-label small">Date Filter</label>
                            <select class="form-control form-control-sm" name="date_filter" id="date_filter" onchange="toggleCustomDates()">
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
                            <label for="start_date" class="form-label small">Start Date</label>
                            <input type="date" class="form-control form-control-sm" name="start_date" id="start_date" value="{{ $dateRange['start_date'] ?? '' }}">
                        </div>
                        <div class="col-md-3" id="end_date_group" style="display: {{ ($dateRange['date_filter'] ?? '') == 'custom' ? 'block' : 'none' }};">
                            <label for="end_date" class="form-label small">End Date</label>
                            <input type="date" class="form-control form-control-sm" name="end_date" id="end_date" value="{{ $dateRange['end_date'] ?? '' }}">
                        </div>
                        @if(auth()->user()->shop_id == null)
                        <div class="col-md-3">
                            <label for="shop_id" class="form-label small">Shop</label>
                            <select class="form-control form-control-sm" name="shop_id" id="shop_id">
                                <option value="all" {{ ($shopFilter['selected_shop_id'] ?? '') == 'all' ? 'selected' : '' }}>All Shops</option>
                                @foreach($shopFilter['shops'] ?? [] as $shop)
                                <option value="{{ $shop->id }}" {{ ($shopFilter['selected_shop_id'] ?? '') == $shop->id ? 'selected' : '' }}>{{ $shop->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        @endif
                        <div class="col-md-3">
                            <button type="submit" class="btn btn-primary btn-sm">Filter</button>
                            <button type="button" class="btn btn-outline-secondary btn-sm ml-2" onclick="window.print()">Print</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <div class="col-lg-12">
            <div class="pl-statement bg-white border rounded p-4">
                <table class="pl-table table table-borderless mb-0">
                    <tbody>
                        {{-- Revenue --}}
                        <tr><td colspan="2" class="pt-3"><strong>Revenue</strong></td></tr>
                        <tr><td class="pl-indent">Sales Revenue</td><td class="pl-amount text-right">{{ $fmt($revenue) }}</td></tr>
                        <tr><td class="pl-indent">Sales Returns</td><td class="pl-amount text-right">{{ $fmt(0) }}</td></tr>
                        <tr><td colspan="2" class="pl-rule border-top pt-2 pb-1"></td></tr>
                        <tr><td class="pl-indent"><strong>Net Revenue</strong></td><td class="pl-amount text-right"><strong>{{ $fmt($netRevenue) }}</strong></td></tr>

                        {{-- Cost of Goods Sold (from order_details.cost_per_unit only) --}}
                        <tr><td colspan="2" class="pt-4"><strong>Cost of Goods Sold</strong></td></tr>
                        <tr><td class="pl-indent">Cost of Goods Sold</td><td class="pl-amount text-right"><strong>{{ $fmt($cogs) }}</strong></td></tr>

                        {{-- Gross Profit --}}
                        <tr><td colspan="2" class="pl-rule border-top pt-3 pb-2"></td></tr>
                        <tr><td class="pl-major"><strong>Gross Profit</strong></td><td class="pl-amount text-right"><strong>{{ $fmt($grossProfit) }}</strong></td></tr>

                        {{-- Operating Expenses --}}
                        <tr><td colspan="2" class="pt-4"><strong>Operating Expenses</strong></td></tr>
                        <tr><td class="pl-indent">Total Operating Expenses</td><td class="pl-amount text-right">{{ $fmt($expenses) }}</td></tr>
                        <tr><td colspan="2" class="pl-rule border-top pt-2 pb-1"></td></tr>
                        <tr><td class="pl-indent"><strong>Total Operating Expenses</strong></td><td class="pl-amount text-right"><strong>{{ $fmt($expenses) }}</strong></td></tr>

                        {{-- Net Profit --}}
                        <tr><td colspan="2" class="pl-rule border-top pt-3 pb-2"></td></tr>
                        <tr><td class="pl-net"><strong>Net Profit</strong></td><td class="pl-amount pl-net-amount text-right"><strong>{{ $fmt($netProfit) }}</strong></td></tr>
                    </tbody>
                </table>
                <p class="mb-0 mt-3 small text-muted">Profit Margin: {{ number_format($profitMargin ?? 0, 2) }}%</p>
            </div>
        </div>
    </div>
</div>

<style>
/* Full width for report content */
.reports-profit-loss.container-fluid { max-width: 100%; }
.reports-profit-loss .row { max-width: 100%; }
.reports-profit-loss .col-lg-12 { max-width: 100%; }
.pl-statement { width: 100%; max-width: 100%; }
.pl-table { width: 100%; max-width: 100%; font-size: 16px; color: #333; table-layout: fixed; }
.pl-table td { vertical-align: middle; padding: 2px 0; line-height: 18px; }
.pl-table td:first-child { width: 1%; white-space: normal; }
.pl-table td.pl-amount { width: 140px; }
.pl-table strong { font-size: 13px; }
.pl-indent { padding-left: 24px !important; }
.pl-major { padding-left: 0; }
.pl-amount { white-space: nowrap; font-variant-numeric: tabular-nums; min-width: 120px; }
.pl-rule { border-top-color: #ddd !important; }
.pl-net { font-size: 18px; padding-top: 4px; }
.pl-net-amount { font-size: 18px; }
@media print {
    /* Full width: remove layout padding and use full page */
    body, .wrapper, .content-page, .container-fluid, .reports-profit-loss .row, .reports-profit-loss .col-lg-12 { width: 100% !important; max-width: 100% !important; padding-left: 0 !important; padding-right: 0 !important; margin: 0 !important; }
    .content-page { padding-top: 0 !important; }
    /* Hide non-print UI */
    .iq-sidebar, .iq-top-navbar, .btn, .border.rounded.p-3, form.border.rounded, #date_filter, #start_date_group, #end_date_group, .pl-report-header .btn { display: none !important; }
    /* Report header: center title and date, title 2px bigger */
    .pl-report-header { display: block !important; text-align: center !important; margin-bottom: 1rem !important; }
    .pl-report-title { font-size: calc(1em + 2px) !important; text-align: center !important; margin: 0 auto 0.25rem !important; }
    .pl-report-period { text-align: center !important; margin: 0 !important; }
    /* Statement and table full width */
    .pl-statement { width: 100% !important; max-width: 100% !important; border: none !important; box-shadow: none !important; padding: 0 !important; }
    .pl-table { width: 100% !important; max-width: 100% !important; table-layout: fixed !important; }
    /* Clean print colors */
    body { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
}
</style>
<script>
function toggleCustomDates() {
    var f = document.getElementById('date_filter').value;
    document.getElementById('start_date_group').style.display = f === 'custom' ? 'block' : 'none';
    document.getElementById('end_date_group').style.display = f === 'custom' ? 'block' : 'none';
}
</script>
@endsection
