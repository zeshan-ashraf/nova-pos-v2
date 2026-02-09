@extends('dashboard.body.main')

@section('container')
<div class="container-fluid">
    <div class="row">
        <div class="col-lg-12">
            <div class="d-flex flex-wrap align-items-center justify-content-between mb-4">
                <div>
                    <h4 class="mb-3">Revenue Report</h4>
                    <p class="mb-0 text-muted">Money earned from sales (invoiced). Revenue ≠ cash flow.</p>
                </div>
                <div>
                    <a href="{{ route('reports.index') }}" class="btn btn-secondary"><i class="ri-arrow-left-line mr-1"></i> Back to Reports</a>
                </div>
            </div>
        </div>

        <div class="col-lg-12 mb-3">
            <div class="card">
                <div class="card-body">
                    <form action="{{ route('reports.financial.revenue') }}" method="GET">
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
                                <button type="button" class="btn btn-info ml-2" onclick="window.print()"><i class="ri-printer-line mr-1"></i> Print</button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-lg-12 mb-3">
            <div class="card">
                <div class="card-body">
                    <p class="text-muted mb-1">Total Revenue (Gross)</p>
                    <h4 class="mb-0">{{ number_format($totalRevenue ?? 0, 2) }}</h4>
                </div>
            </div>
        </div>

        <div class="col-lg-12">
            <div class="card">
                <div class="card-header"><h5 class="mb-0">Revenue by Date</h5></div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th class="text-right">Revenue</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($byDate ?? [] as $row)
                                <tr>
                                    <td>{{ \Carbon\Carbon::parse($row->date)->format('d M Y') }}</td>
                                    <td class="text-right">{{ number_format($row->revenue ?? 0, 2) }}</td>
                                </tr>
                                @empty
                                <tr><td colspan="2" class="text-center">No data for the selected period.</td></tr>
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
