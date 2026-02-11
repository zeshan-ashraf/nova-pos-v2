@extends('dashboard.body.main')

@section('specificpagestyles')
    <script src="https://code.jquery.com/jquery-3.3.1.min.js"></script>
    <script src="https://unpkg.com/gijgo@1.9.14/js/gijgo.min.js" type="text/javascript"></script>
    <link href="https://unpkg.com/gijgo@1.9.14/css/gijgo.min.css" rel="stylesheet" type="text/css" />
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
                        <h4 class="card-title">Edit Expense</h4>
                    </div>
                </div>

                <div class="card-body">
                    <form action="{{ route('expenses.update', $expense->id) }}" method="POST" enctype="multipart/form-data">
                        @csrf
                        @method('PUT')

                        <!-- begin: Expense Category -->
                        <div class="form-group row">
                            <div class="col-md-12">
                                <div class="d-flex justify-content-between align-items-center mb-1">
                                    <label for="expense_id" class="mb-0">Expense Category <span class="text-danger">*</span></label>
                                    @if (auth()->user()->can('expense-categories.menu'))
                                    <button type="button" class="btn btn-sm btn-outline-primary" data-toggle="modal" data-target="#expenseCategoryModal">Add category</button>
                                    @endif
                                </div>
                                <select class="form-control expense-category-select @error('expense_id') is-invalid @enderror" id="expense_id" name="expense_id" required>
                                    <option value="">Select category</option>
                                    @foreach($expenseCategories ?? [] as $cat)
                                    <option value="{{ $cat->id }}" {{ old('expense_id', $expense->expense_id) == $cat->id ? 'selected' : '' }}>{{ $cat->expense_title }}</option>
                                    @endforeach
                                </select>
                                @error('expense_id')
                                <div class="invalid-feedback">
                                    {{ $message }}
                                </div>
                                @enderror
                            </div>
                        </div>
                        <!-- end: Expense Category -->

                        <!-- begin: Input Description -->
                        <div class="form-group row">
                            <div class="col-md-12">
                                <label for="description">Expense Description</label>
                                <textarea class="form-control @error('description') is-invalid @enderror" id="description" name="description" rows="4">{{ old('description', $expense->description) }}</textarea>
                                @error('description')
                                <div class="invalid-feedback">
                                    {{ $message }}
                                </div>
                                @enderror
                            </div>
                        </div>
                        <!-- end: Input Description -->

                        <!-- begin: Input Date -->
                        <div class="form-group row">
                            <div class="col-md-6">
                                <label for="date">Expense Date <span class="text-danger">*</span></label>
                                <input type="text" class="form-control @error('date') is-invalid @enderror" id="date" name="date" value="{{ old('date', $expense->date) }}" required>
                                @error('date')
                                <div class="invalid-feedback">
                                    {{ $message }}
                                </div>
                                @enderror
                            </div>
                        </div>
                        <!-- end: Input Date -->

                        <!-- begin: Input Images -->
                        <div class="form-group row">
                            <div class="col-md-6">
                                <label for="image_1">Image 1 <span class="text-danger">*</span></label>
                                <input type="file" class="custom-file-input @error('image_1') is-invalid @enderror" id="image_1" name="image_1" accept="image" onchange="previewImages();">
                                <label class="custom-file-label" for="image_1">Choose file</label>
                                @error('image_1')
                                <div class="invalid-feedback">
                                    {{ $message }}
                                </div>
                                @enderror
                              <div id="image-preview-1" class="mt-2">
                                    <img class="avatar-60 rounded" id="preview-image-1"
                                        src="{{ is_array($expense->images) && isset($expense->images[0])
                                                ? asset('storage/' . $expense->images[0])
                                                : asset('assets/images/product/default.webp') }}"
                                        alt="preview">
                                </div>
                            </div>

                            <div class="col-md-6">
                                <label for="image_2">Image 2</label>
                                <input type="file" class="custom-file-input @error('image_2') is-invalid @enderror" id="image_2" name="image_2" accept="image" onchange="previewImages();">
                                <label class="custom-file-label" for="image_2">Choose file</label>
                                @error('image_2')
                                <div class="invalid-feedback">
                                    {{ $message }}
                                </div>
                                @enderror
                              <div id="image-preview-2" class="mt-2">
                                    <img class="avatar-60 rounded" id="preview-image-2"
                                        src="{{ is_array($expense->images) && isset($expense->images[1])
                                                ? asset('storage/' . $expense->images[1])
                                                : asset('assets/images/product/default.webp') }}"
                                        alt="preview">
                                </div>
                            </div>
                        </div>
                        <!-- end: Input Images -->

                        <!-- begin: Input Cost -->
                        <div class="form-group row">
                            <div class="col-md-6">
                                <label for="activity_cost">Cost <span class="text-danger">*</span></label>
                                <input type="text" class="form-control @error('activity_cost') is-invalid @enderror" id="activity_cost" name="activity_cost" value="{{ old('activity_cost', $expense->activity_cost) }}" required>
                                @error('activity_cost')
                                <div class="invalid-feedback">
                                    {{ $message }}
                                </div>
                                @enderror
                            </div>
                        </div>
                        <!-- end: Input Cost -->

                        <!-- Submit Button -->
                        <div class="mt-2">
                            <button type="submit" class="btn btn-primary mr-2">Update</button>
                            <a class="btn bg-danger" href="{{ route('expenses.index') }}">Cancel</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    // Initialize Datepicker for the expense date field
    $('#date').datepicker({
        uiLibrary: 'bootstrap4',
        format: 'yyyy-mm-dd'
    });

    // Preview Images Function
    function previewImages() {
        var preview1 = document.querySelector('#preview-image-1');
        var preview2 = document.querySelector('#preview-image-2');
        var file1 = document.querySelector('#image_1').files[0];
        var file2 = document.querySelector('#image_2').files[0];

        if (file1) {
            var reader1 = new FileReader();
            reader1.onload = function(e) {
                preview1.src = e.target.result;
            }
            reader1.readAsDataURL(file1);
        }

        if (file2) {
            var reader2 = new FileReader();
            reader2.onload = function(e) {
                preview2.src = e.target.result;
            }
            reader2.readAsDataURL(file2);
        }
    }
