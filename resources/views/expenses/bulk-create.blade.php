@extends('dashboard.body.main')

@section('specificpagestyles')
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <link href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" rel="stylesheet" />
    <style>
        .bulk-expense-container {
            background: #fff;
            padding: 24px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .expense-table-wrapper {
            margin: 20px 0;
        }
        .expense-table {
            width: 100%;
            border-collapse: collapse;
        }
        .expense-table thead {
            background-color: #f8f9fa;
        }
        .expense-table th,
        .expense-table td {
            padding: 10px 12px;
            border: 1px solid #dee2e6;
            text-align: left;
            vertical-align: middle;
        }
        .expense-table th {
            font-weight: 600;
            color: #495057;
        }
        .expense-table input[type="text"],
        .expense-table input[type="number"],
        .expense-table select,
        .expense-table textarea {
            width: 100%;
            padding: 8px;
            border: 1px solid #ced4da;
            border-radius: 4px;
        }
        .expense-table textarea {
            min-height: 38px;
            resize: vertical;
        }
        .expense-table .category-col { width: 18%; }
        .expense-table .amount-col { width: 10%; }
        .expense-table .description-col { width: 28%; }
        .expense-table .payment-col { width: 10%; }
        .expense-table .bank-col { width: 14%; }
        .expense-table .action-col { width: 8%; text-align: center; }
        .btn-add-expense-row {
            margin: 10px 0;
        }
        .remove-row-btn {
            padding: 6px 10px;
            border-radius: 4px;
            cursor: pointer;
        }
    </style>
@endsection

@section('container')
<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            @if (session()->has('success'))
                <div class="alert text-white bg-success" role="alert">
                    <div class="iq-alert-text">{{ session('success') }}</div>
                    <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                        <i class="ri-close-line"></i>
                    </button>
                </div>
            @endif
            @if ($errors->any())
                <div class="alert text-white bg-danger" role="alert">
                    <div class="iq-alert-text">
                        @foreach ($errors->all() as $message)
                            {{ $message }}<br>
                        @endforeach
                    </div>
                    <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                        <i class="ri-close-line"></i>
                    </button>
                </div>
            @endif
            <div class="d-flex flex-wrap align-items-center justify-content-between mb-4">
                <div>
                    <h4 class="mb-2">Bulk Expense</h4>
                    <p class="mb-0 text-muted">Add multiple expenses for a single date. One date applies to all rows.</p>
                </div>
                <a href="{{ route('expenses.index') }}" class="btn btn-outline-secondary">
                    <i class="ri-arrow-left-line mr-1"></i> Back to Expenses
                </a>
            </div>
        </div>
    </div>

    <form action="{{ route('expenses.bulk-store') }}" method="POST">
        @csrf

    <div class="bulk-expense-container">
        {{-- Date and Total row --}}
        <div class="form-row mb-4">
            <div class="col-md-4">
                <label for="bulk_expense_date" class="font-weight-bold text-primary mb-2">Expense Date (for all)</label>
                <div class="input-group">
                    <input type="date" class="form-control" id="bulk_expense_date" name="bulk_expense_date" value="{{ old('bulk_expense_date', date('Y-m-d')) }}" required>
                    <div class="input-group-append">
                        <button type="button" class="btn btn-primary" id="bulk_expense_date_btn" title="Select date" aria-label="Select date">
                            <i class="ri-calendar-line"></i>
                        </button>
                    </div>
                </div>
            </div>
            <div class="col-md-4 ml-md-auto d-flex align-items-end justify-content-md-end">
                <div class="mb-0">
                    <span class="text-muted small text-uppercase">Total amount</span>
                    <div class="h4 mb-0 font-weight-bold text-primary" id="live-total">0.00</div>
                </div>
            </div>
        </div>

        {{-- Expense table --}}
        <div class="expense-table-wrapper">
            <div class="d-flex align-items-center mb-2">
                <button type="button" class="btn btn-success btn-add-expense-row" id="add-expense-row">
                    <i class="ri-add-line mr-1"></i> Add expense row
                </button>
            </div>
            <div class="table-responsive">
                <table class="expense-table" id="expenseTable">
                    <thead>
                        <tr>
                            <th class="category-col">Category</th>
                            <th class="amount-col">Amount</th>
                            <th class="description-col">Description</th>
                            <th class="payment-col">Payment</th>
                            <th class="bank-col">Bank</th>
                            <th class="action-col">Action</th>
                        </tr>
                    </thead>
                    <tbody id="bulk-expense-tbody">
                        @foreach([0, 1, 2] as $index)
                        <tr class="expense-row" data-row-index="{{ $index }}">
                            <td>
                                <select class="form-control expense-category" name="expenses[{{ $index }}][expense_id]">
                                    <option value="">Select category</option>
                                    @foreach($expenseCategories ?? [] as $cat)
                                    <option value="{{ $cat->id }}">{{ $cat->expense_title }}</option>
                                    @endforeach
                                </select>
                            </td>
                            <td>
                                <input type="number" class="form-control expense-amount" name="expenses[{{ $index }}][activity_cost]" placeholder="0.00" min="0" step="0.01" value="">
                            </td>
                            <td>
                                <input type="text" class="form-control expense-description" name="expenses[{{ $index }}][description]" placeholder="Optional">
                            </td>
                            <td>
                                <select class="form-control payment-method" name="expenses[{{ $index }}][payment_method]">
                                    <option value="cash">Cash</option>
                                    <option value="bank">Bank</option>
                                </select>
                            </td>
                            <td class="bank-cell">
                                <select class="form-control bank-select" name="expenses[{{ $index }}][shop_bank_id]" style="display: none;">
                                    <option value="">Select bank</option>
                                    @foreach($shopBanks ?? [] as $bank)
                                    <option value="{{ $bank->id }}">{{ $bank->name }}</option>
                                    @endforeach
                                </select>
                            </td>
                            <td class="action-cell">
                                <button type="button" class="btn btn-danger btn-sm remove-row remove-row-btn" title="Remove row" aria-label="Remove row">
                                    <i class="ri-delete-bin-line"></i>
                                </button>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        {{-- Actions --}}
        <div class="row mt-4">
            <div class="col-12">
                <button type="submit" class="btn btn-primary btn-lg">
                    <i class="ri-save-line mr-1"></i> Save
                </button>
                <a href="{{ route('expenses.index') }}" class="btn btn-outline-secondary btn-lg ml-2">Cancel</a>
            </div>
        </div>
    </div>
    </form>
</div>

{{-- Template for new table row --}}
<template id="expense-row-template">
    <tr class="expense-row" data-row-index="">
        <td>
            <select class="form-control expense-category" name="expenses[__INDEX__][expense_id]">
                <option value="">Select category</option>
                @foreach($expenseCategories ?? [] as $cat)
                <option value="{{ $cat->id }}">{{ $cat->expense_title }}</option>
                @endforeach
            </select>
        </td>
        <td>
            <input type="number" class="form-control expense-amount" name="expenses[__INDEX__][activity_cost]" placeholder="0.00" min="0" step="0.01" value="">
        </td>
        <td>
            <input type="text" class="form-control expense-description" name="expenses[__INDEX__][description]" placeholder="Optional">
        </td>
        <td>
            <select class="form-control payment-method" name="expenses[__INDEX__][payment_method]">
                <option value="cash">Cash</option>
                <option value="bank">Bank</option>
            </select>
        </td>
        <td class="bank-cell">
            <select class="form-control bank-select" name="expenses[__INDEX__][shop_bank_id]" style="display: none;">
                <option value="">Select bank</option>
                @foreach($shopBanks ?? [] as $bank)
                <option value="{{ $bank->id }}">{{ $bank->name }}</option>
                @endforeach
            </select>
        </td>
        <td class="action-cell">
            <button type="button" class="btn btn-danger btn-sm remove-row remove-row-btn" title="Remove row" aria-label="Remove row">
                <i class="ri-delete-bin-line"></i>
            </button>
        </td>
    </tr>
</template>

<script>
(function() {
    var tbody = document.getElementById('bulk-expense-tbody');
    var template = document.getElementById('expense-row-template');
    var addBtn = document.getElementById('add-expense-row');
    var liveTotalEl = document.getElementById('live-total');

    function getNextIndex() {
        var rows = tbody.querySelectorAll('tr.expense-row');
        var max = -1;
        rows.forEach(function(row) {
            var idx = parseInt(row.getAttribute('data-row-index'), 10);
            if (!isNaN(idx) && idx > max) max = idx;
        });
        return max + 1;
    }

    function updateRowNumbers() {
        tbody.querySelectorAll('tr.expense-row').forEach(function(row, i) {
            row.setAttribute('data-row-index', i);
            var idx = i;
            row.querySelectorAll('[name]').forEach(function(input) {
                var name = input.getAttribute('name');
                if (name && name.indexOf('expenses[') === 0) {
                    input.setAttribute('name', name.replace(/expenses\[\d+\]/, 'expenses[' + idx + ']'));
                }
            });
        });
    }

    function updateLiveTotal() {
        var total = 0;
        tbody.querySelectorAll('.expense-amount').forEach(function(input) {
            var val = parseFloat(input.value) || 0;
            total += val;
        });
        if (liveTotalEl) liveTotalEl.textContent = total.toFixed(2);
    }

    function toggleBankInRow(row) {
        var payment = row.querySelector('.payment-method');
        var bankSelect = row.querySelector('.bank-select');
        if (!payment || !bankSelect) return;
        bankSelect.style.display = payment.value === 'bank' ? 'block' : 'none';
        if (payment.value !== 'bank') {
            bankSelect.value = '';
        }
    }

    addBtn.addEventListener('click', function() {
        var index = getNextIndex();
        var html = template.innerHTML.replace(/__INDEX__/g, index);
        var wrap = document.createElement('tbody');
        wrap.innerHTML = html.trim();
        var newTr = wrap.querySelector('tr');
        if (!newTr) return;
        newTr.setAttribute('data-row-index', index);
        tbody.appendChild(newTr);

        toggleBankInRow(newTr);
        newTr.querySelector('.payment-method').addEventListener('change', function() {
            toggleBankInRow(newTr);
        });
        newTr.querySelector('.expense-amount').addEventListener('input', updateLiveTotal);
        newTr.querySelector('.remove-row').addEventListener('click', function() {
            var rows = tbody.querySelectorAll('tr.expense-row');
            if (rows.length <= 1) return;
            newTr.remove();
            updateRowNumbers();
            updateLiveTotal();
        });
        if (typeof initSelect2ForRow === 'function') {
            initSelect2ForRow(newTr);
        }
        updateRowNumbers();
    });

    tbody.addEventListener('change', function(e) {
        if (e.target.classList.contains('payment-method')) {
            var row = e.target.closest('tr.expense-row');
            if (row) toggleBankInRow(row);
        }
    });
    tbody.addEventListener('input', function(e) {
        if (e.target.classList.contains('expense-amount')) {
            updateLiveTotal();
        }
    });

    tbody.querySelectorAll('.remove-row').forEach(function(btn) {
        btn.addEventListener('click', function() {
            var row = this.closest('tr.expense-row');
            var rows = tbody.querySelectorAll('tr.expense-row');
            if (rows.length <= 1) return;
            row.remove();
            updateRowNumbers();
            updateLiveTotal();
        });
    });

    tbody.querySelectorAll('.payment-method').forEach(function(select) {
        var row = select.closest('tr.expense-row');
        if (row) toggleBankInRow(row);
    });
    tbody.querySelectorAll('.expense-amount').forEach(function(input) {
        input.addEventListener('input', updateLiveTotal);
    });

    updateLiveTotal();
})();
</script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script>
    $(function() {
        var dateInput = document.getElementById('bulk_expense_date');
        var dateBtn = document.getElementById('bulk_expense_date_btn');
        if (dateBtn && dateInput) {
            dateBtn.addEventListener('click', function() {
                dateInput.focus();
                dateInput.showPicker && dateInput.showPicker();
            });
        }

        $('.expense-category').select2({
            theme: 'bootstrap-5',
            placeholder: 'Select category',
            allowClear: true
        });
    });

    function initSelect2ForRow(rowEl) {
        if (typeof $ !== 'undefined' && $.fn.select2) {
            $(rowEl).find('.expense-category').select2({
                theme: 'bootstrap-5',
                placeholder: 'Select category',
                allowClear: true
            });
        }
    }
</script>
@endsection
