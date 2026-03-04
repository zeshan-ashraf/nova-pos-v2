@extends('dashboard.body.main')

@section('container')
<div class="container-fluid">
    <div class="row">
        <div class="col-lg-12">
            <div class="d-flex flex-wrap align-items-center justify-content-between mb-4">
                <div>
                    <h4 class="mb-1">Simple Cash Flow Report</h4>
                    <p class="mb-0 text-muted small">Inflow = Debit, Outflow = Credit. Cash and bank accounts only.</p>
                </div>
                <div class="d-print-none">
                    <a href="{{ route('reports.index') }}" class="btn btn-secondary btn-sm"><i class="ri-arrow-left-line mr-1"></i> Back to Reports</a>
                </div>
            </div>
        </div>

        <div class="col-lg-12 mb-3 d-print-none">
            <div class="card report-filter-card border-primary shadow-sm">
                <div class="card-header border-0 py-2">
                    <h6 class="mb-0 text-primary"><i class="ri-filter-3-line mr-1"></i> Filters</h6>
                </div>
                <div class="card-body pt-0">
                    <form action="{{ route('reports.cash-flow') }}" method="GET">
                        <div class="row align-items-end">
                            <div class="col-md-3">
                                <label for="date_from" class="form-label small">Date From</label>
                                <input type="date" class="form-control form-control-sm" name="date_from" id="date_from" value="{{ $dateFrom ?? '' }}">
                            </div>
                            <div class="col-md-3">
                                <label for="date_to" class="form-label small">Date To</label>
                                <input type="date" class="form-control form-control-sm" name="date_to" id="date_to" value="{{ $dateTo ?? '' }}">
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
                                <button type="submit" class="btn btn-primary btn-sm"><i class="ri-search-line mr-1"></i> Apply</button>
                                <button type="button" class="btn btn-outline-secondary btn-sm ml-2" onclick="window.print()"><i class="ri-printer-line mr-1"></i> Print</button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-lg-12">
            <div class="card">
                <div class="card-header bg-primary text-white"><h5 class="mb-0">Cash Flow</h5></div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-striped table-bordered mb-0" id="simpleCashFlowTable">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Account</th>
                                    <th class="text-right">Inflow</th>
                                    <th class="text-right">Outflow</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($rows ?? [] as $row)
                                <tr>
                                    <td>{{ \Carbon\Carbon::parse($row->transaction_date)->format('d M Y') }}</td>
                                    <td>{{ $row->account_name ?? '—' }}</td>
                                    <td class="text-right">{{ number_format((float)($row->inflow ?? 0), 2) }}</td>
                                    <td class="text-right">{{ number_format((float)($row->outflow ?? 0), 2) }}</td>
                                </tr>
                                @empty
                                <tr>
                                    <td colspan="4" class="text-center">No data for the selected period.</td>
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
