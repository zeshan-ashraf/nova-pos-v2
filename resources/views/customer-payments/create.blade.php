@extends('dashboard.body.main')

@section('specificpagestyles')
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <link href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" rel="stylesheet" />
@endsection

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
            @if (session()->has('error'))
                <div class="alert text-white bg-danger" role="alert">
                    <div class="iq-alert-text">{{ session('error') }}</div>
                    <button type="button" class="close" data-dismiss="alert" aria-label="Close"><i class="ri-close-line"></i></button>
                </div>
            @endif

            <div class="card">
                <div class="card-header d-flex justify-content-between">
                    <div class="header-title">
                        <h4 class="card-title">Record Customer Payment</h4>
                    </div>
                </div>
                <div class="card-body">
                    <form action="{{ route('customer-payments.store') }}" method="POST">
                        @csrf
                        <div class="form-group row">
                            <div class="col-md-6">
                                <label for="customer_id">Customer <span class="text-danger">*</span></label>
                                <select class="form-control customer-select @error('customer_id') is-invalid @enderror" id="customer_id" name="customer_id" required>
                                    <option value="">Select customer</option>
                                    @foreach($customers ?? [] as $c)
                                        <option value="{{ $c->id }}" {{ old('customer_id', request('customer_id')) == $c->id ? 'selected' : '' }}>{{ $c->shopname ?: $c->name }}</option>
                                    @endforeach
                                </select>
                                @error('customer_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-md-6">
                                <label for="payment_date">Payment Date <span class="text-danger">*</span></label>
                                <input type="date" class="form-control @error('payment_date') is-invalid @enderror" id="payment_date" name="payment_date" value="{{ old('payment_date', now()->format('Y-m-d')) }}" required>
                                @error('payment_date')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                        </div>
                        <div class="form-group row">
                            <div class="col-md-6">
                                <label for="amount">Amount <span class="text-danger">*</span></label>
                                <input type="number" step="0.01" min="0.01" class="form-control @error('amount') is-invalid @enderror" id="amount" name="amount" value="{{ old('amount') }}" required>
                                @error('amount')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-md-6">
                                <label for="payment_method">Payment Method <span class="text-danger">*</span></label>
                                <select class="form-control @error('payment_method') is-invalid @enderror" id="payment_method" name="payment_method" required>
                                    <option value="cash" {{ old('payment_method', 'cash') == 'cash' ? 'selected' : '' }}>Cash</option>
                                    <option value="bank" {{ old('payment_method') == 'bank' ? 'selected' : '' }}>Bank</option>
                                </select>
                                @error('payment_method')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                        </div>
                        <div class="form-group row" id="shop_bank_group" style="display: {{ old('payment_method') == 'bank' ? 'flex' : 'none' }};">
                            <div class="col-md-6">
                                <label for="shop_bank_id">Bank Account</label>
                                <select class="form-control @error('shop_bank_id') is-invalid @enderror" id="shop_bank_id" name="shop_bank_id">
                                    <option value="">Select bank</option>
                                    @foreach($shopBanks ?? [] as $bank)
                                        <option value="{{ $bank->id }}" {{ old('shop_bank_id') == $bank->id ? 'selected' : '' }}>{{ $bank->name }}</option>
                                    @endforeach
                                </select>
                                @error('shop_bank_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                        </div>
                        <div class="form-group">
                            <label for="description">Description (optional)</label>
                            <input type="text" class="form-control @error('description') is-invalid @enderror" id="description" name="description" value="{{ old('description') }}" placeholder="e.g. Payment against invoice">
                            @error('description')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <button type="submit" class="btn btn-primary">Save payment</button>
                        <a href="{{ route('customers.index') }}" class="btn btn-secondary">Cancel</a>
                    </form>
                </div>
            </div>

            <div class="card mt-4">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h4 class="card-title mb-0">All Customer Payments</h4>
                    <form method="get" action="{{ route('customer-payments.create') }}" class="d-flex align-items-center">
                        <label class="mb-0 mr-2">Show</label>
                        <select name="row" class="form-control form-control-sm" style="width: auto;" onchange="this.form.submit()">
                            <option value="10" {{ request('row', 15) == 10 ? 'selected' : '' }}>10</option>
                            <option value="15" {{ request('row', 15) == 15 ? 'selected' : '' }}>15</option>
                            <option value="25" {{ request('row') == 25 ? 'selected' : '' }}>25</option>
                            <option value="50" {{ request('row') == 50 ? 'selected' : '' }}>50</option>
                            <option value="100" {{ request('row') == 100 ? 'selected' : '' }}>100</option>
                        </select>
                        <span class="ml-2">per page</span>
                    </form>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-striped mb-0">
                            <thead class="bg-light text-uppercase">
                                <tr>
                                    <th>#</th>
                                    <th>Customer</th>
                                    <th>Date</th>
                                    <th>Amount</th>
                                    <th>Method</th>
                                    <th>Description</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($payments as $index => $p)
                                <tr>
                                    <td>{{ $payments->firstItem() + $index }}</td>
                                    <td>{{ $p->customer_name }}</td>
                                    <td>{{ \Carbon\Carbon::parse($p->transaction_date)->format('d M Y') }}</td>
                                    <td>{{ number_format($p->amount, 2) }}</td>
                                    <td>{{ ucfirst($p->payment_method) }}</td>
                                    <td>{{ $p->description ?? '—' }}</td>
                                </tr>
                                @empty
                                <tr>
                                    <td colspan="6" class="text-center text-muted py-4">No customer payments recorded yet.</td>
                                </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                    @if($payments->hasPages())
                    <div class="d-flex justify-content-end mt-3">
                        {{ $payments->withQueryString()->links() }}
                    </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@section('specificpagescripts')
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script>
(function() {
    document.getElementById('payment_method').addEventListener('change', function() {
        var isBank = this.value === 'bank';
        document.getElementById('shop_bank_group').style.display = isBank ? 'flex' : 'none';
        if (!isBank) document.getElementById('shop_bank_id').value = '';
    });

    if (typeof $ !== 'undefined' && $.fn.select2) {
        $('.customer-select').select2({
            theme: 'bootstrap-5',
            placeholder: 'Search customer...',
            allowClear: true,
            width: '100%',
            minimumResultsForSearch: 0
        }).on('select2:open', function() {
            var focusSearch = function() {
                var el = document.querySelector('.select2-container--open .select2-search__field');
                if (el) {
                    el.focus();
                }
            };
            requestAnimationFrame(function() { focusSearch(); });
            setTimeout(focusSearch, 50);
            setTimeout(focusSearch, 200);
        });
    }
})();
</script>
@endsection
