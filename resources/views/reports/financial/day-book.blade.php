@extends('dashboard.body.main')

@section('container')
<div class="container-fluid">
    <div class="row">
        <div class="col-lg-12">
            <div class="d-flex flex-wrap align-items-center justify-content-between mb-4">
                <div>
                    <h4 class="mb-3">Day Book Report</h4>
                    <p class="mb-0 text-muted">All figures from the ledger (<code>account_transactions</code>): sale cash/bank in, expense cash/bank out, and customer payment cash/bank in. Filtered by transaction date. Read-only.</p>
                </div>
                <div>
                    <a href="{{ route('reports.index') }}" class="btn btn-secondary"><i class="ri-arrow-left-line mr-1"></i> Back to Reports</a>
                </div>
            </div>
        </div>

        <div class="col-lg-12 mb-3">
            <div class="card report-filter-card border-primary shadow-sm">
                <div class="card-header border-0 py-2">
                    <h6 class="mb-0 text-primary"><i class="ri-filter-3-line mr-1"></i> Filters</h6>
                </div>
                <div class="card-body pt-0">
                    <form action="{{ route('reports.financial.day-book') }}" method="GET" class="mb-0">
                        <div class="row align-items-end">
                            <div class="col-md-3 mb-2 mb-md-0">
                                <label for="start_date" class="form-label">From Date</label>
                                <input type="date" class="form-control" name="start_date" id="start_date" value="{{ $start_date }}">
                            </div>
                            <div class="col-md-3 mb-2 mb-md-0">
                                <label for="end_date" class="form-label">To Date</label>
                                <input type="date" class="form-control" name="end_date" id="end_date" value="{{ $end_date }}">
                            </div>
                            @if(auth()->user()->shop_id == null)
                            <div class="col-md-3 mb-2 mb-md-0">
                                <label for="shop_id" class="form-label">Shop</label>
                                <select class="form-control" name="shop_id" id="shop_id">
                                    <option value="all" {{ ($shopFilter['selected_shop_id'] ?? '') == 'all' ? 'selected' : '' }}>All Shops</option>
                                    @foreach($shopFilter['shops'] ?? [] as $shop)
                                    <option value="{{ $shop->id }}" {{ ($shopFilter['selected_shop_id'] ?? '') == $shop->id ? 'selected' : '' }}>{{ $shop->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            @endif
                            <div class="col-md-3 mb-2 mb-md-0 d-flex flex-wrap align-items-end">
                                <button type="submit" class="btn btn-primary mr-2"><i class="ri-search-line mr-1"></i> Apply Filter</button>
                                <a href="{{ route('reports.financial.day-book.export', request()->query()) }}" class="btn btn-outline-success">
                                    <i class="fas fa-file-excel mr-1"></i> Export Excel
                                </a>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-lg-12 mb-3">
            <div class="row">
                <div class="col-md-4 mb-3">
                    <div class="card border-success shadow-sm h-100">
                        <div class="card-body">
                            <p class="text-muted mb-1 small">Total In (Cash)</p>
                            <h5 class="mb-0 text-success text-right">{{ number_format($totals['amount_in_cash'] ?? 0, 2) }}</h5>
                        </div>
                    </div>
                </div>
                <div class="col-md-4 mb-3">
                    <div class="card border-danger shadow-sm h-100">
                        <div class="card-body">
                            <p class="text-muted mb-1 small">Total Out (Cash)</p>
                            <h5 class="mb-0 text-danger text-right">{{ number_format($totals['amount_out_cash'] ?? 0, 2) }}</h5>
                        </div>
                    </div>
                </div>
                <div class="col-md-4 mb-3">
                    <div class="card border-secondary shadow-sm h-100">
                        <div class="card-body">
                            <p class="text-muted mb-1 small">Net (In − Out)</p>
                            <h5 class="mb-0 text-right">{{ number_format(($totals['amount_in_cash'] ?? 0) - ($totals['amount_out_cash'] ?? 0), 2) }}</h5>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-12">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">Day Book Lines</h5>
                    <span class="text-muted small">{{ $paginator->total() }} record(s)</span>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-striped table-bordered mb-0">
                            <thead class="bg-light text-uppercase">
                                <tr>
                                    <th>Date</th>
                                    <th>Ref No</th>
                                    <th>Type</th>
                                    <th>Particular</th>
                                    <th class="text-right">Net Amount</th>
                                    <th>Title</th>
                                    <th class="text-right">Amount (In Cash)</th>
                                    <th class="text-right">Amount (Out Cash)</th>
                                    <th class="text-right">Amount In Online</th>
                                    <th class="text-right">Amount Out Online</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($paginator as $r)
                                <tr>
                                    <td>{{ \Carbon\Carbon::parse($r->date)->format('d M Y') }}</td>
                                    <td>{{ $r->ref_no }}</td>
                                    <td>{{ $r->type }}</td>
                                    <td>{{ $r->particular }}</td>
                                    <td class="text-right">{{ number_format((float) $r->net_amount, 2) }}</td>
                                    <td>{{ $r->title }}</td>
                                    <td class="text-right">{{ number_format((float) $r->amount_in_cash, 2) }}</td>
                                    <td class="text-right">{{ number_format((float) $r->amount_out_cash, 2) }}</td>
                                    <td class="text-right">{{ number_format((float) $r->amount_in_online, 2) }}</td>
                                    <td class="text-right">{{ number_format((float) $r->amount_out_online, 2) }}</td>
                                </tr>
                                @empty
                                <tr>
                                    <td colspan="10" class="text-center text-muted py-4">No records for the selected range.</td>
                                </tr>
                                @endforelse
                            </tbody>
                            @if($paginator->count() > 0)
                            <tfoot class="font-weight-bold bg-light">
                                <tr>
                                    <td colspan="4" class="text-right">Totals (all filtered)</td>
                                    <td class="text-right">{{ number_format($totals['net_amount'] ?? 0, 2) }}</td>
                                    <td></td>
                                    <td class="text-right">{{ number_format($totals['amount_in_cash'] ?? 0, 2) }}</td>
                                    <td class="text-right">{{ number_format($totals['amount_out_cash'] ?? 0, 2) }}</td>
                                    <td class="text-right">{{ number_format($totals['amount_in_online'] ?? 0, 2) }}</td>
                                    <td class="text-right">{{ number_format($totals['amount_out_online'] ?? 0, 2) }}</td>
                                </tr>
                            </tfoot>
                            @endif
                        </table>
                    </div>
                    @if($paginator->hasPages())
                    <div class="d-flex justify-content-end mt-3">
                        {{ $paginator->withQueryString()->links() }}
                    </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
