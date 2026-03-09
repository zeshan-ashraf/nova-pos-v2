@extends('dashboard.body.main')

@section('container')
<div class="container-fluid">
    <div class="row">
        <div class="col-lg-12">
            @if (session()->has('success'))
                <div class="alert text-white bg-success" role="alert">
                    <div class="iq-alert-text">{{ session('success') }}</div>
                    <button type="button" class="close" data-dismiss="alert" aria-label="Close"><i class="ri-close-line"></i></button>
                </div>
            @endif
            @if ($errors->any())
                <div class="alert text-white bg-danger" role="alert">
                    <div class="iq-alert-text">{{ $errors->first() }}</div>
                    <button type="button" class="close" data-dismiss="alert" aria-label="Close"><i class="ri-close-line"></i></button>
                </div>
            @endif

            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <div>
                        <h4 class="card-title mb-1">Payable Ledger: {{ $payable->name }}</h4>
                        <p class="mb-0 text-muted small">Phone: {{ $payable->phone ?? '—' }} &nbsp;|&nbsp; Balance: <strong>{{ number_format($payable->balance, 2) }}</strong></p>
                    </div>
                    <div>
                        <button type="button" class="btn btn-success btn-sm" data-toggle="modal" data-target="#borrowModal">+ Transaction</button>
                        <button type="button" class="btn btn-primary btn-sm" data-toggle="modal" data-target="#repaymentModal">Repayment</button>
                        <a href="{{ route('payables.edit', $payable) }}" class="btn btn-outline-secondary btn-sm">Edit</a>
                        <a href="{{ route('payables.index') }}" class="btn btn-outline-secondary btn-sm">Back to list</a>
                    </div>
                </div>
                <div class="card-body">
                    {{-- Summary totals: cards like sales summary report --}}
                    <div class="row mb-4">
                        <div class="col-md-4 mb-3 mb-md-0">
                            <div class="card card-block card-stretch card-height shadow-sm h-100">
                                <div class="card-body d-flex align-items-center">
                                    <div class="icon iq-icon-box-2 bg-primary-light d-flex align-items-center justify-content-center mr-3 flex-shrink-0">
                                        <i class="ri-add-circle-line text-primary" style="font-size: 1.75rem;"></i>
                                    </div>
                                    <div class="flex-grow-1 min-w-0">
                                        <p class="text-muted mb-0 small font-weight-500">Total Payable</p>
                                        <h4 class="mb-0 font-weight-bold text-dark">{{ number_format($borrowTotal ?? 0, 2) }}</h4>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4 mb-3 mb-md-0">
                            <div class="card card-block card-stretch card-height shadow-sm h-100">
                                <div class="card-body d-flex align-items-center">
                                    <div class="icon iq-icon-box-2 bg-success-light d-flex align-items-center justify-content-center mr-3 flex-shrink-0">
                                        <i class="ri-money-dollar-circle-line text-success" style="font-size: 1.75rem;"></i>
                                    </div>
                                    <div class="flex-grow-1 min-w-0">
                                        <p class="text-muted mb-0 small font-weight-500">Total Paid</p>
                                        <h4 class="mb-0 font-weight-bold text-dark">{{ number_format($repaymentTotal ?? 0, 2) }}</h4>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4 mb-3 mb-md-0">
                            <div class="card card-block card-stretch card-height shadow-sm h-100">
                                <div class="card-body d-flex align-items-center">
                                    <div class="icon iq-icon-box-2 bg-info-light d-flex align-items-center justify-content-center mr-3 flex-shrink-0">
                                        <i class="ri-wallet-3-line text-info" style="font-size: 1.75rem;"></i>
                                    </div>
                                    <div class="flex-grow-1 min-w-0">
                                        <p class="text-muted mb-0 small font-weight-500">Balance</p>
                                        <h4 class="mb-0 font-weight-bold text-dark">{{ number_format($payable->balance, 2) }}</h4>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="table-responsive rounded mb-0">
                        <table class="table mb-0">
                            <thead class="bg-white text-uppercase">
                                <tr class="ligth ligth-data">
                                    <th>Date</th>
                                    <th>Type</th>
                                    <th class="text-right">Amount</th>
                                    <th>Expected Return Date</th>
                                    <th>Status</th>
                                    <th>Notes</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody class="ligth-body">
                                @php $balance = $payable->balance; @endphp
                                @forelse ($payable->payableTransactions as $tx)
                                <tr>
                                    <td>{{ $tx->date->format('Y-m-d') }}</td>
                                    <td><span class="badge {{ $tx->type === 'borrow' ? 'badge-warning' : 'badge-success' }}">{{ $tx->type === 'borrow' ? 'Payable' : 'Paid' }}</span></td>
                                    <td class="text-right">{{ number_format($tx->amount, 2) }}</td>
                                    <td>{{ $tx->expected_return_date ? $tx->expected_return_date->format('Y-m-d') : '—' }}</td>
                                    <td>
                                        @if ($tx->expected_return_date && $tx->expected_return_date->isPast() && $balance > 0)
                                            <span class="badge bg-danger">Overdue</span>
                                        @else
                                            —
                                        @endif
                                    </td>
                                    <td>{{ $tx->notes ?? '—' }}</td>
                                    <td>
                                        <form action="{{ route('payable-transactions.destroy', $tx->id) }}" method="POST" class="d-inline" onsubmit="return confirm('Delete this transaction?');">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="badge bg-warning border-none btn-sm"><i class="ri-delete-bin-line mr-0"></i> Delete</button>
                                        </form>
                                    </td>
                                </tr>
                                @empty
                                <tr>
                                    <td colspan="7" class="text-center text-muted">No transactions yet. Use Borrow or Repayment to add.</td>
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

