@extends('dashboard.body.main')

@section('specificpagestyles')
<style>
/* Profit & Loss report — screen layout unchanged */
.reports-profit-loss .pl-statement { width: 100%; max-width: 100%; }
.reports-profit-loss .pl-table { width: 100%; max-width: 100%; font-size: 15px; color: #333; table-layout: fixed; }
.reports-profit-loss .pl-table td { vertical-align: middle; padding: 6px 8px; line-height: 1.4; }
.reports-profit-loss .pl-table td:first-child { width: 1%; white-space: normal; }
.reports-profit-loss .pl-table td.pl-amount { width: 140px; }
.reports-profit-loss .pl-table strong { font-size: 13px; }
.reports-profit-loss .pl-table td.pl-section-heading {
    padding-left: 0 !important;
    font-size: 15px;
    font-weight: 600;
    color: #1f2937;
    border-bottom: 1px solid #e5e7eb;
    padding-top: 12px !important;
    padding-bottom: 6px !important;
}
.reports-profit-loss .pl-table td.pl-indent { padding-left: 24px !important; }
.reports-profit-loss .pl-table td.pl-expense-category {
    padding-left: 50px !important;
    font-weight: 500;
    color: #374151;
}
.reports-profit-loss .pl-table td.pl-expense-detail-label {
    padding-left: 100px !important;
    font-size: 13px;
    color: #6b7280;
}
.reports-profit-loss .pl-table td.pl-major { padding-left: 0 !important; }
.reports-profit-loss .cursor-pointer { cursor: pointer; }
.reports-profit-loss .pl-expand-toggle:hover { color: #111; }
.reports-profit-loss .pl-chevron { transition: transform 0.2s; display: inline-block; width: 12px; margin-right: 6px; }
.reports-profit-loss .pl-expand-toggle.expanded .pl-chevron { transform: rotate(90deg); }
.reports-profit-loss .pl-category-row:hover td { background-color: #f8fafc; }
.reports-profit-loss .pl-amount { white-space: nowrap; font-variant-numeric: tabular-nums; min-width: 120px; padding-right: 10px; }
.reports-profit-loss .pl-rule { border-top-color: #e5e7eb !important; }
.reports-profit-loss .pl-net { font-size: 17px; padding-top: 6px !important; padding-left: 0 !important; }
.reports-profit-loss .pl-net-amount { font-size: 17px; }
@media print {
        /* Same pattern as cash-flow, revenue, supplier ledger: full-width print (see docs/REPORT_PRINT_FULL_WIDTH.md) */
        @page { margin: 8mm; size: auto; }
        html, body { width: 100% !important; margin: 0 !important; padding: 0 !important; }
        .iq-sidebar, .iq-top-navbar, .iq-footer,
        .report-filter-card, .d-print-none, .btn, .pl-report-header .btn { display: none !important; }
        .wrapper { display: block !important; width: 100% !important; }
        .content-page { padding: 0 !important; margin: 0 !important; width: 100% !important; max-width: none !important; }
        body, .wrapper { padding: 0 !important; margin: 0 !important; }
        body { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        .container-fluid { width: 100% !important; max-width: none !important; padding-left: 0 !important; padding-right: 0 !important; }
        .container-fluid .row { margin-left: 0 !important; margin-right: 0 !important; width: 100% !important; }
        .container-fluid .row .col-lg-12 { padding-left: 0 !important; padding-right: 0 !important; max-width: none !important; }
        .reports-profit-loss .card { width: 100% !important; max-width: none !important; border: 1px solid #dee2e6 !important; box-shadow: none !important; }
        .reports-profit-loss .card-body { width: 100% !important; max-width: none !important; padding: 8px !important; box-sizing: border-box !important; }
        .reports-profit-loss .pl-statement { width: 100% !important; max-width: none !important; border: none !important; padding: 0 !important; box-sizing: border-box !important; display: block !important; }
        .reports-profit-loss .pl-table { width: 100% !important; min-width: 100% !important; max-width: 100% !important; table-layout: fixed !important; box-sizing: border-box !important; display: table !important; }
        .reports-profit-loss .pl-expense-detail { display: table-row !important; }
        .reports-profit-loss .pl-table td:first-child { width: auto !important; min-width: 55% !important; }
        .reports-profit-loss .pl-table td.pl-expense-detail-label,
        .reports-profit-loss .pl-table .pl-expense-detail td:first-child {
            white-space: nowrap !important;
            word-break: keep-all !important;
            overflow-wrap: normal !important;
        }
        /* Section labels: Revenue, Cost of Goods Sold, Gross Profit, Operating Expenses, Net Profit — 5px padding-left on print */
        .reports-profit-loss .pl-table td.pl-section-heading { padding-left: 5px !important; }
        .reports-profit-loss .pl-table td.pl-major { padding-left: 5px !important; }
        .reports-profit-loss .pl-table td.pl-net { padding-left: 5px !important; }
        .pl-report-header { display: block !important; text-align: center !important; margin-bottom: 1rem !important; }
    }
</style>
@endsection

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
            <div class="card report-filter-card border-primary shadow-sm">
                <div class="card-header border-0 py-2">
                    <h6 class="mb-0 text-primary"><i class="ri-filter-3-line mr-1"></i> Filters</h6>
                </div>
                <div class="card-body pt-0">
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
                            <button type="submit" class="btn btn-primary btn-sm"><i class="ri-search-line mr-1"></i> Filter</button>
                            <button type="button" class="btn btn-outline-secondary btn-sm ml-2" onclick="window.print()"><i class="ri-printer-line mr-1"></i> Print</button>
                        </div>
                    </div>
                </form>
                </div>
            </div>
        </div>

        <div class="col-lg-12">
            <div class="card">
                <div class="card-header bg-primary text-white d-flex align-items-center">
                    <h5 class="mb-0">Profit & Loss Statement</h5>
                </div>
                <div class="card-body">
            <div class="pl-statement bg-white border rounded p-4">
                <table class="pl-table table table-borderless mb-0">
                    <tbody>
                        {{-- Revenue --}}
                        <tr><td colspan="2" class="pt-3 pl-section-heading"><strong>Revenue</strong></td></tr>
                        <tr><td class="pl-indent">Sales Revenue</td><td class="pl-amount text-right">{{ $fmt($revenue) }}</td></tr>
                        <tr><td class="pl-indent">Sales Returns</td><td class="pl-amount text-right">{{ $fmt(0) }}</td></tr>
                        <tr><td colspan="2" class="pl-rule border-top pt-2 pb-1"></td></tr>
                        <tr><td class="pl-indent"><strong>Net Revenue</strong></td><td class="pl-amount text-right"><strong>{{ $fmt($netRevenue) }}</strong></td></tr>

                        {{-- Cost of Goods Sold (from order_details.cost_per_unit only) --}}
                        <tr><td colspan="2" class="pt-4 pl-section-heading"><strong>Cost of Goods Sold</strong></td></tr>
                        <tr><td class="pl-indent">Cost of Goods Sold</td><td class="pl-amount text-right"><strong>{{ $fmt($cogs) }}</strong></td></tr>

                        {{-- Gross Profit --}}
                        <tr><td colspan="2" class="pl-rule border-top pt-3 pb-2"></td></tr>
                        <tr><td class="pl-major"><strong>Gross Profit</strong></td><td class="pl-amount text-right"><strong>{{ $fmt($grossProfit) }}</strong></td></tr>

                        {{-- Operating Expenses --}}
                        <tr><td colspan="2" class="pt-4 pl-section-heading"><strong>Operating Expenses</strong></td></tr>
                        @foreach($expensesByCategory ?? [] as $catIndex => $category)
                        <tr class="pl-category-row" data-category-id="pl-cat-{{ $catIndex }}">
                            <td class="pl-expense-category">
                                <span class="pl-expand-toggle cursor-pointer" data-target="pl-cat-{{ $catIndex }}" role="button" tabindex="0" aria-expanded="false" title="Click to expand/collapse">
                                    <i class="fas fa-chevron-right pl-chevron text-muted small"></i>
                                    {{ $category['name'] }}
                                </span>
                            </td>
                            <td class="pl-amount text-right">{{ $fmt($category['total']) }}</td>
                        </tr>
                        @foreach($category['lines'] ?? [] as $line)
                        <tr class="pl-expense-detail pl-detail-pl-cat-{{ $catIndex }}" style="display: none;">
                            <td class="pl-expense-detail-label">{{ $line['date'] }} — {{ $line['description'] }}</td>
                            <td class="pl-amount text-right">{{ $fmt($line['amount']) }}</td>
                        </tr>
                        @endforeach
                        @endforeach
                        @if(empty($expensesByCategory) || count($expensesByCategory) === 0)
                        <tr><td class="pl-indent text-muted">No expenses in period</td><td class="pl-amount text-right">{{ $fmt(0) }}</td></tr>
                        @endif
                        <tr><td colspan="2" class="pl-rule border-top pt-2 pb-1"></td></tr>
                        <tr><td class="pl-indent"><strong>Total Operating Expenses</strong></td><td class="pl-amount text-right"><strong>{{ $fmt($expenses) }}</strong></td></tr>

                        {{-- Net Profit --}}
                        <tr><td colspan="2" class="pl-rule border-top pt-3 pb-2"></td></tr>
                        <tr><td class="pl-net"><strong>Net Profit</strong></td><td class="pl-amount pl-net-amount text-right"><strong>{{ $fmt($netProfit) }}</strong></td></tr>
                    </tbody>
                </table>
                <p class="mb-0 mt-3 small text-muted text-right">Profit Margin: {{ number_format($profitMargin ?? 0, 2) }}%</p>
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

document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('.pl-expand-toggle').forEach(function(btn) {
        btn.addEventListener('click', function() {
            var target = this.getAttribute('data-target');
            var rows = document.querySelectorAll('.pl-detail-' + target);
            var expanded = this.getAttribute('aria-expanded') === 'true';
            rows.forEach(function(r) { r.style.display = expanded ? 'none' : 'table-row'; });
            this.setAttribute('aria-expanded', expanded ? 'false' : 'true');
            this.classList.toggle('expanded', !expanded);
        });
        btn.addEventListener('keydown', function(e) {
            if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); this.click(); }
        });
    });
});
</script>
@endsection
