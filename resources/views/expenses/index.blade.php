@extends('dashboard.body.main')

@section('container')
<div class="container-fluid">
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
            @if (session()->has('error') || $errors->has('expense_ids'))
                <div class="alert text-white bg-danger" role="alert">
                    <div class="iq-alert-text">{{ $errors->first('expense_ids') ?? session('error') }}</div>
                    <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                    <i class="ri-close-line"></i>
                    </button>
                </div>
            @endif
            <div class="d-flex flex-wrap align-items-center justify-content-between mb-4">
                <div>
                    <h4 class="mb-3">Expense List</h4>
                </div>
                <div>
                <a href="{{ route('expenses.bulk-create') }}" class="btn btn-outline-primary add-list mr-2">Bulk Expense</a>
                <a href="{{ route('expenses.create') }}" class="btn btn-primary add-list">Add Expense</a>
                </div>
            </div>
        </div>

        @php
            $dateRange = $dateRange ?? [];
            $dateFilter = $dateRange['date_filter'] ?? 'today';
            $groupBy = $groupBy ?? 'none';
            $expenseTab = $expenseTab ?? request('expense_tab', 'petty');
            $disableDelete = ($expenseTab === 'purchase');
            $usePurchaseDesign = ($expenseTab === 'purchase' && $groupBy === 'none');
            $noDataColspan = $usePurchaseDesign ? 9 : 8;
        @endphp

        @php
            $tabBaseQuery = request()->except(['expense_tab', 'page']);
            $pettyTabQuery = array_merge($tabBaseQuery, ['expense_tab' => 'petty', 'page' => 1]);
            $purchaseTabQuery = array_merge($tabBaseQuery, ['expense_tab' => 'purchase', 'page' => 1]);
        @endphp

        <div class="col-lg-12 mb-3">
            <div class="card report-filter-card border-primary shadow-sm">
                <div class="card-header border-0 py-2">
                    <h6 class="mb-0 text-primary"><i class="ri-filter-3-line mr-1"></i> Filters</h6>
                </div>
                <div class="card-body pt-0">
                    <form action="{{ route('expenses.index') }}" method="get" id="expenses-filter-form">
                <input type="hidden" name="expense_tab" value="{{ $expenseTab }}">
                <div class="d-flex flex-wrap align-items-end mb-3">
                    <div class="form-group mr-3 mb-2">
                        <label for="date_filter" class="small mb-0">Date</label>
                        <select class="form-control form-control-sm" name="date_filter" id="date_filter" onchange="toggleExpenseCustomDates()">
                            <option value="today" {{ $dateFilter == 'today' ? 'selected' : '' }}>Today</option>
                            <option value="yesterday" {{ $dateFilter == 'yesterday' ? 'selected' : '' }}>Yesterday</option>
                            <option value="this_week" {{ $dateFilter == 'this_week' ? 'selected' : '' }}>This Week</option>
                            <option value="last_week" {{ $dateFilter == 'last_week' ? 'selected' : '' }}>Last Week</option>
                            <option value="this_month" {{ $dateFilter == 'this_month' ? 'selected' : '' }}>This Month</option>
                            <option value="last_month" {{ $dateFilter == 'last_month' ? 'selected' : '' }}>Last Month</option>
                            <option value="this_year" {{ $dateFilter == 'this_year' ? 'selected' : '' }}>This Year</option>
                            <option value="last_year" {{ $dateFilter == 'last_year' ? 'selected' : '' }}>Last Year</option>
                            <option value="custom" {{ $dateFilter == 'custom' ? 'selected' : '' }}>Custom Range</option>
                        </select>
                    </div>
                    <div class="form-group mr-3 mb-2" id="expense_start_date_group" style="display: {{ $dateFilter == 'custom' ? 'block' : 'none' }};">
                        <label for="start_date" class="small mb-0">Start Date</label>
                        <input type="date" class="form-control form-control-sm" name="start_date" id="start_date" value="{{ $dateRange['start_date'] ?? '' }}">
                    </div>
                    <div class="form-group mr-3 mb-2" id="expense_end_date_group" style="display: {{ $dateFilter == 'custom' ? 'block' : 'none' }};">
                        <label for="end_date" class="small mb-0">End Date</label>
                        <input type="date" class="form-control form-control-sm" name="end_date" id="end_date" value="{{ $dateRange['end_date'] ?? '' }}">
                    </div>
                    <div class="form-group mr-3 mb-2">
                        <label for="group_by" class="small mb-0">Group by</label>
                        <select class="form-control form-control-sm" name="group_by" id="group_by" onchange="this.form.submit()">
                            <option value="none" {{ $groupBy == 'none' ? 'selected' : '' }}>None</option>
                            <option value="date" {{ $groupBy == 'date' ? 'selected' : '' }}>Date</option>
                            <option value="category" {{ $groupBy == 'category' ? 'selected' : '' }}>Category</option>
                        </select>
                    </div>
                    <div class="form-group mr-3 mb-2">
                        <label for="row" class="small mb-0">Row</label>
                        <select class="form-control form-control-sm" name="row" onchange="this.form.submit()">
                            <option value="10" {{ request('row') == '10' ? 'selected' : '' }}>10</option>
                            <option value="25" {{ request('row') == '25' ? 'selected' : '' }}>25</option>
                            <option value="50" {{ request('row', '50') == '50' ? 'selected' : '' }}>50</option>
                            <option value="100" {{ request('row') == '100' ? 'selected' : '' }}>100</option>
                        </select>
                    </div>
                    <div class="form-group mr-3 mb-2">
                        <label class="small mb-0" for="search">Search</label>
                        <div class="input-group input-group-sm flex-nowrap">
                            <input type="text" id="search" class="form-control form-control-sm" name="search" placeholder="Search expense" value="{{ request('search') }}">
                            <div class="input-group-append">
                                <button type="submit" class="input-group-text bg-primary"><i class="las la-search"></i></button>
                            </div>
                        </div>
                    </div>
                    <div class="form-group mb-2">
                        <button type="submit" class="btn btn-primary btn-sm"><i class="ri-search-line mr-1"></i> Apply</button>
                    </div>
                </div>
                    </form>
                </div>
            </div>
        </div>

            <div class="mb-3 p-0">
                <ul class="nav nav-tabs" role="tablist">
                    <li class="nav-item">
                    <a class="nav-link btn {{ $expenseTab === 'petty' ? 'btn-primary active' : 'btn-outline-secondary' }}"
                           href="{{ route('expenses.index', $pettyTabQuery) }}"
                           data-expense-tab-link>
                            Petty Expenses
                        </a>
                    </li>
                    <li class="nav-item">
                    <a class="nav-link btn {{ $expenseTab === 'purchase' ? 'btn-primary active' : 'btn-outline-secondary' }}"
                           href="{{ route('expenses.index', $purchaseTabQuery) }}"
                           data-expense-tab-link>
                            Purchase Expenses
                        </a>
                    </li>
                </ul>
            </div>

        </div>

        <div class="col-lg-12 expenses-tabs-panel">
            <form id="expenses-bulk-delete-form" method="post" action="{{ route('expenses.bulk-delete', request()->query()) }}">
                @csrf
                <div id="bulk-delete-expense-ids-container"></div>
            </form>
            @php
                $expenseTotal = $expenseTotal ?? 0;
                $expenseCount = $expenseCount ?? 0;
            @endphp
            <div class="row mb-2 align-items-center">
                <div class="col-md-6">
                    <button
                        type="button"
                        class="btn btn-warning btn-sm {{ $disableDelete ? 'disabled' : '' }}"
                        id="btn-delete-selected-expenses"
                        title="{{ $disableDelete ? 'Disabled in Purchase Expenses tab' : 'Delete selected expenses' }}"
                        {{ $disableDelete ? 'disabled' : '' }}
                    >
                        <i class="ri-delete-bin-line mr-1"></i>Delete selected
                    </button>
                </div>
                <div class="col-md-6 text-md-right">
                    <div class="card border-0 shadow-sm overflow-hidden d-inline-block" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); border-radius: 12px;">
                        <div class="card-body py-3 px-4">
                            <div class="d-flex flex-wrap align-items-center justify-content-between">
                                <div class="d-flex align-items-center">
                                    <i class="fas fa-money-bill-wave text-white mr-3" style="font-size: 1.35rem;"></i>
                                    <div>
                                        <span class="text-white-50 small text-uppercase d-block">Total expenses in range</span>
                                        <span class="text-white h5 mb-0 font-weight-bold">{{ number_format($expenseTotal, 2) }}</span>
                                    </div>
                                </div>
                                <div class="text-right">
                                    <span class="badge badge-light text-dark px-3 py-2">{{ $expenseCount }} {{ Str::plural('expense', $expenseCount) }}</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="table-responsive rounded mb-3">
                <table class="table mb-0">
                    <thead class="bg-white text-uppercase">
                        <tr class="ligth ligth-data">
                            <th style="width: 40px;">
                                <input type="checkbox" id="expense-select-all" title="Select all on this page" {{ $disableDelete ? 'disabled' : '' }}>
                            </th>
                            <th>No.</th>
                            <th>@sortablelink('expense.expense_title', 'Category')</th>
                            @if($usePurchaseDesign)
                                <th>Purchase</th>
                            @endif
                            <th>@sortablelink('description', 'Description')</th>
                            <th>@sortablelink('date', 'Date')</th>
                            <th class="text-right">@sortablelink('activity_cost', 'Cost')</th>
                            <th>Type</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody class="ligth-body" id="expense-table-body">
                        @if($groupBy === 'none')
                            @forelse ($expenses as $expense)
                                <tr>
                                    <td>
                                        @unless($expense->is_system ?? false)
                                            <input type="checkbox" class="expense-row-cb" value="{{ $expense->id }}" data-cost="{{ $expense->activity_cost }}" {{ $disableDelete ? 'disabled' : '' }}>
                                        @endunless
                                    </td>
                                    <td>{{ (($expenses->currentPage() - 1) * $expenses->perPage()) + $loop->iteration }}</td>
                                    <td>{{ $expense->expense?->expense_title ?? '—' }}</td>
                                    @if($usePurchaseDesign)
                                        <td>
                                            @if($expense->purchase)
                                                <a href="{{ route('purchases.show', $expense->purchase->id) }}" target="_blank" rel="noopener noreferrer">
                                                    {{ $expense->purchase->purchase_no ?? $expense->purchase->id }}
                                                </a>
                                            @else
                                                —
                                            @endif
                                        </td>
                                    @endif
                                    <td>{{ Str::limit($expense->description, 20) }}</td>
                                    <td>{{ $expense->date }}</td>
                                    <td class="text-right">{{ $expense->activity_cost }}</td>
                                    <td>
                                        @if($expense->is_system ?? false)
                                            <span class="badge badge-secondary">System</span>
                                        @else
                                            <span class="badge badge-primary">Manual</span>
                                        @endif
                                    </td>
                                    <td>
                                        <div class="d-flex align-items-center list-action">
                                            <a class="btn btn-info mr-2" data-toggle="tooltip" data-placement="top" title="View"
                                               href="{{ route('expenses.show', $expense->id) }}"><i class="ri-eye-line mr-0"></i></a>
                                            @unless($expense->is_system ?? false)
                                                <form action="{{ route('expenses.destroy', $expense->id) }}" method="POST" class="d-inline">
                                                    @method('delete')
                                                    @csrf
                                                    @foreach(request()->only(['date_filter', 'start_date', 'end_date', 'group_by', 'row', 'search', 'expense_tab']) as $key => $val)
                                                        @if($val !== null && $val !== '')
                                                            <input type="hidden" name="redirect_query_{{ $key }}" value="{{ $val }}">
                                                        @endif
                                                    @endforeach
                                                    <button
                                                        type="submit"
                                                        class="btn btn-warning mr-2 border-none {{ $disableDelete ? 'disabled' : '' }}"
                                                        onclick="return confirm('Are you sure you want to delete this record?')"
                                                        data-toggle="tooltip"
                                                        data-placement="top"
                                                        title="{{ $disableDelete ? 'Disabled in Purchase Expenses tab' : 'Delete' }}"
                                                        {{ $disableDelete ? 'disabled' : '' }}
                                                    >
                                                        <i class="ri-delete-bin-line mr-0"></i>
                                                    </button>
                                                </form>
                                            @endunless
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="{{ $noDataColspan }}" class="text-center">
                                        <div class="alert text-white bg-danger" role="alert">
                                            <div class="iq-alert-text">No Expenses Found.</div>
                                            <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                                            <i class="ri-close-line"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            @endforelse
                        @else
                            @php
                                $collection = $expenses->getCollection();
                                if ($groupBy === 'date') {
                                    $grouped = $collection->groupBy('date');
                                } else {
                                    $grouped = $collection->groupBy(function ($item) {
                                        return $item->expense?->expense_title ?? 'Uncategorized';
                                    });
                                }
                                $baseSerial = ($expenses->currentPage() - 1) * $expenses->perPage();
                            @endphp
                            @php $cumulative = 0; @endphp
                            @forelse ($grouped as $groupKey => $groupItems)
                                <tr class="table-secondary font-weight-bold">
                                    <td colspan="8">{{ $groupBy === 'date' ? $groupKey : $groupKey }}</td>
                                </tr>
                                @foreach ($groupItems as $expense)
                                @php $cumulative++; @endphp
                                <tr>
                                    <td>
                                        @unless($expense->is_system ?? false)
                                            <input type="checkbox" class="expense-row-cb" value="{{ $expense->id }}" data-cost="{{ $expense->activity_cost }}" {{ $disableDelete ? 'disabled' : '' }}>
                                        @endunless
                                    </td>
                                    <td>{{ $baseSerial + $cumulative }}</td>
                                    <td>{{ $expense->expense?->expense_title ?? '—' }}</td>
                                    <td>{{ Str::limit($expense->description, 20) }}</td>
                                    <td>{{ $expense->date }}</td>
                                    <td class="text-right">{{ $expense->activity_cost }}</td>
                                    <td>
                                        @if($expense->is_system ?? false)
                                            <span class="badge badge-secondary">System</span>
                                        @else
                                            <span class="badge badge-primary">Manual</span>
                                        @endif
                                    </td>
                                    <td>
                                        <div class="d-flex align-items-center list-action">
                                            <a class="btn btn-info mr-2" data-toggle="tooltip" data-placement="top" title="View"
                                               href="{{ route('expenses.show', $expense->id) }}"><i class="ri-eye-line mr-0"></i></a>
                                            @unless($expense->is_system ?? false)
                                                <form action="{{ route('expenses.destroy', $expense->id) }}" method="POST" class="d-inline">
                                                    @method('delete')
                                                    @csrf
                                                    @foreach(request()->only(['date_filter', 'start_date', 'end_date', 'group_by', 'row', 'search', 'expense_tab']) as $key => $val)
                                                        @if($val !== null && $val !== '')
                                                            <input type="hidden" name="redirect_query_{{ $key }}" value="{{ $val }}">
                                                        @endif
                                                    @endforeach
                                                    <button
                                                        type="submit"
                                                        class="btn btn-warning mr-2 border-none {{ $disableDelete ? 'disabled' : '' }}"
                                                        onclick="return confirm('Are you sure you want to delete this record?')"
                                                        data-toggle="tooltip"
                                                        data-placement="top"
                                                        title="{{ $disableDelete ? 'Disabled in Purchase Expenses tab' : 'Delete' }}"
                                                        {{ $disableDelete ? 'disabled' : '' }}
                                                    >
                                                        <i class="ri-delete-bin-line mr-0"></i>
                                                    </button>
                                                </form>
                                            @endunless
                                        </div>
                                    </td>
                                </tr>
                                @endforeach
                                <tr class="table-light font-weight-bold">
                                    <td colspan="5" class="text-right">Total</td>
                                    <td class="text-right">{{ number_format($groupItems->sum('activity_cost'), 2) }}</td>
                                    <td colspan="2"></td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="8" class="text-center">
                                        <div class="alert text-white bg-danger" role="alert">
                                            <div class="iq-alert-text">No Expenses Found.</div>
                                            <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                                            <i class="ri-close-line"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            @endforelse
                        @endif
                    </tbody>
                </table>
            </div>

            <!-- Pagination Links -->
            <div class="pagination" id="pagination">
                {{ $expenses->appends(request()->query())->links() }}
            </div>
        </div>
    </div>
    <!-- Page end  -->
