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
            @if(session('print_customer_payment_id'))
                <div class="card border-success mb-3">
                    <div class="card-body d-flex flex-wrap justify-content-between align-items-center">
                        <div class="mb-2 mb-md-0">
                            <h6 class="mb-1 text-success">Payment saved successfully</h6>
                            <small class="text-muted">Print this payment now.</small>
                        </div>
                        <div class="btn-group">
                            <button type="button" class="btn btn-success dropdown-toggle" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                                Print
                            </button>
                            <div class="dropdown-menu dropdown-menu-right">
                                <a class="dropdown-item" href="{{ route('customer-payments.printA4', session('print_customer_payment_id')) }}" target="_blank">
                                    <i class="fas fa-file-alt"></i> A4
                                </a>
                                <a class="dropdown-item" href="{{ route('customer-payments.printReceipt', session('print_customer_payment_id')) }}" target="_blank">
                                    <i class="fas fa-receipt"></i> Receipt
                                </a>
                            </div>
                        </div>
                    </div>
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
                                <select class="form-control payment-customer-select @error('customer_id') is-invalid @enderror" id="customer_id" name="customer_id" required>
                                    <option value="">Select customer</option>
                                    @foreach($customers ?? [] as $c)
                                        <option value="{{ $c->id }}"
                                            data-credit-amount="{{ $c->credit_amount ?? 0 }}"
                                            {{ old('customer_id', request('customer_id')) == $c->id ? 'selected' : '' }}>
                                            {{ $c->shopname ? ($c->name ? $c->shopname . ' (' . $c->name . ')' : $c->shopname) : ($c->name ?? '—') }}
                                        </option>
                                    @endforeach
                                </select>
                                @error('customer_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                <div id="customer_due_amount_wrap" class="mt-2" style="display: none;">
                                    <small class="text-muted">Due / Payable:</small>
                                    <strong id="customer_due_amount" class="ml-1 text-danger">0.00</strong>
                                </div>
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

            @php
                $dateRange = $dateRange ?? [];
                $dateFilter = $dateRange['date_filter'] ?? 'all';
                $paymentMethodFilter = $payment_method_filter ?? request('payment_method_filter', '');
            @endphp
            <div class="card report-filter-card border-primary shadow-sm mt-4">
                <div class="card-header border-0 py-2">
                    <h6 class="mb-0 text-primary"><i class="ri-filter-3-line mr-1"></i> Filters</h6>
                </div>
                <div class="card-body pt-0">
                    <form action="{{ route('customer-payments.create') }}" method="GET">
                        <div class="row align-items-end">
                            <div class="col-md-2 mb-2 mb-md-0">
                                <label for="date_filter" class="form-label">Date Filter</label>
                                <select class="form-control" name="date_filter" id="date_filter" onchange="toggleCustomerPaymentCustomDates()">
                                    <option value="all" {{ $dateFilter === 'all' ? 'selected' : '' }}>All</option>
                                    <option value="today" {{ $dateFilter === 'today' ? 'selected' : '' }}>Today</option>
                                    <option value="yesterday" {{ $dateFilter === 'yesterday' ? 'selected' : '' }}>Yesterday</option>
                                    <option value="this_week" {{ $dateFilter === 'this_week' ? 'selected' : '' }}>This Week</option>
                                    <option value="last_week" {{ $dateFilter === 'last_week' ? 'selected' : '' }}>Last Week</option>
                                    <option value="this_month" {{ $dateFilter === 'this_month' ? 'selected' : '' }}>This Month</option>
                                    <option value="last_month" {{ $dateFilter === 'last_month' ? 'selected' : '' }}>Last Month</option>
                                    <option value="this_year" {{ $dateFilter === 'this_year' ? 'selected' : '' }}>This Year</option>
                                    <option value="last_year" {{ $dateFilter === 'last_year' ? 'selected' : '' }}>Last Year</option>
                                    <option value="custom" {{ $dateFilter === 'custom' ? 'selected' : '' }}>Custom Range</option>
                                </select>
                            </div>
                            <div class="col-md-2 mb-2 mb-md-0">
                                <label for="filter_customer_id" class="form-label">Customer</label>
                                <select class="form-control filter-customer-select" id="filter_customer_id" name="customer_id">
                                    <option value="">— All —</option>
                                    @foreach($customers ?? [] as $c)
                                        <option value="{{ $c->id }}" {{ request('customer_id') == $c->id ? 'selected' : '' }}>{{ $c->shopname ? ($c->name ? $c->shopname . ' (' . $c->name . ')' : $c->shopname) : ($c->name ?? '—') }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-2 mb-2 mb-md-0">
                                <label for="payment_method_filter" class="form-label">Payment Method</label>
                                <select class="form-control" name="payment_method_filter" id="payment_method_filter">
                                    <option value="" {{ $paymentMethodFilter === '' ? 'selected' : '' }}>All</option>
                                    <option value="cash" {{ $paymentMethodFilter === 'cash' ? 'selected' : '' }}>Cash</option>
                                    <option value="bank" {{ $paymentMethodFilter === 'bank' ? 'selected' : '' }}>Bank</option>
                                </select>
                            </div>
                            <div class="col-md-2 mb-2 mb-md-0" id="filter_start_date_group" style="display: {{ $dateFilter === 'custom' ? 'block' : 'none' }};">
                                <label for="start_date" class="form-label">Start Date</label>
                                <input type="date" class="form-control" id="start_date" name="start_date" value="{{ $dateRange['start_date'] ?? '' }}">
                            </div>
                            <div class="col-md-2 mb-2 mb-md-0" id="filter_end_date_group" style="display: {{ $dateFilter === 'custom' ? 'block' : 'none' }};">
                                <label for="end_date" class="form-label">End Date</label>
                                <input type="date" class="form-control" id="end_date" name="end_date" value="{{ $dateRange['end_date'] ?? '' }}">
                            </div>
                        </div>
                        <div class="row mt-3">
                            <div class="col-12 overflow-x-auto">
                                <div class="customer-payments-filter-actions d-flex flex-row flex-nowrap align-items-center">
                                    <button type="submit" class="btn btn-primary px-3 py-2 mr-2 mb-0">
                                        <i class="ri-search-line mr-1"></i> Filter
                                    </button>
                                    <a href="{{ route('customer-payments.exportExcel', request()->except('page')) }}" class="btn btn-outline-success px-3 py-2 mr-2 mb-0">
                                        <i class="fas fa-file-excel mr-1"></i> Export Excel
                                    </a>
                                    <a href="{{ route('customer-payments.create') }}" class="btn btn-outline-secondary px-3 py-2 mb-0">Clear</a>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            <div class="card mt-4">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h4 class="card-title mb-0">All Customer Payments</h4>
                    <form method="get" action="{{ route('customer-payments.create') }}" class="d-flex align-items-center">
                        <input type="hidden" name="date_filter" value="{{ request('date_filter', 'all') }}">
                        <input type="hidden" name="customer_id" value="{{ request('customer_id') }}">
                        <input type="hidden" name="payment_method_filter" value="{{ request('payment_method_filter') }}">
                        <input type="hidden" name="start_date" value="{{ request('start_date') }}">
                        <input type="hidden" name="end_date" value="{{ request('end_date') }}">
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
                        <table class="table table-striped mb-0" id="customerPaymentsTable">
                            <thead class="bg-light text-uppercase">
                                <tr>
                                    <th>#</th>
                                    <th>Receipt No</th>
                                    <th>Customer</th>
                                    <th>Date</th>
                                    <th>Amount</th>
                                    <th>Method</th>
                                    <th>Description</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($payments as $index => $p)
                                <tr>
                                    <td>{{ $payments->firstItem() + $index }}</td>
                                    <td>{{ $p->receipt_no ?? '—' }}</td>
                                    <td>{{ $p->customer_name }}</td>
                                    <td>{{ \Carbon\Carbon::parse($p->transaction_date)->format('d M Y') }}</td>
                                    <td>{{ number_format($p->amount, 2) }}</td>
                                    <td>{{ ucfirst($p->payment_method) }}</td>
                                    <td class="text-break">
                                        @php
                                            $payDesc = (string) ($p->description ?? '');
                                        @endphp
                                        @if($payDesc === '')
                                            —
                                        @elseif(mb_strlen($payDesc) <= 15)
                                            {{ $payDesc }}
                                        @else
                                            <span class="cp-desc-wrap d-inline-block">
                                                <span class="cp-desc-collapsed">
                                                    {{ \Illuminate\Support\Str::substr($payDesc, 0, 15) }}<button type="button" class="btn btn-link btn-sm p-0 align-baseline cp-desc-more text-decoration-none" aria-expanded="false" title="Show full description">....</button>
                                                </span>
                                                <span class="cp-desc-expanded d-none">
                                                    {{ $payDesc }} <button type="button" class="btn btn-link btn-sm p-0 align-baseline cp-desc-less text-decoration-none" aria-expanded="true" title="Show less">less</button>
                                                </span>
                                            </span>
                                        @endif
                                    </td>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <div class="btn-group mr-2">
                                                <button type="button" class="btn btn-success btn-sm dropdown-toggle" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                                                    Print
                                                </button>
                                                <div class="dropdown-menu dropdown-menu-right">
                                                    <a class="dropdown-item" href="{{ route('customer-payments.printA4', $p->id) }}" target="_blank">
                                                        <i class="fas fa-file-alt"></i> A4
                                                    </a>
                                                    <a class="dropdown-item" href="{{ route('customer-payments.printReceipt', $p->id) }}" target="_blank">
                                                        <i class="fas fa-receipt"></i> Receipt
                                                    </a>
                                                </div>
                                            </div>
                                            <button type="button" class="btn btn-danger btn-sm delete-payment" data-id="{{ $p->id }}">Delete</button>
                                        </div>
                                    </td>
                                </tr>
                                @empty
                                <tr>
                                    <td colspan="8" class="text-center text-muted py-4">No customer payments recorded yet.</td>
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
    window.toggleCustomerPaymentCustomDates = function() {
        var isCustom = document.getElementById('date_filter').value === 'custom';
        document.getElementById('filter_start_date_group').style.display = isCustom ? 'block' : 'none';
        document.getElementById('filter_end_date_group').style.display = isCustom ? 'block' : 'none';
    };

    document.getElementById('payment_method').addEventListener('change', function() {
        var isBank = this.value === 'bank';
        document.getElementById('shop_bank_group').style.display = isBank ? 'flex' : 'none';
        if (!isBank) document.getElementById('shop_bank_id').value = '';
    });

    function formatPaymentAmount(n) {
        var num = parseFloat(n) || 0;
        return num.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    function updateCustomerDueAmount() {
        var select = document.getElementById('customer_id');
        var wrap = document.getElementById('customer_due_amount_wrap');
        var amountEl = document.getElementById('customer_due_amount');
        if (!select || !wrap || !amountEl) return;

        var selected = select.options[select.selectedIndex];
        if (!selected || !selected.value) {
            wrap.style.display = 'none';
            amountEl.textContent = '0.00';
            amountEl.classList.remove('text-success');
            amountEl.classList.add('text-danger');
            return;
        }

        var credit = parseFloat(selected.getAttribute('data-credit-amount')) || 0;
        wrap.style.display = 'block';
        amountEl.textContent = formatPaymentAmount(credit);
        // Positive = customer owes us (due); negative = advance
        if (credit < 0) {
            amountEl.classList.remove('text-danger');
            amountEl.classList.add('text-success');
        } else {
            amountEl.classList.remove('text-success');
            amountEl.classList.add('text-danger');
        }
    }

    if (typeof $ !== 'undefined' && $.fn.select2) {
        $('.payment-customer-select').select2({
            theme: 'bootstrap-5',
            placeholder: 'Search customer...',
            allowClear: false,
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
        }).on('change', updateCustomerDueAmount);

        $('.filter-customer-select').select2({
            theme: 'bootstrap-5',
            placeholder: 'All customers',
            allowClear: true,
            width: '100%',
            minimumResultsForSearch: 0
        });
    } else {
        document.getElementById('customer_id')?.addEventListener('change', updateCustomerDueAmount);
    }

    updateCustomerDueAmount();

    (function() {
        var amountInput = document.getElementById('amount');
        if (amountInput) {
            amountInput.addEventListener('wheel', function(e) {
                if (document.activeElement === amountInput) {
                    e.preventDefault();
                }
            }, { passive: false });
        }
    })();

    document.getElementById('customerPaymentsTable')?.addEventListener('click', function(e) {
        var more = e.target.closest('.cp-desc-more');
        var less = e.target.closest('.cp-desc-less');
        if (!more && !less) return;
        e.preventDefault();
        var wrap = (more || less).closest('.cp-desc-wrap');
        if (!wrap) return;
        var collapsed = wrap.querySelector('.cp-desc-collapsed');
        var expanded = wrap.querySelector('.cp-desc-expanded');
        if (more) {
            collapsed.classList.add('d-none');
            expanded.classList.remove('d-none');
        } else {
            expanded.classList.add('d-none');
            collapsed.classList.remove('d-none');
        }
    });

    document.querySelectorAll('.delete-payment').forEach(function(button) {
        button.addEventListener('click', function() {
            var paymentId = this.getAttribute('data-id');
            if (confirm('Are you sure you want to delete this payment?')) {
                fetch('/customer-payments/' + paymentId, {
                    method: 'DELETE',
                    headers: {
                        'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]') && document.querySelector('meta[name="csrf-token"]').getAttribute('content')) || (document.querySelector('input[name="_token"]') && document.querySelector('input[name="_token"]').value),
                        'Accept': 'application/json',
                        'Content-Type': 'application/json'
                    }
                })
                .then(function(res) { return res.json(); })
                .then(function(data) {
                    if (data.success) {
                        alert('Payment deleted successfully!');
                        location.reload();
                    } else {
                        alert(data.message || 'Error deleting payment!');
                    }
                })
                .catch(function() {
                    alert('Error deleting payment!');
                });
            }
        });
    });
})();
</script>
@endsection
