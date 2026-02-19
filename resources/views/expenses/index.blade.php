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
            @if (session()->has('error'))
                <div class="alert text-white bg-danger" role="alert">
                    <div class="iq-alert-text">{{ session('success') }}</div>
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
        @endphp
        <div class="col-lg-12">
            <form action="{{ route('expenses.index') }}" method="get" id="expenses-filter-form">
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
                        <div class="input-group input-group-sm">
                            <input type="text" id="search" class="form-control" name="search" placeholder="Search expense" value="{{ request('search') }}">
                            <div class="input-group-append">
                                <button type="submit" class="input-group-text bg-primary"><i class="las la-search"></i></button>
                            </div>
                        </div>
                    </div>
                    <div class="form-group mb-2">
                        <button type="submit" class="btn btn-primary btn-sm">Apply</button>
                    </div>
                </div>
            </form>
        </div>



        <div class="col-lg-12">
            <div class="table-responsive rounded mb-3">
                <table class="table mb-0">
                    <thead class="bg-white text-uppercase">
                        <tr class="ligth ligth-data">
                            <th>No.</th>
                            <th>@sortablelink('expense.expense_title', 'Category')</th>
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
                                    <td>{{ (($expenses->currentPage() - 1) * $expenses->perPage()) + $loop->iteration }}</td>
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
                                                <button type="submit" class="btn btn-warning mr-2 border-none" onclick="return confirm('Are you sure you want to delete this record?')" data-toggle="tooltip" data-placement="top" title="Delete"><i class="ri-delete-bin-line mr-0"></i></button>
                                            </form>
                                            @endunless
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="text-center">
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
                                    <td colspan="7">{{ $groupBy === 'date' ? $groupKey : $groupKey }}</td>
                                </tr>
                                @foreach ($groupItems as $expense)
                                @php $cumulative++; @endphp
                                <tr>
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
                                                <button type="submit" class="btn btn-warning mr-2 border-none" onclick="return confirm('Are you sure you want to delete this record?')" data-toggle="tooltip" data-placement="top" title="Delete"><i class="ri-delete-bin-line mr-0"></i></button>
                                            </form>
                                            @endunless
                                        </div>
                                    </td>
                                </tr>
                                @endforeach
                                <tr class="table-light font-weight-bold">
                                    <td colspan="4" class="text-right">Total</td>
                                    <td class="text-right">{{ number_format($groupItems->sum('activity_cost'), 2) }}</td>
                                    <td colspan="2"></td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="text-center">
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

@endsection
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script>
    function toggleExpenseCustomDates() {
        var v = document.getElementById('date_filter').value;
        var startGroup = document.getElementById('expense_start_date_group');
        var endGroup = document.getElementById('expense_end_date_group');
        if (startGroup) startGroup.style.display = v === 'custom' ? 'block' : 'none';
        if (endGroup) endGroup.style.display = v === 'custom' ? 'block' : 'none';
    }
    document.addEventListener('DOMContentLoaded', function() {
        toggleExpenseCustomDates();
    });
    function deleteExpense(expenseId) {
        if (confirm('Are you sure you want to delete this record?')) {
            var form = document.createElement('form');
            form.method = 'POST';
            form.action = `/expenses/${expenseId}`;
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