</div>

{{-- Bulk delete confirmation modal --}}
<div class="modal fade" id="bulkDeleteExpenseModal" tabindex="-1" role="dialog" aria-labelledby="bulkDeleteExpenseModalLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="bulkDeleteExpenseModalLabel">Confirm delete</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <p class="mb-2">Are you sure you want to delete the selected expense(s)?</p>
                <div id="bulk-delete-summary" class="alert alert-light border mb-0">
                    <strong id="bulk-delete-count">0</strong> expense(s) selected &mdash; Total: <strong id="bulk-delete-total">0.00</strong>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-danger" id="bulk-delete-confirm-btn"><i class="ri-delete-bin-line mr-1"></i>Delete</button>
            </div>
        </div>
    </div>
</div>

{{-- Page loading overlay (for tab switching / filter apply) --}}
<style>
    #expenses-page-loading-overlay {
        position: fixed;
        inset: 0;
        background: rgba(0, 0, 0, 0.25);
        z-index: 9999;
        display: none;
        align-items: center;
        justify-content: center;
    }

    /* jQuery UI style tabs look */
    #expenses-page-loading-overlay .spinner-border { width: 3rem; height: 3rem; }

    .nav.nav-tabs[role="tablist"]{
        display: inline-flex;
        gap: 0;
        border-bottom: 0;
        border-radius: 4px 4px 0 0;
        border: 1px solid #c5c5c5;
        background: #f7f7f7;
        padding: 0 0.5em 0 0.5em;
        margin: 0;
    }
    .nav.nav-tabs[role="tablist"] .nav-item{
        margin: 0 2px 0 0;
    }
    .nav.nav-tabs[role="tablist"] .nav-link[data-expense-tab-link]{
        border: 1px solid transparent;
        border-bottom: 0;
        background: transparent !important;
        color: #444 !important;
        border-radius: 0 !important;
        padding: 0.55em 1.1em !important;
        font-weight: 600;
    }
    .nav.nav-tabs[role="tablist"] .nav-link[data-expense-tab-link]:hover{
        background: #ffffff !important;
    }
    .nav.nav-tabs[role="tablist"] .nav-link[data-expense-tab-link].active,
    .nav.nav-tabs[role="tablist"] .nav-link[data-expense-tab-link].btn-primary.active{
        background: #ffffff !important;
        border-color: #c5c5c5 !important;
        color: #222 !important;
        position: relative;
        top: 1px;
    }

    /* Panel border underneath the tab header */
    .expenses-tabs-panel{
        border: 1px solid #c5c5c5;
        border-top: 0;
        border-radius: 0 0 4px 4px;
        background: #ffffff;
        padding: 12px;
    }