</script>

<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script>
$(document).ready(function() {
    $('.expense-category-select').select2({
        theme: 'bootstrap-5',
        placeholder: 'Select expense category',
        allowClear: false
    });
});
</script>
@if (auth()->user()->can('expense-categories.menu'))
@include('partials.expense-category-modal')
<script>
(function() {
    var modal = $('#expenseCategoryModal');
    var form = document.getElementById('expense-category-form');
    var titleInput = document.getElementById('expense_category_title');
    var idInput = document.getElementById('expense_category_id');
    var submitBtn = document.getElementById('expense-category-modal-submit');
    var modalTitle = document.getElementById('expenseCategoryModalLabel');
    var errorEl = document.getElementById('expense-category-modal-error');
    var successEl = errorEl.nextElementSibling;

    modal.on('show.bs.modal', function() {
        idInput.value = '';
        titleInput.value = '';
        modalTitle.textContent = 'Add Expense Category';
    });

    submitBtn.addEventListener('click', function() {
        var title = titleInput.value.trim();
        if (!title) {
            errorEl.textContent = 'Category title is required.';
            errorEl.classList.remove('d-none');
            return;
        }
        errorEl.classList.add('d-none');
        successEl.classList.add('d-none');
        submitBtn.disabled = true;

        fetch('{{ route("expense-categories.store") }}', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': form.querySelector('input[name="_token"]').value
            },
            body: JSON.stringify({ expense_title: title, _token: form.querySelector('input[name="_token"]').value })
        })
        .then(function(r) {
            return r.text().then(function(text) {
                var data = null;
                try { data = text ? JSON.parse(text) : null; } catch (e) {}
                return { ok: r.ok, status: r.status, data: data || {} };
            }).catch(function() {
                return { ok: r.ok, status: r.status, data: {} };
            });
        })
        .then(function(res) {
            try {
                var data = res.data || {};
                var success = data.success === true;
                var category = data.category || (data.id && data.expense_title ? { id: data.id, expense_title: data.expense_title } : null);
                if (res.ok && success && category) {
                    var sel = document.getElementById('expense_id');
                    if (sel) {
                        var opt = document.createElement('option');
                        opt.value = category.id;
                        opt.textContent = category.expense_title;
                        sel.appendChild(opt);
                        try {
                            if (typeof $ !== 'undefined') {
                                $('#expense_id').val(category.id).trigger('change');
                            } else {
                                sel.value = category.id;
                            }
                        } catch (e) {}
                    }
                    try {
                        if (typeof modal !== 'undefined' && modal.modal) {
                            modal.modal('hide');
                        } else {
                            var m = document.getElementById('expenseCategoryModal');
                            if (m) { m.classList.remove('show'); m.style.display = 'none'; document.body.classList.remove('modal-open'); }
                        }
                    } catch (e) {}
                    titleInput.value = '';
                    errorEl.classList.add('d-none');
                } else {
                    errorEl.textContent = data.message || 'Request failed.';
                    errorEl.classList.remove('d-none');
                }
            } catch (e) {
                errorEl.textContent = (res.data && res.data.message) || 'Something went wrong. Please select the category from the list.';
                errorEl.classList.remove('d-none');
                try {
                    if (typeof modal !== 'undefined' && modal.modal) { modal.modal('hide'); } else { var m = document.getElementById('expenseCategoryModal'); if (m) { m.classList.remove('show'); m.style.display = 'none'; } }
                    titleInput.value = '';
                } catch (e2) {}
            }
        })
        .catch(function() {
            errorEl.textContent = 'Network error. Please check your connection and try again.';
            errorEl.classList.remove('d-none');
        })
        .finally(function() { submitBtn.disabled = false; });
    });
})();
</script>
@endif
@include('components.preview-img-form')
@endsection