{{-- Modal: Borrow --}}
<div class="modal fade" id="borrowModal" tabindex="-1" role="dialog" aria-labelledby="borrowModalLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <form action="{{ route('payable-transactions.store') }}" method="POST">
                @csrf
                <input type="hidden" name="payable_id" value="{{ $payable->id }}">
                <input type="hidden" name="type" value="borrow">
                <div class="modal-header">
                    <h5 class="modal-title" id="borrowModalLabel">Borrow</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <div class="form-group">
                        <label for="borrow_date">Date <span class="text-danger">*</span></label>
                        <input type="date" class="form-control @error('date') is-invalid @enderror" id="borrow_date" name="date" value="{{ old('date', date('Y-m-d')) }}" required>
                        @error('date')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="form-group">
                        <label for="borrow_amount">Amount <span class="text-danger">*</span></label>
                        <input type="number" step="0.01" min="0.01" class="form-control @error('amount') is-invalid @enderror" id="borrow_amount" name="amount" value="{{ old('amount') }}" required>
                        @error('amount')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="form-group">
                        <label for="borrow_expected_return_date">Expected Return Date</label>
                        <input type="date" class="form-control @error('expected_return_date') is-invalid @enderror" id="borrow_expected_return_date" name="expected_return_date" value="{{ old('expected_return_date') }}">
                        @error('expected_return_date')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="form-group">
                        <label for="borrow_notes">Notes</label>
                        <textarea class="form-control @error('notes') is-invalid @enderror" id="borrow_notes" name="notes" rows="2">{{ old('notes') }}</textarea>
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

{{-- Modal: Repayment --}}
<div class="modal fade" id="repaymentModal" tabindex="-1" role="dialog" aria-labelledby="repaymentModalLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <form action="{{ route('payable-transactions.store') }}" method="POST">
                @csrf
                <input type="hidden" name="payable_id" value="{{ $payable->id }}">
                <input type="hidden" name="type" value="repayment">
                <div class="modal-header">
                    <h5 class="modal-title" id="repaymentModalLabel">Repayment</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <div class="form-group">
                        <label for="repayment_date">Date <span class="text-danger">*</span></label>
                        <input type="date" class="form-control @error('date') is-invalid @enderror" id="repayment_date" name="date" value="{{ old('date', date('Y-m-d')) }}" required>
                        @error('date')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="form-group">
                        <label for="repayment_amount">Amount <span class="text-danger">*</span></label>
                        <input type="number" step="0.01" min="0.01" max="{{ $payable->balance > 0 ? $payable->balance : '' }}" class="form-control @error('amount') is-invalid @enderror" id="repayment_amount" name="amount" value="{{ old('amount') }}" placeholder="Max: {{ number_format($payable->balance, 2) }}" required>
                        <small class="form-text text-muted">Cannot exceed balance ({{ number_format($payable->balance, 2) }})</small>
                        @error('amount')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="form-group">
                        <label for="repayment_notes">Notes</label>
                        <textarea class="form-control @error('notes') is-invalid @enderror" id="repayment_notes" name="notes" rows="2">{{ old('notes') }}</textarea>
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
