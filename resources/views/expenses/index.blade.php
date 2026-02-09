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
                <a href="{{ route('expenses.create') }}" class="btn btn-primary add-list">Add Expense</a>
                </div>
            </div>
        </div>

        <div class="col-lg-12">
            <form action="{{ route('expenses.index') }}" method="get">
                <div class="d-flex flex-wrap align-items-center justify-content-between">
                    <div class="form-group row">
                        <label for="row" class="col-sm-3 align-self-center">Row:</label>
                        <div class="col-sm-9">
                            <select class="form-control" name="row" onchange="this.form.submit()">
                                <option value="10" @if(request('row') == '10')selected="selected"@endif>10</option>
                                <option value="25" @if(request('row') == '25')selected="selected"@endif>25</option>
                                <option value="50" @if(request('row', '50') == '50')selected="selected"@endif>50</option>
                                <option value="100" @if(request('row') == '100')selected="selected"@endif>100</option>
                            </select>
                        </div>
                    </div>

                    <div class="form-group row">
                        <label class="control-label col-sm-3 align-self-center" for="search">Search:</label>
                        <div class="input-group col-sm-8">
                            <input type="text" id="search" class="form-control" name="search" placeholder="Search expense" value="{{ request('search') }}">
                            <div class="input-group-append">
                                <button type="button" id="search-btn" class="input-group-text bg-primary"><i class="las la-search"></i></button>
                                <a href="{{ route('expenses.index') }}" class="input-group-text bg-danger"><i class="las la-trash"></i></a>
                            </div>
                        </div>
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
                            <th>@sortablelink('title', 'Title')</th>
                            <th>@sortablelink('description', 'Description')</th>
                            <th>@sortablelink('date', 'Date')</th>
                            <th>@sortablelink('activity_cost', 'Cost')</th>
                            <th>Type</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody class="ligth-body" id="expense-table-body">
                        @forelse ($expenses as $expense)
                            <tr>
                                <td>{{ (($expenses->currentPage() - 1) * $expenses->perPage()) + $loop->iteration }}</td>
                                <td>{{ $expense->title }}</td>
                                <td>{{ Str::limit($expense->description, 20) }}</td>
                                <td>{{ $expense->date }}</td>
                                <td>{{ $expense->activity_cost }}</td>
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
                                        <a class="btn btn-success mr-2" data-toggle="tooltip" data-placement="top" title="Edit"
                                           href="{{ route('expenses.edit', $expense->id) }}"><i class="ri-pencil-line mr-0"></i></a>
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
    $(document).ready(function() {
        $('#search-btn').on('click', function() {
            var searchQuery = $('#search').val();

            $.ajax({
                url: "{{ route('expenses.search') }}",
                method: 'GET',
                data: { search: searchQuery },
                success: function(response) {
                    var expenseList = $('#expense-table-body');
                    expenseList.empty();

                    if (response.expenses.data.length > 0) {
                        $.each(response.expenses.data, function(index, expense) {
                            var currentPage = response.expenses.current_page;
                            var perPage = response.expenses.per_page;
                            var serialNumber = (currentPage - 1) * perPage + index + 1;
                            var truncatedDescription = expense.description.length > 20 ? expense.description.substring(0, 20) + '...' : expense.description;

                            var isSystem = expense.is_system === true || expense.is_system === 1;
                            var typeBadge = isSystem ? '<span class="badge badge-secondary">System</span>' : '<span class="badge badge-primary">Manual</span>';
                            var actionButtons = '<div class="d-flex align-items-center list-action">' +
                                '<a class="btn btn-info mr-2" data-toggle="tooltip" data-placement="top" title="View" href="/expenses/' + expense.id + '"><i class="ri-eye-line mr-0"></i></a>';
                            if (!isSystem) {
                                actionButtons += '<a class="btn btn-success mr-2" data-toggle="tooltip" data-placement="top" title="Edit" href="/expenses/' + expense.id + '/edit"><i class="ri-pencil-line mr-0"></i></a>' +
                                    '<button type="button" class="btn btn-warning mr-2" onclick="deleteExpense(' + expense.id + ')" data-toggle="tooltip" data-placement="top" title="Delete"><i class="ri-delete-bin-line mr-0"></i></button>';
                            }
                            actionButtons += '</div>';

                            var row = `
                                <tr>
                                    <td>${serialNumber}</td>
                                    <td>${expense.title}</td>
                                    <td>${truncatedDescription}</td>
                                    <td>${expense.date}</td>
                                    <td>${expense.activity_cost}</td>
                                    <td>${typeBadge}</td>
                                    <td>${actionButtons}</td>
                                </tr>
                            `;
                            $('#expense-table-body').append(row);
                        });
                    } else {
                        expenseList.append('<tr><td colspan="7" class="text-center">No Expenses Found.</td></tr>');
                    }

                    $('#pagination').html(response.expenses.links);
                },
                error: function() {
                    alert("Error occurred while searching. Please try again.");
                }
            });
        });

        $('#search').on('input', function() {
            $('#search-btn').click();
        });
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
