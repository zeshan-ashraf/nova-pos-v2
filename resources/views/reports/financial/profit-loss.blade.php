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
    $operatingProfit = $grossProfit - $expenses;
@endphp
<div class="container-fluid">
    <div class="row">
        <div class="col-lg-12">
            <div class="d-flex flex-wrap align-items-center justify-content-between mb-4">
                <div>
                    <h4 class="mb-1">Profit & Loss Report</h4>
                    <p class="mb-0 text-muted small">{{ $dateRange['start_date'] ?? '' }} to {{ $dateRange['end_date'] ?? '' }}</p>
                </div>
                <div>
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

                        {{-- Cost of Goods Sold --}}
                        <tr><td colspan="2" class="pt-4"><strong>Cost of Goods Sold</strong></td></tr>
                        <tr><td class="pl-indent">Opening Stock</td><td class="pl-amount text-right">{{ $fmt(0) }}</td></tr>
                        <tr><td class="pl-indent">Purchases</td><td class="pl-amount text-right">{{ $fmt($cogs) }}</td></tr>
                        <tr><td class="pl-indent">Purchase Returns</td><td class="pl-amount text-right">{{ $fmt(0) }}</td></tr>
                        <tr><td class="pl-indent">Closing Stock</td><td class="pl-amount text-right">{{ $fmt(0) }}</td></tr>
                        <tr><td colspan="2" class="pl-rule border-top pt-2 pb-1"></td></tr>
                        <tr><td class="pl-indent"><strong>Cost of Goods Sold</strong></td><td class="pl-amount text-right"><strong>{{ $fmt($cogs) }}</strong></td></tr>

                        {{-- Gross Profit --}}
                        <tr><td colspan="2" class="pl-rule border-top pt-3 pb-2"></td></tr>
                        <tr><td class="pl-major"><strong>Gross Profit</strong></td><td class="pl-amount text-right"><strong>{{ $fmt($grossProfit) }}</strong></td></tr>

                        {{-- Operating Expenses --}}
                        <tr><td colspan="2" class="pt-4"><strong>Operating Expenses</strong></td></tr>
                        <tr><td class="pl-indent">Total Operating Expenses</td><td class="pl-amount text-right">{{ $fmt($expenses) }}</td></tr>
                        <tr><td colspan="2" class="pl-rule border-top pt-2 pb-1"></td></tr>
                        <tr><td class="pl-indent"><strong>Total Operating Expenses</strong></td><td class="pl-amount text-right"><strong>{{ $fmt($expenses) }}</strong></td></tr>

                        {{-- Operating Profit --}}
                        <tr><td colspan="2" class="pl-rule border-top pt-3 pb-2"></td></tr>
                        <tr><td class="pl-major"><strong>Operating Profit</strong></td><td class="pl-amount text-right"><strong>{{ $fmt($operatingProfit) }}</strong></td></tr>

                        {{-- Other --}}
                        <tr><td class="pl-indent pt-2">Other Income</td><td class="pl-amount text-right pt-2">{{ $fmt(0) }}</td></tr>
                        <tr><td class="pl-indent">Other Expenses</td><td class="pl-amount text-right">{{ $fmt(0) }}</td></tr>

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
.pl-statement { max-width: 640px; }
.pl-table { font-size: 14px; color: #333; }
.pl-table td { vertical-align: middle; padding: 2px 0; }
.pl-indent { padding-left: 24px !important; }
.pl-major { padding-left: 0; }
.pl-amount { white-space: nowrap; font-variant-numeric: tabular-nums; min-width: 120px; }
.pl-rule { border-top-color: #ddd !important; }
.pl-net { font-size: 15px; padding-top: 4px; }
.pl-net-amount { font-size: 15px; }
@media print {
    .pl-statement { border: none !important; box-shadow: none !important; }
    .btn, .border.rounded.p-3 { display: none !important; }
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
