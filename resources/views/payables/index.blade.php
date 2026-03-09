@extends('dashboard.body.main')

@section('specificpagestyles')
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<link href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" rel="stylesheet" />
<style>
.sortable-th-link { color: #32BDEA !important; text-decoration: none; cursor: pointer; white-space: nowrap; font-weight: bold; }
.sortable-th-link:hover { color: #2a9fc7 !important; text-decoration: underline; }
.sortable-th-link i { color: #32BDEA !important; }
.payables-action-cell .btn { font-size: 0.9rem; }
.payables-action-cell .btn-icon { padding: 0.4rem 0.55rem; }
.payables-action-cell .btn-action-text { padding: 0.45rem 0.75rem; }
.table .payables-action-cell { text-align: center; vertical-align: middle; }
.payables-action-cell .d-flex { justify-content: center; }
/* Catchy backgrounds */
.payables-page .report-filter-card .card-header { background: transparent; border-bottom: 1px solid rgba(0,0,0,.08); }
.payables-page .card.payables-accounts-card .card-header { background: linear-gradient(135deg, #e8f5e9 0%, #c8e6c9 100%); color: #1b5e20; }
.payables-page .card.payables-transactions-card .card-header { background: linear-gradient(135deg, #fff3e0 0%, #ffe0b2 100%); color: #e65100; }
.payables-page .table thead.bg-white { background: linear-gradient(180deg, #f5f5f5 0%, #eeeeee 100%) !important; }
.payables-page .summary-kpi-card { border-left: 4px solid; }
.payables-page .summary-kpi-card.card-primary { border-left-color: #0d6efd; }
.payables-page .summary-kpi-card.card-warning { border-left-color: #ffc107; }
.payables-page .summary-kpi-card.card-success { border-left-color: #198754; }
.payables-page .summary-kpi-card.card-info { border-left-color: #0dcaf0; }
.payables-page .summary-kpi-card.card-danger { border-left-color: #dc3545; }
</style>
@endsection

@section('container')
<div class="container-fluid payables-page">
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

            {{-- Delete error modal: shown when delete was blocked because account has transactions --}}
            @if (session()->has('delete_error'))
            <div class="modal fade" id="payablesDeleteErrorModal" tabindex="-1" role="dialog" aria-labelledby="payablesDeleteErrorModalLabel" aria-hidden="true" data-show="1">
                <div class="modal-dialog modal-dialog-centered" role="document">
                    <div class="modal-content border-danger">
                        <div class="modal-header bg-danger text-white">
                            <h5 class="modal-title" id="payablesDeleteErrorModalLabel"><i class="ri-error-warning-line mr-2"></i>Cannot delete account</h5>
                            <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                                <span aria-hidden="true">&times;</span>
                            </button>
                        </div>
                        <div class="modal-body">
                            <p class="mb-0">{{ session('delete_error') }}</p>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-primary" data-dismiss="modal">OK</button>
                        </div>
                    </div>
                </div>
            </div>
            @endif

            <div class="d-flex flex-wrap align-items-center justify-content-between mb-4">
                <div>
                    <h4 class="mb-3">Payables</h4>
                    <p class="mb-0">Track money or services borrowed from people and repayments.</p>
                </div>
                <div>
                    <a href="{{ route('payables.create') }}" class="btn btn-primary add-list"><i class="fas fa-plus mr-3"></i>Add Payable Account</a>
                    <a href="{{ route('payables.index') }}" class="btn btn-danger add-list"><i class="las la-trash mr-3"></i>Clear Search</a>
                </div>
            </div>
        </div>

        {{-- Filter box: affects only the "All transactions" table below; does not affect payable accounts list --}}
        @php
            $txnDateFilter = $txnDateFilter ?? 'all';
            $txnDateRange = $txnDateRange ?? ['date_filter' => 'all', 'start_date' => '', 'end_date' => ''];
        @endphp
        <div class="col-lg-12 mb-3">
            <div class="card report-filter-card border-primary shadow-sm">
                <div class="card-header border-0 py-2">
                    <h6 class="mb-0 text-primary"><i class="ri-filter-3-line mr-1"></i> Filters <span class="font-weight-normal text-muted small">(for All transactions below)</span></h6>
                </div>
                <div class="card-body pt-0">
                    <form action="{{ route('payables.index') }}" method="get" id="payables-txn-filter-form">
                        <input type="hidden" name="search" value="{{ request('search') }}">
                        <input type="hidden" name="row" value="{{ request('row', '50') }}">
                        <input type="hidden" name="sort" value="{{ request('sort', 'name') }}">
                        <input type="hidden" name="dir" value="{{ request('dir', 'asc') }}">
                        <input type="hidden" name="txn_row" value="{{ $txnRow ?? 25 }}">
                        <div class="row align-items-end mb-3">
                            <div class="col-md-3 mb-2 mb-md-0">
                                <label for="txn_date_filter" class="form-label">Date Filter</label>
                                <select class="form-control" name="txn_date_filter" id="txn_date_filter" onchange="togglePayableTxnCustomDates()">
                                    <option value="all" {{ $txnDateFilter == 'all' ? 'selected' : '' }}>All</option>
                                    <option value="today" {{ $txnDateFilter == 'today' ? 'selected' : '' }}>Today</option>
                                    <option value="yesterday" {{ $txnDateFilter == 'yesterday' ? 'selected' : '' }}>Yesterday</option>
                                    <option value="this_week" {{ $txnDateFilter == 'this_week' ? 'selected' : '' }}>This Week</option>
                                    <option value="last_week" {{ $txnDateFilter == 'last_week' ? 'selected' : '' }}>Last Week</option>
                                    <option value="this_month" {{ $txnDateFilter == 'this_month' ? 'selected' : '' }}>This Month</option>
                                    <option value="last_month" {{ $txnDateFilter == 'last_month' ? 'selected' : '' }}>Last Month</option>
                                    <option value="this_year" {{ $txnDateFilter == 'this_year' ? 'selected' : '' }}>This Year</option>
                                    <option value="last_year" {{ $txnDateFilter == 'last_year' ? 'selected' : '' }}>Last Year</option>
                                    <option value="custom" {{ $txnDateFilter == 'custom' ? 'selected' : '' }}>Custom Range</option>
                                </select>
                            </div>
                            <div class="col-md-3 mb-2 mb-md-0" id="txn_start_date_group" style="display: {{ $txnDateFilter == 'custom' ? 'block' : 'none' }};">
                                <label for="txn_start_date" class="form-label">Start Date</label>
                                <input type="date" class="form-control" name="txn_start_date" id="txn_start_date" value="{{ $txnDateRange['start_date'] ?? '' }}">
                            </div>
                            <div class="col-md-3 mb-2 mb-md-0" id="txn_end_date_group" style="display: {{ $txnDateFilter == 'custom' ? 'block' : 'none' }};">
                                <label for="txn_end_date" class="form-label">End Date</label>
                                <input type="date" class="form-control" name="txn_end_date" id="txn_end_date" value="{{ $txnDateRange['end_date'] ?? '' }}">
                            </div>
                            <div class="col-md-3 mb-2 mb-md-0">
                                <label for="txn_type" class="form-label">Type</label>
                                <select class="form-control" name="txn_type" id="txn_type">
                                    <option value="">— All —</option>
                                    <option value="borrow" {{ ($txnType ?? '') === 'borrow' ? 'selected' : '' }}>Payable</option>
                                    <option value="repayment" {{ ($txnType ?? '') === 'repayment' ? 'selected' : '' }}>Paid</option>
                                </select>
                            </div>
                            <div class="col-md-3 mb-2 mb-md-0">
                                <label for="txn_account_id" class="form-label">Account Title</label>
                                <select name="txn_account_id" id="txn_account_id" class="form-control txn-account-select">
                                    <option value="">— All —</option>
                                    @if (isset($selectedPayableForTxn) && $selectedPayableForTxn)
                                        <option value="{{ $selectedPayableForTxn->id }}" selected>{{ $selectedPayableForTxn->name }}{{ $selectedPayableForTxn->phone ? ' - ' . $selectedPayableForTxn->phone : '' }}</option>
                                    @endif
                                </select>
                            </div>
                            <div class="col-md-3 mb-2 mb-md-0 d-flex align-items-end">
                                <button type="submit" class="btn btn-primary px-4 py-2 mr-2"><i class="ri-search-line mr-1"></i> Filter</button>
                                @php
                                    $clearTxnQuery = array_merge(request()->except(['txn_date_filter', 'txn_start_date', 'txn_end_date', 'txn_type', 'txn_account_id', 'txn_page', 'txn_row']), ['txn_date_filter' => 'all', 'txn_type' => '']);
                                @endphp
                                <a href="{{ route('payables.index', $clearTxnQuery) }}" class="btn btn-outline-secondary px-4 py-2" title="Clear transaction filters">Clear</a>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-lg-12">
            <form action="{{ route('payables.index') }}" method="get">
                <div class="d-flex flex-wrap align-items-center justify-content-between">
                    <div class="form-group row">
                        <label for="row" class="col-sm-3 align-self-center">Row:</label>
                        <div class="col-sm-9">
                            <select class="form-control" name="row" onchange="this.form.submit()">
                                <option value="10" @if(request('row') == '10') selected @endif>10</option>
                                <option value="25" @if(request('row') == '25') selected @endif>25</option>
                                <option value="50" @if(request('row', '50') == '50') selected @endif>50</option>
                                <option value="100" @if(request('row') == '100') selected @endif>100</option>
                            </select>
                        </div>
                    </div>
                    <div class="form-group row">
                        <label class="control-label col-sm-3 align-self-center" for="search">Search:</label>
                        <div class="col-sm-8">
                            <div class="input-group flex-nowrap">
                                <input type="text" id="search" class="form-control" name="search" placeholder="Search name, phone, notes" value="{{ request('search') }}" style="min-width: 200px;">
                                <div class="input-group-append">
                                    <button type="submit" class="input-group-text bg-primary"><i class="las la-search"></i></button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                @foreach (request()->only(['txn_date_filter', 'txn_start_date', 'txn_end_date', 'txn_type', 'txn_account_id', 'txn_row']) as $key => $value)
                    @if ($value !== null && $value !== '')
                        <input type="hidden" name="{{ $key }}" value="{{ $value }}">
                    @endif
                @endforeach
            </form>
        </div>

        @php
            $stats = $payableStats ?? ['total_accounts' => 0, 'total_borrowed' => 0, 'total_repaid' => 0, 'total_balance' => 0, 'overdue_count' => 0];
        @endphp
        <!-- Summary KPI cards – all on one row -->
        <div class="col-lg-12 mb-3">
            <div class="row">
                <div class="col mb-2 mb-md-0">
                    <div class="card card-block card-stretch card-height shadow-lg h-100 summary-kpi-card card-primary">
                        <div class="card-body d-flex align-items-center">
                            <div class="icon iq-icon-box-2 bg-primary-light d-flex align-items-center justify-content-center mr-3 flex-shrink-0">
                                <i class="ri-user-line text-primary" style="font-size: 1.75rem;"></i>
                            </div>
                            <div class="flex-grow-1 min-w-0">
                                <p class="text-muted mb-0 small font-weight-500">Total Accounts</p>
                                <h4 class="mb-0 font-weight-bold text-dark">{{ number_format($stats['total_accounts'], 0) }}</h4>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col mb-2 mb-md-0">
                    <div class="card card-block card-stretch card-height shadow-lg h-100 summary-kpi-card card-warning">
                        <div class="card-body d-flex align-items-center">
                            <div class="icon iq-icon-box-2 bg-warning-light d-flex align-items-center justify-content-center mr-3 flex-shrink-0">
                                <i class="ri-add-circle-line text-warning" style="font-size: 1.75rem;"></i>
                            </div>
                            <div class="flex-grow-1 min-w-0">
                                <p class="text-muted mb-0 small font-weight-500">Total Payable</p>
                                <h4 class="mb-0 font-weight-bold text-dark">{{ number_format($stats['total_borrowed'], 2) }}</h4>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col mb-2 mb-md-0">
                    <div class="card card-block card-stretch card-height shadow-lg h-100 summary-kpi-card card-success">
                        <div class="card-body d-flex align-items-center">
                            <div class="icon iq-icon-box-2 bg-success-light d-flex align-items-center justify-content-center mr-3 flex-shrink-0">
                                <i class="ri-money-dollar-circle-line text-success" style="font-size: 1.75rem;"></i>
                            </div>
                            <div class="flex-grow-1 min-w-0">
                                <p class="text-muted mb-0 small font-weight-500">Total Paid</p>
                                <h4 class="mb-0 font-weight-bold text-dark">{{ number_format($stats['total_repaid'], 2) }}</h4>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col mb-2 mb-md-0">
                    <div class="card card-block card-stretch card-height shadow-lg h-100 summary-kpi-card card-info">
                        <div class="card-body d-flex align-items-center">
                            <div class="icon iq-icon-box-2 bg-info-light d-flex align-items-center justify-content-center mr-3 flex-shrink-0">
                                <i class="ri-wallet-3-line text-info" style="font-size: 1.75rem;"></i>
                            </div>
                            <div class="flex-grow-1 min-w-0">
                                <p class="text-muted mb-0 small font-weight-500">Balance</p>
                                <h4 class="mb-0 font-weight-bold text-dark">{{ number_format($stats['total_balance'], 2) }}</h4>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col mb-2 mb-md-0">
                    <div class="card card-block card-stretch card-height shadow-lg h-100 summary-kpi-card card-danger">
                        <div class="card-body d-flex align-items-center">
                            <div class="icon iq-icon-box-2 bg-danger-light d-flex align-items-center justify-content-center mr-3 flex-shrink-0">
                                <i class="ri-alarm-warning-line text-danger" style="font-size: 1.75rem;"></i>
                            </div>
                            <div class="flex-grow-1 min-w-0">
                                <p class="text-muted mb-0 small font-weight-500">Overdue</p>
                                <h4 class="mb-0 font-weight-bold text-dark">{{ number_format($stats['overdue_count'], 0) }}</h4>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-12">
            <div class="card payables-accounts-card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">All Payable Accounts</h5>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive rounded mb-0">
                        <table class="table mb-0">
                            <thead class="bg-white text-uppercase">
                                <tr class="ligth ligth-data">
                                    <th>No.</th>
                                    <th>
                                @php
                                    $nameDir = (isset($sortBy) && $sortBy === 'name' && isset($sortDir) && $sortDir === 'asc') ? 'desc' : 'asc';
                                    $nameUrl = route('payables.index', array_merge(request()->query(), ['sort' => 'name', 'dir' => $nameDir]));
                                @endphp
                                <a href="{{ $nameUrl }}" class="sortable-th-link">Name @if(isset($sortBy) && $sortBy === 'name')<i class="ri-arrow-{{ $sortDir === 'asc' ? 'up' : 'down' }}-line ml-1"></i>@endif</a>
                            </th>
                            <th>Phone</th>
                            <th>
                                @php
                                    $balDir = (isset($sortBy) && $sortBy === 'balance' && isset($sortDir) && $sortDir === 'asc') ? 'desc' : 'asc';
                                    $balUrl = route('payables.index', array_merge(request()->query(), ['sort' => 'balance', 'dir' => $balDir]));
                                @endphp
                                <a href="{{ $balUrl }}" class="sortable-th-link">Balance @if(isset($sortBy) && $sortBy === 'balance')<i class="ri-arrow-{{ $sortDir === 'asc' ? 'up' : 'down' }}-line ml-1"></i>@endif</a>
                            </th>
                            <th>Status</th>
                            <th class="text-center">Action</th>
                        </tr>
                    </thead>
                    <tbody class="ligth-body">
                        @forelse ($payables as $payable)
                        <tr>
                            <td>{{ (($payables->currentPage() - 1) * $payables->perPage()) + $loop->iteration }}</td>
                            <td><a href="{{ route('payables.show', $payable) }}" class="text-primary">{{ $payable->name }}</a></td>
                            <td>{{ $payable->phone ?? '—' }}</td>
                            <td>{{ number_format(($payable->borrow_total ?? 0) - ($payable->repayment_total ?? 0), 2) }}</td>
                            <td>
                                @php
                                    $balance = ($payable->borrow_total ?? 0) - ($payable->repayment_total ?? 0);
                                    $isOverdue = ($payable->has_overdue_transaction ?? false) && $balance > 0;
                                @endphp
                                @if ($isOverdue)
                                    <span class="badge bg-danger">Overdue</span>
                                @else
                                    <span class="badge bg-success">OK</span>
                                @endif
                            </td>
                            <td class="payables-action-cell">
                                <div class="d-flex align-items-center flex-wrap justify-content-center" style="gap: 0.4rem;">
                                    <a class="btn btn-sm btn-info btn-icon" data-toggle="tooltip" data-placement="top" title="View" href="{{ route('payables.show', $payable) }}"><i class="ri-eye-line"></i></a>
                                    <a class="btn btn-sm btn-success btn-icon" data-toggle="tooltip" data-placement="top" title="Edit" href="{{ route('payables.edit', $payable) }}"><i class="ri-pencil-line"></i></a>
                                    <button type="button" class="btn btn-sm btn-primary btn-action-text btn-transaction-index" data-toggle="modal" data-target="#indexBorrowModal" data-payable-id="{{ $payable->id }}" title="+ Transaction"><i class="ri-add-line mr-1"></i> Transaction</button>
                                    <button type="button" class="btn btn-sm btn-primary btn-action-text btn-repay-index" data-toggle="modal" data-target="#indexRepaymentModal" data-payable-id="{{ $payable->id }}" data-balance="{{ $balance }}" title="Repayment"><i class="ri-money-dollar-circle-line mr-1"></i> Repay</button>
                                    <form action="{{ route('payables.destroy', $payable) }}" method="POST" class="d-inline">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-sm btn-warning btn-icon" data-toggle="tooltip" data-placement="top" title="Delete" onclick="return confirm('Are you sure you want to delete this payable?');"><i class="ri-delete-bin-line"></i></button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="6" class="text-center">No payables found.</td>
                        </tr>
                        @endforelse
                    </tbody>
                        </table>
                    </div>
                    <div class="p-3 border-top">{{ $payables->appends(request()->query())->links() }}</div>
                </div>
            </div>
        </div>

        {{-- Second grid: All transactions (filtered by filter box above) --}}
        <div class="col-lg-12 mt-4">
            <div class="card payables-transactions-card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">All transactions</h5>
                    <span class="text-muted small">Filtered by date and type</span>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive rounded mb-0">
                        <table class="table mb-0">
                            <thead class="bg-white text-uppercase">
                                <tr class="ligth ligth-data">
                                    <th>Date</th>
                                    <th>Account</th>
                                    <th>Type</th>
                                    <th class="text-right">Amount</th>
                                    <th>Expected Return</th>
                                    <th>Notes</th>
                                </tr>
                            </thead>
                            <tbody class="ligth-body">
                                @php $txnList = $allTransactions ?? null; @endphp
                                @if ($txnList && $txnList->count() > 0)
                                    @foreach ($txnList as $tx)
                                    <tr>
                                        <td>{{ $tx->date->format('Y-m-d') }}</td>
                                        <td><a href="{{ route('payables.show', $tx->payable) }}" class="text-primary">{{ $tx->payable->name ?? '—' }}</a></td>
                                        <td><span class="badge {{ $tx->type === 'borrow' ? 'badge-warning' : 'badge-success' }}">{{ $tx->type === 'borrow' ? 'Payable' : 'Paid' }}</span></td>
                                        <td class="text-right">{{ number_format($tx->amount, 2) }}</td>
                                        <td>{{ $tx->expected_return_date ? $tx->expected_return_date->format('Y-m-d') : '—' }}</td>
                                        <td>{{ $tx->notes ?? '—' }}</td>
                                    </tr>
                                    @endforeach
                                @else
                                <tr>
                                    <td colspan="6" class="text-center text-muted py-4">No transactions match the filter.</td>
                                </tr>
                                @endif
                            </tbody>
                        </table>
                    </div>
                    @if (isset($allTransactions) && $allTransactions->hasPages())
                        <div class="p-3 border-top">{{ $allTransactions->appends(request()->query())->links() }}</div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>

@php
    $openRepayModal = $errors->any() && old('type') === 'repayment';
    $openBorrowModal = $errors->any() && old('type') !== 'repayment';
@endphp

{{-- Modal: Borrow (+ Transaction) on index --}}
<div class="modal fade" id="indexBorrowModal" tabindex="-1" role="dialog" aria-labelledby="indexBorrowModalLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <form action="{{ route('payable-transactions.store') }}" method="POST" id="indexBorrowForm">
                @csrf
                <input type="hidden" name="payable_id" id="indexBorrowPayableId" value="{{ old('payable_id', '') }}">
                <input type="hidden" name="type" value="borrow">
                <input type="hidden" name="redirect_to" value="index">
                <div class="modal-header">
                    <h5 class="modal-title" id="indexBorrowModalLabel">+ Transaction</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                </div>
                <div class="modal-body">
                    <div class="form-group">
                        <label for="index_borrow_date">Date <span class="text-danger">*</span></label>
                        <input type="date" class="form-control @error('date') is-invalid @enderror" id="index_borrow_date" name="date" value="{{ old('date', date('Y-m-d')) }}" required>
                        @error('date')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="form-group">
                        <label for="index_borrow_amount">Amount <span class="text-danger">*</span></label>
                        <input type="number" step="0.01" min="0.01" class="form-control @error('amount') is-invalid @enderror" id="index_borrow_amount" name="amount" value="{{ old('amount') }}" required>
                        @error('amount')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="form-group">
                        <label for="index_borrow_expected_return_date">Expected Return Date</label>
                        <input type="date" class="form-control @error('expected_return_date') is-invalid @enderror" id="index_borrow_expected_return_date" name="expected_return_date" value="{{ old('expected_return_date') }}">
                        @error('expected_return_date')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="form-group">
                        <label for="index_borrow_notes">Notes</label>
                        <textarea class="form-control @error('notes') is-invalid @enderror" id="index_borrow_notes" name="notes" rows="2">{{ old('notes') }}</textarea>
                        @error('notes')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save</button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- Modal: Repayment on index --}}
<div class="modal fade" id="indexRepaymentModal" tabindex="-1" role="dialog" aria-labelledby="indexRepaymentModalLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <form action="{{ route('payable-transactions.store') }}" method="POST" id="indexRepaymentForm">
                @csrf
                <input type="hidden" name="payable_id" id="indexRepayPayableId" value="{{ old('payable_id', '') }}">
                <input type="hidden" name="type" value="repayment">
                <input type="hidden" name="redirect_to" value="index">
                <div class="modal-header">
                    <h5 class="modal-title" id="indexRepaymentModalLabel">Repayment</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                </div>
                <div class="modal-body">
                    <div class="form-group">
                        <label for="index_repay_date">Date <span class="text-danger">*</span></label>
                        <input type="date" class="form-control @error('date') is-invalid @enderror" id="index_repay_date" name="date" value="{{ old('date', date('Y-m-d')) }}" required>
                        @error('date')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="form-group">
                        <label for="index_repay_amount">Amount <span class="text-danger">*</span></label>
                        <input type="number" step="0.01" min="0.01" class="form-control @error('amount') is-invalid @enderror" id="index_repay_amount" name="amount" value="{{ old('amount') }}" required>
                        <small class="form-text text-muted" id="indexRepayMaxHint">Cannot exceed balance.</small>
                        @error('amount')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="form-group">
                        <label for="index_repay_notes">Notes</label>
                        <textarea class="form-control @error('notes') is-invalid @enderror" id="index_repay_notes" name="notes" rows="2">{{ old('notes') }}</textarea>
                        @error('notes')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@section('specificpagescripts')
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script>
function togglePayableTxnCustomDates() {
    var v = document.getElementById('txn_date_filter').value;
    var startGroup = document.getElementById('txn_start_date_group');
    var endGroup = document.getElementById('txn_end_date_group');
    if (startGroup) startGroup.style.display = v === 'custom' ? 'block' : 'none';
    if (endGroup) endGroup.style.display = v === 'custom' ? 'block' : 'none';
}
(function() {
    document.addEventListener('DOMContentLoaded', function() { togglePayableTxnCustomDates(); });
    $(document).ready(function() {
        $('#txn_account_id').select2({
            theme: 'bootstrap-5',
            placeholder: 'Type to search account...',
            allowClear: true,
            minimumInputLength: 1,
            ajax: {
                url: '{{ route("api.payables.search") }}',
                dataType: 'json',
                delay: 300,
                data: function (params) {
                    return { q: params.term || '', page: params.page || 1 };
                },
                processResults: function (data, params) {
                    params.page = params.page || 1;
                    if (!data || !data.results) {
                        return { results: [], pagination: { more: false } };
                    }
                    return {
                        results: data.results,
                        pagination: data.pagination || { more: false }
                    };
                },
                cache: true
            }
        });
    });
    document.querySelectorAll('.btn-transaction-index').forEach(function(btn) {
        btn.addEventListener('click', function() {
            var id = this.getAttribute('data-payable-id');
            document.getElementById('indexBorrowPayableId').value = id || '';
        });
    });
    document.querySelectorAll('.btn-repay-index').forEach(function(btn) {
        btn.addEventListener('click', function() {
            var id = this.getAttribute('data-payable-id');
            var balance = parseFloat(this.getAttribute('data-balance')) || 0;
            document.getElementById('indexRepayPayableId').value = id || '';
            var amountInput = document.getElementById('index_repay_amount');
            amountInput.setAttribute('max', balance);
            amountInput.placeholder = 'Max: ' + balance.toFixed(2);
            document.getElementById('indexRepayMaxHint').textContent = 'Cannot exceed balance (' + balance.toFixed(2) + ').';
        });
    });
    @if (isset($openRepayModal) && $openRepayModal)
    $(document).ready(function() { $('#indexRepaymentModal').modal('show'); });
    @endif
    @if (isset($openBorrowModal) && $openBorrowModal)
    $(document).ready(function() { $('#indexBorrowModal').modal('show'); });
    @endif
    @if (session()->has('delete_error'))
    $(document).ready(function() { $('#payablesDeleteErrorModal').modal('show'); });
    @endif
})();
</script>
@endsection
