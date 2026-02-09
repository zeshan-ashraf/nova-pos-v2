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
            <div class="d-flex flex-wrap align-items-center justify-content-between mb-4">
                <div>
                    <h4 class="mb-3">Category List</h4>
                    <p class="mb-0">A Category dashboard lets you easily gather and visualize Category data from optimizing <br>
                        the Category experience, ensuring Category retention. </p>
                </div>
                <div>
                <button type="button" class="btn btn-primary add-list" data-toggle="modal" data-target="#addCategoryModal"><i class="fas fa-plus mr-3"></i>Add Category</button>
                <a href="{{ route('categories.index') }}" class="btn btn-danger add-list"><i class="las la-trash mr-3"></i>Clear Search</a>
                </div>
            </div>
        </div>

        <div class="col-lg-12">
            <form action="{{ route('categories.index') }}" method="get">
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
                            <th>@sortablelink('name')</th>
                            <th>@sortablelink('slug')</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody class="ligth-body">
                        @forelse ($categories as $category)
                        <tr>
                            <td>{{ (($categories->currentPage() * $categories->perPage()) - $categories->perPage()) + $loop->iteration  }}</td>
                            <td>{{ $category->name }}</td>
                            <td>{{ $category->slug }}</td>
                            <td>
                                <div class="d-flex align-items-center list-action">
                                    <a class="badge bg-success mr-2" data-toggle="tooltip" data-placement="top" title="" data-original-title="Edit"
                                        href="{{ route('categories.edit', $category->slug) }}""><i class="ri-pencil-line mr-0"></i>
                                    </a>
                                    <form action="{{ route('categories.destroy', $category->slug) }}" method="POST" style="margin-bottom: 5px">
                                        @method('delete')
                                        @csrf
                                        <button type="submit" class="badge bg-warning mr-2 border-none" onclick="return confirm('Are you sure you want to delete this record?')" data-toggle="tooltip" data-placement="top" title="" data-original-title="Delete"><i class="ri-delete-bin-line mr-0"></i></button>
                                    </form>
                                </div>
                            </td>
                        </tr>

                        @empty
                        <div class="alert text-white bg-danger" role="alert">
                            <div class="iq-alert-text">Data not Found.</div>
                            <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                            <i class="ri-close-line"></i>
                            </button>
                        </div>
                        @endforelse
                    </tbody>
                </table>
            </div>
            {{ $categories->appends(request()->query())->links() }}
        </div>
    </div>
    <!-- Page end  -->
</div>

{{-- Add Category modal (AJAX) --}}
<div class="modal fade" id="addCategoryModal" tabindex="-1" role="dialog" aria-labelledby="addCategoryModalLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="addCategoryModalLabel">Add Category</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <div id="add-category-error" class="alert alert-danger d-none" role="alert"></div>
                <div id="add-category-success" class="alert alert-success d-none" role="alert"></div>
                <form id="add-category-form">
                    @csrf
                    <div class="form-group">
                        <label for="category_name">Category Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="category_name" name="name" required placeholder="Enter category name">
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                <button type="button" class="btn btn-primary" id="add-category-submit">Save</button>
            </div>
        </div>
    </div>
</div>

@endsection

@section('specificpagescripts')
<script>
(function() {
    var modal = $('#addCategoryModal');
    var form = document.getElementById('add-category-form');
    var successEl = document.getElementById('add-category-success');
    var errorEl = document.getElementById('add-category-error');

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

    document.getElementById('add-category-submit').addEventListener('click', function() {
        var name = document.getElementById('category_name').value.trim();
        if (!name) {
            showError('Category name is required.');
            return;
        }
        showError('');
        this.disabled = true;
        fetch('{{ route("categories.store") }}', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': document.querySelector('#add-category-form input[name="_token"]').value
            },
            body: JSON.stringify({ name: name, _token: document.querySelector('#add-category-form input[name="_token"]').value })
        })
        .then(function(r) { return r.json().then(function(data) { return { ok: r.ok, data: data }; }); })
        .then(function(res) {
            if (res.ok && res.data.success) {
                showSuccess(res.data.message || 'Category has been created!');
                setTimeout(function() {
                    modal.modal('hide');
                    window.location.reload();
                }, 5000);
            } else {
                showError(res.data.message || (res.data.errors && Object.values(res.data.errors).flat().join(' ')) || 'Request failed.');
            }
        })
        .catch(function() {
            showError('Network error. Please try again.');
        })
        .finally(function() {
            document.getElementById('add-category-submit').disabled = false;
        });
    });

    modal.on('hidden.bs.modal', function() {
        showSuccess('');
        showError('');
        document.getElementById('category_name').value = '';
    });

    @if(!empty($open_modal))
    $(function() { modal.modal('show'); });
    @endif
})();
</script>
@endsection
