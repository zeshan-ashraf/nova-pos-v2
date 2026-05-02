@extends('dashboard.body.main')

@section('specificpagestyles')
    <script src="https://code.jquery.com/jquery-3.3.1.min.js"></script>
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <link href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" rel="stylesheet" />
@endsection

@section('container')
<div class="container-fluid">
    <div class="row">
        <div class="col-lg-12">
            <div class="card">
                <div class="card-header d-flex justify-content-between">
                    <div class="header-title">
                        <h4 class="card-title">Add Shop Expense</h4>
                    </div>
                </div>
                <div class="card-body">
                    <form action="{{ route('shop-expenses.store') }}" method="POST" id="shop-expense-create-form">
                        @csrf
                        <div class="form-group row">
                            <div class="col-md-12">
                                <label for="expense_id">Expense category</label>
                                <select class="form-control shop-expense-category-select @error('expense_id') is-invalid @enderror" id="expense_id" name="expense_id">
                                    <option value="">Optional</option>
                                    @foreach ($expenseCategories as $cat)
                                        <option value="{{ $cat->id }}" @selected((string) old('expense_id') === (string) $cat->id)>{{ $cat->expense_title }}</option>
                                    @endforeach
                                </select>
                                @error('expense_id')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                            </div>
                        </div>
                        <div class="form-group row">
                            <div class="col-md-4">
                                <label for="expense_date">Date <span class="text-danger">*</span></label>
                                <input type="date" class="form-control @error('expense_date') is-invalid @enderror" id="expense_date" name="expense_date" value="{{ old('expense_date', now()->format('Y-m-d')) }}" required>
                                @error('expense_date')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-md-4">
                                <label for="amount">Amount <span class="text-danger">*</span></label>
                                <input type="number" step="0.01" min="0" class="form-control @error('amount') is-invalid @enderror" id="amount" name="amount" value="{{ old('amount') }}" required>
                                @error('amount')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-md-4">
                                <label for="payment_type">Payment type <span class="text-danger">*</span></label>
                                <select class="form-control @error('payment_type') is-invalid @enderror" id="payment_type" name="payment_type" required>
                                    <option value="cash" @selected(old('payment_type', 'cash') === 'cash')>Cash</option>
                                    <option value="bank" @selected(old('payment_type') === 'bank')>Bank</option>
                                </select>
                                @error('payment_type')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                        </div>
                        <div class="form-group row">
                            <div class="col-md-6" id="bank_field_wrap" style="display: {{ old('payment_type', 'cash') === 'bank' ? 'block' : 'none' }};">
                                <label for="bank_id">Bank <span class="text-danger">*</span></label>
                                <select class="form-control shop-expense-bank-select @error('bank_id') is-invalid @enderror" id="bank_id" name="bank_id">
                                    <option value="">Select bank</option>
                                    @foreach ($shopBanks as $bank)
                                        <option value="{{ $bank->id }}" @selected((string) old('bank_id') === (string) $bank->id)>{{ $bank->name }}</option>
                                    @endforeach
                                </select>
                                @error('bank_id')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                            </div>
                        </div>
                        <div class="form-group row">
                            <div class="col-md-12">
                                <label for="description">Description</label>
                                <textarea class="form-control @error('description') is-invalid @enderror" id="description" name="description" rows="3">{{ old('description') }}</textarea>
                                @error('description')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                        </div>
                        <div class="mt-2">
                            <button type="submit" class="btn btn-primary mr-2" id="shop-expense-submit-btn">
                                <span class="shop-expense-btn-label">Save</span>
                                <span class="shop-expense-btn-loading d-none" aria-live="polite">
                                    <i class="fas fa-spinner fa-spin mr-1" aria-hidden="true"></i>Saving…
                                </span>
                            </button>
                            <a class="btn btn-danger" href="{{ route('shop-expenses.index') }}" id="shop-expense-cancel-btn">Cancel</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script>
$(document).ready(function() {
    function bindSelect2Focus($el) {
        $el.on('select2:open', function() {
            var focusSearch = function() {
                var el = document.querySelector('.select2-container--open .select2-search__field');
                if (el) el.focus();
            };
            requestAnimationFrame(function() { focusSearch(); });
            setTimeout(focusSearch, 50);
            setTimeout(focusSearch, 200);
        });
    }
    $('.shop-expense-category-select').select2({
        theme: 'bootstrap-5',
        placeholder: 'Search category…',
        allowClear: true,
        width: '100%',
        minimumResultsForSearch: 0
    });
    bindSelect2Focus($('.shop-expense-category-select'));
    $('.shop-expense-bank-select').select2({
        theme: 'bootstrap-5',
        placeholder: 'Search bank…',
        allowClear: true,
        width: '100%',
        minimumResultsForSearch: 0
    });
    bindSelect2Focus($('.shop-expense-bank-select'));
    $('#payment_type').on('change', function() {
        var isBank = $(this).val() === 'bank';
        $('#bank_field_wrap').toggle(isBank);
        if (!isBank) $('#bank_id').val(null).trigger('change');
    });

    var $form = $('#shop-expense-create-form');
    var $submitBtn = $('#shop-expense-submit-btn');
    $form.on('submit', function(e) {
        if (typeof this.checkValidity === 'function' && !this.checkValidity()) {
            return;
        }
        if ($submitBtn.prop('disabled')) {
            e.preventDefault();
            return;
        }
        $submitBtn.prop('disabled', true);
        $submitBtn.find('.shop-expense-btn-label').addClass('d-none');
        $submitBtn.find('.shop-expense-btn-loading').removeClass('d-none');
        $('#shop-expense-cancel-btn').addClass('disabled').attr('tabindex', '-1').on('click', function(ev) {
            ev.preventDefault();
        });
    });
});
</script>
@endsection