</style>
<div id="expenses-page-loading-overlay" aria-hidden="true">
    <div class="spinner-border text-primary" role="status"></div>
</div>

@endsection
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script>
    function showExpensesPageLoading() {
        var el = document.getElementById('expenses-page-loading-overlay');
        if (!el) return;
        el.style.display = 'flex';
    }

    function toggleExpenseCustomDates() {
        var v = document.getElementById('date_filter').value;
        var startGroup = document.getElementById('expense_start_date_group');
        var endGroup = document.getElementById('expense_end_date_group');
        if (startGroup) startGroup.style.display = v === 'custom' ? 'block' : 'none';
        if (endGroup) endGroup.style.display = v === 'custom' ? 'block' : 'none';
    }
    document.addEventListener('DOMContentLoaded', function() {
        toggleExpenseCustomDates();

        // Show spinner on tab switching / filter / pagination (full page reload).
        document.querySelectorAll('a[data-expense-tab-link]').forEach(function (a) {
            a.addEventListener('click', function () {
                showExpensesPageLoading();
            });
        });

        var filterForm = document.getElementById('expenses-filter-form');
        if (filterForm) {
            filterForm.addEventListener('submit', function () {
                showExpensesPageLoading();
            });
        }

        document.querySelectorAll('#pagination a.page-link').forEach(function (a) {
            a.addEventListener('click', function () {
                showExpensesPageLoading();
            });
        });

        // Select all (current page only)
        var selectAll = document.getElementById('expense-select-all');
        var rowCbs = document.querySelectorAll('.expense-row-cb');
        if (selectAll) {
            selectAll.addEventListener('change', function() {
                rowCbs.forEach(function(cb) { cb.checked = selectAll.checked; });
            });
        }
        rowCbs.forEach(function(cb) {
            cb.addEventListener('change', function() {
                var checked = document.querySelectorAll('.expense-row-cb:checked');
                if (selectAll) selectAll.checked = checked.length === rowCbs.length;
            });
        });

        // Delete selected: validate then show modal
        var btnDeleteSelected = document.getElementById('btn-delete-selected-expenses');
        var modal = document.getElementById('bulkDeleteExpenseModal');
        var bulkCountEl = document.getElementById('bulk-delete-count');
        var bulkTotalEl = document.getElementById('bulk-delete-total');
        var bulkConfirmBtn = document.getElementById('bulk-delete-confirm-btn');
        var bulkForm = document.getElementById('expenses-bulk-delete-form');
        var idsContainer = document.getElementById('bulk-delete-expense-ids-container');

        if (btnDeleteSelected && modal) {
            btnDeleteSelected.addEventListener('click', function() {
                var checked = document.querySelectorAll('.expense-row-cb:checked');
                if (checked.length === 0) {
                    alert('Please select at least one expense.');
                    return;
                }
                var total = 0;
                var ids = [];
                checked.forEach(function(cb) {
                    ids.push(cb.value);
                    total += parseFloat(cb.getAttribute('data-cost')) || 0;
                });
                bulkCountEl.textContent = ids.length;
                bulkTotalEl.textContent = total.toFixed(2);
                bulkConfirmBtn.dataset.ids = ids.join(',');
                $(modal).modal('show');
            });
        }

        if (bulkConfirmBtn && bulkForm && idsContainer) {
            bulkConfirmBtn.addEventListener('click', function() {
                var ids = (bulkConfirmBtn.dataset.ids || '').split(',').filter(Boolean);
                idsContainer.innerHTML = '';
                ids.forEach(function(id) {
                    var input = document.createElement('input');
                    input.type = 'hidden';
                    input.name = 'expense_ids[]';
                    input.value = id;
                    idsContainer.appendChild(input);
                });
                $(modal).modal('hide');
                bulkForm.submit();
            });
        }
    });
    function deleteExpense(expenseId) {
        if (confirm('Are you sure you want to delete this record?')) {
            var form = document.createElement('form');
            form.method = 'POST';
            form.action = '/expenses/' + expenseId;
            var csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
            var csrfField = document.createElement('input');
            csrfField.type = 'hidden';
            csrfField.name = '_token';
            csrfField.value = csrfToken;
            form.appendChild(csrfField);

            var methodField = document.createElement('input');
            methodField.type = 'hidden';
            methodField.name = '_method';
            methodField.value = 'DELETE';
            form.appendChild(methodField);

            document.body.appendChild(form);
            form.submit();
        }
    }
</script>
