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
            @if (session()->has('error'))
                <div class="alert text-white bg-danger" role="alert">
                    <div class="iq-alert-text">{{ session('error') }}</div>
                    <button type="button" class="close" data-dismiss="alert" aria-label="Close"><i class="ri-close-line"></i></button>
                </div>
            @endif
            <div class="d-flex flex-wrap align-items-center justify-content-between mb-4">
                <div>
                    <h4 class="mb-3">Expense Categories</h4>
                    <p class="mb-0">Manage expense categories used when recording expenses.</p>
                </div>
                <div>
                    <button type="button" class="btn btn-primary add-list" data-toggle="modal" data-target="#expenseCategoryModal" data-mode="add"><i class="fas fa-plus mr-3"></i>Add Category</button>
                    <a href="{{ route('expense-categories.index') }}" class="btn btn-danger add-list"><i class="las la-trash mr-3"></i>Clear Search</a>
                </div>
            </div>
        </div>

        <div class="col-lg-12">
            <form action="{{ route('expense-categories.index') }}" method="get">
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
                            <div class="input-group">
                                <input type="text" id="search" class="form-control" name="search" placeholder="Search category" value="{{ request('search') }}">
                                <div class="input-group-append">
                                    <button type="submit" class="input-group-text bg-primary"><i class="las la-search"></i></button>
                                </div>
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
                            <th>@sortablelink('expense_title', 'Category Title')</th>
                            <th>Entries</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody class="ligth-body">
                        @forelse ($categories as $category)
                        <tr>
                            <td>{{ (($categories->currentPage() - 1) * $categories->perPage()) + $loop->iteration }}</td>
                            <td>{{ $category->expense_title }}</td>
                            <td>{{ $category->activities_count }}</td>
                            <td>
                                <div class="d-flex align-items-center list-action">
                                    <button type="button" class="btn btn-sm btn-info mr-2 view-entries-btn" data-id="{{ $category->id }}" data-title="{{ $category->expense_title }}" title="View expense entries">
                                        <i class="ri-eye-line mr-0"></i> View entries
                                    </button>
                                    <button type="button" class="btn btn-sm btn-success mr-2 edit-category-btn" data-id="{{ $category->id }}" data-title="{{ $category->expense_title }}" title="Edit">
                                        <i class="ri-pencil-line mr-0"></i>
                                    </button>
                                    <form action="{{ route('expense-categories.destroy', $category) }}" method="POST" class="d-inline expense-category-delete-form">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-sm btn-warning mr-2" onclick="return confirm('Are you sure you want to delete this category? It can only be deleted if no expense entries use it.');" title="Delete">
                                            <i class="ri-delete-bin-line mr-0"></i>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="4" class="text-center">No expense categories found.</td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            {{ $categories->appends(request()->query())->links() }}
        </div>
    </div>
</div>

@include('partials.expense-category-modal')

{{-- Popup: View expense entries (AJAX) --}}
<div class="modal fade" id="expenseEntriesModal" tabindex="-1" role="dialog" aria-labelledby="expenseEntriesModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="expenseEntriesModalLabel">Expense entries</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <div id="expense-entries-spinner" class="text-center py-5 d-none">
                    <div class="spinner-border text-primary" role="status" style="width: 3rem; height: 3rem;">
                        <span class="sr-only">Loading...</span>
                    </div>
                    <p class="mt-2 mb-0">Loading entries...</p>
                </div>
                <div id="expense-entries-content" class="d-none">
                    <div class="table-responsive">
                        <table class="table table-sm table-bordered mb-0">
                            <thead class="thead-light">
                                <tr>
                                    <th>Date</th>
                                    <th>Title</th>
                                    <th>Description</th>
                                    <th class="text-right">Cost</th>
                                    <th>Payment</th>
                                </tr>
                            </thead>
                            <tbody id="expense-entries-tbody"></tbody>
                        </table>
                    </div>
                    <p id="expense-entries-empty" class="text-muted mb-0 mt-2 d-none">No entries in this category.</p>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@section('specificpagescripts')
<script>
(function() {
    var modal = $('#expenseCategoryModal');
    var form = document.getElementById('expense-category-form');
    var titleInput = document.getElementById('expense_category_title');
    var idInput = document.getElementById('expense_category_id');
    var submitBtn = document.getElementById('expense-category-modal-submit');
    var modalTitle = document.getElementById('expenseCategoryModalLabel');
    var errorEl = document.getElementById('expense-category-modal-error');
    var successEl = document.getElementById('expense-category-modal-success');

    function showSuccess(msg) {
        errorEl.classList.add('d-none');
        successEl.textContent = msg || '';
        successEl.classList.toggle('d-none', !msg);
    }
    function showError(msg) {
        successEl.classList.add('d-none');
        errorEl.textContent = msg || '';
        errorEl.classList.toggle('d-none', !msg);
    }

    modal.on('show.bs.modal', function(e) {
        // Only clear when opened via "Add Category" button (data-mode="add"); leave fields when opened via Edit
        if (e.relatedTarget && e.relatedTarget.getAttribute('data-mode') === 'add') {
            idInput.value = '';
            titleInput.value = '';
            modalTitle.textContent = 'Add Expense Category';
        }
    });

    document.querySelectorAll('.edit-category-btn').forEach(function(btn) {
        btn.addEventListener('click', function() {
            idInput.value = this.getAttribute('data-id');
            titleInput.value = this.getAttribute('data-title') || '';
            modalTitle.textContent = 'Edit Expense Category';
            modal.modal('show');
        });
    });

    submitBtn.addEventListener('click', function() {
        var title = titleInput.value.trim();
        var id = idInput.value;
        if (!title) {
            showError('Category title is required.');
            return;
        }
        showError('');
        showSuccess('');
        submitBtn.disabled = true;

        var url = id
            ? '{{ url("expense-categories") }}/' + id
            : '{{ route("expense-categories.store") }}';
        var method = id ? 'PUT' : 'POST';
        var body = { expense_title: title, _token: form.querySelector('input[name="_token"]').value };
        if (id) body._method = 'PUT';

        fetch(url, {
            method: method,
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': form.querySelector('input[name="_token"]').value
            },
            body: JSON.stringify(body)
        })
        .then(function(r) { return r.json().then(function(data) { return { ok: r.ok, status: r.status, data: data }; }); })
        .then(function(res) {
            if (res.ok && res.data.success) {
                showSuccess(res.data.message || 'Saved.');
                setTimeout(function() {
                    modal.modal('hide');
                    window.location.reload();
                }, 800);
            } else {
                showError(res.data.message || (res.data.errors && Object.values(res.data.errors).flat().join(' ')) || 'Request failed.');
            }
        })
        .catch(function() {
            showError('Network error. Please try again.');
        })
        .finally(function() {
            submitBtn.disabled = false;
        });
    });

    modal.on('hidden.bs.modal', function() {
        showSuccess('');
        showError('');
    });

    // Delete form: if server returns 422 (validation/block), show message and prevent default
    document.querySelectorAll('.expense-category-delete-form').forEach(function(f) {
        f.addEventListener('submit', function(e) {
            e.preventDefault();
            var formEl = this;
            fetch(formEl.action, {
                method: 'DELETE',
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': formEl.querySelector('input[name="_token"]').value
                }
            })
            .then(function(r) { return r.json().then(function(data) { return { ok: r.ok, status: r.status, data: data }; }); })
            .then(function(res) {
                if (res.ok && res.data.success) {
                    window.location.reload();
                } else {
                    alert(res.data.message || 'Cannot delete this category.');
                }
            })
            .catch(function() {
                formEl.submit();
            });
            return false;
        });
    });

    // View entries popup
    var entriesModal = $('#expenseEntriesModal');
    var entriesSpinner = document.getElementById('expense-entries-spinner');
    var entriesContent = document.getElementById('expense-entries-content');
    var entriesTbody = document.getElementById('expense-entries-tbody');
    var entriesEmpty = document.getElementById('expense-entries-empty');

    document.querySelectorAll('.view-entries-btn').forEach(function(btn) {
        btn.addEventListener('click', function() {
            var id = this.getAttribute('data-id');
            var title = this.getAttribute('data-title');
            document.getElementById('expenseEntriesModalLabel').textContent = 'Entries: ' + title;
            entriesSpinner.classList.remove('d-none');
            entriesContent.classList.add('d-none');
            entriesTbody.innerHTML = '';
            entriesEmpty.classList.add('d-none');
            entriesModal.modal('show');

            fetch('{{ url("expense-categories") }}/' + id + '/entries', {
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
            })
            .then(function(r) { return r.json(); })
            .then(function(data) {
                entriesSpinner.classList.add('d-none');
                entriesContent.classList.remove('d-none');
                if (data.entries && data.entries.length > 0) {
                    data.entries.forEach(function(entry) {
                        var tr = document.createElement('tr');
                        tr.innerHTML =
                            '<td>' + (entry.date || '–') + '</td>' +
                            '<td>' + (entry.title || '–') + '</td>' +
                            '<td>' + (entry.description || '–') + '</td>' +
                            '<td class="text-right">' + (entry.activity_cost != null ? Number(entry.activity_cost) : '–') + '</td>' +
                            '<td>' + (entry.payment_method || '–') + '</td>';
                        entriesTbody.appendChild(tr);
                    });
                } else {
                    entriesEmpty.classList.remove('d-none');
                }
            })
            .catch(function() {
                entriesSpinner.classList.add('d-none');
                entriesContent.classList.remove('d-none');
                entriesTbody.innerHTML = '<tr><td colspan="5" class="text-center text-danger">Failed to load entries.</td></tr>';
            });
        });
    });
})();
</script>
@endsection
