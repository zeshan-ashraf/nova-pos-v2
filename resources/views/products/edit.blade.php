@extends('dashboard.body.main')

@section('specificpagestyles')
    <script src="https://code.jquery.com/jquery-3.3.1.min.js"></script>
    <script src="https://unpkg.com/gijgo@1.9.14/js/gijgo.min.js" type="text/javascript"></script>
    <link href="https://unpkg.com/gijgo@1.9.14/css/gijgo.min.css" rel="stylesheet" type="text/css" />
@endsection

@section('container')
<div class="container-fluid">
    <div class="row">
        <div class="col-lg-12">
            <div class="card">
                <div class="card-header d-flex justify-content-between">
                    <div class="header-title">
                        <h4 class="card-title">Edit Product</h4>
                    </div>
                </div>

                <div class="card-body">
                    <form action="{{ route('products.update', $product->id) }}" method="POST" enctype="multipart/form-data">
                    @csrf
                    @method('put')
                        <!-- begin: Input Image -->
                        <div class="form-group row align-items-center">
                            <div class="col-md-12">
                                <div class="profile-img-edit">
                                    <div class="crm-profile-img-edit">
                                        <img class="crm-profile-pic rounded-circle avatar-100" id="image-preview" src="{{ $product->product_image ? asset('storage/products/'.$product->product_image) : asset('assets/images/product/default.webp') }}" alt="profile-pic">
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="input-group mb-4 col-lg-6">
                                <div class="custom-file">
                                    <input type="file" class="custom-file-input @error('product_image') is-invalid @enderror" id="image" name="product_image" accept="image/*" onchange="previewImage();">
                                    <label class="custom-file-label" for="product_image">Choose file</label>
                                </div>
                                @error('product_image')
                                <div class="invalid-feedback">
                                    {{ $message }}
                                </div>
                                @enderror
                            </div>
                        </div>
                        <!-- end: Input Image -->
                        <!-- begin: Input Data -->
                        <div class=" row align-items-center">
                            <div class="form-group col-md-12">
                                <div class="d-flex align-items-center justify-content-between mb-1">
                                    <label for="product_name" class="mb-0">Product Name <span class="text-danger">*</span></label>
                                    @if ($product->status === 'active')
                                        <span class="badge rounded-pill bg-success">Active</span>
                                    @elseif ($product->status === 'ordered')
                                        <span class="badge rounded-pill bg-warning">Ordered</span>
                                    @else
                                        <span class="badge rounded-pill bg-secondary">{{ ucfirst($product->status ?? 'N/A') }}</span>
                                    @endif
                                </div>
                                <input type="text" class="form-control @error('product_name') is-invalid @enderror" id="product_name" name="product_name" value="{{ old('product_name', $product->product_name) }}" required>
                                @error('product_name')
                                <div class="invalid-feedback">
                                    {{ $message }}
                                </div>
                                @enderror
                            </div>
                            <div class="form-group col-md-6">
                                <label for="product_code">Product Code <span class="text-danger">*</span></label>
                                <input type="text" class="form-control @error('product_code') is-invalid @enderror" id="product_code" name="product_code" value="{{ old('product_code', $product->product_code) }}" required>
                                @error('product_code')
                                <div class="invalid-feedback">
                                    {{ $message }}
                                </div>
                                @enderror
                            </div>
                            <div class="form-group col-md-6">
                                <div class="d-flex align-items-center justify-content-between mb-1">
                                    <label for="category_id" class="mb-0">Category <span class="text-danger">*</span></label>
                                    @can('category.menu')
                                    <button type="button" class="btn btn-sm btn-outline-primary" data-toggle="modal" data-target="#addCategoryModalProduct">Add Category</button>
                                    @endcan
                                </div>
                                <select class="form-control" id="category_id" name="category_id" required>
                                    <option value="" disabled>-- Select Category --</option>
                                    @foreach ($categories as $category)
                                        <option value="{{ $category->id }}" {{ old('category_id', $product->category_id) == $category->id ? 'selected' : '' }}>{{ $category->name }}</option>
                                    @endforeach
                                </select>
                                @error('category_id')
                                <div class="invalid-feedback">
                                    {{ $message }}
                                </div>
                                @enderror
                            </div>
                            {{-- Supplier field removed - now optional --}}
                            {{-- <div class="form-group col-md-6">
                                <label for="supplier_id">Supplier <span class="text-danger">*</span></label>
                                <select class="form-control" name="supplier_id" required>
                                    <option selected="" disabled>-- Select Supplier --</option>
                                    @foreach ($suppliers as $supplier)
                                        <option value="{{ $supplier->id }}" {{ old('supplier_id', $product->supplier_id) == $supplier->id ? 'selected' : '' }}>{{ $supplier->name }}</option>
                                    @endforeach
                                </select>
                                @error('supplier_id')
                                <div class="invalid-feedback">
                                    {{ $message }}
                                </div>
                                @enderror
                            </div> --}}
                            <div class="form-group col-md-6">
                                <label for="product_garage">Product Garage</label>
                                <input type="text" class="form-control @error('product_garage') is-invalid @enderror" id="product_garage" name="product_garage" value="{{ old('product_garage', $product->product_garage) }}">
                                @error('product_garage')
                                <div class="invalid-feedback">
                                    {{ $message }}
                                </div>
                                @enderror
                            </div>
                            @php
                                $currentUnit = old('unit', $product->unit ?: 'piece');
                                $unitLocked = $unitLocked ?? false;
                            @endphp
                            <div class="form-group col-md-6">
                                <label for="unit">Unit <span class="text-danger">*</span></label>
                                @if ($unitLocked)
                                    <input type="hidden" name="unit" value="{{ $product->unit ?: 'piece' }}">
                                    <select class="form-control @error('unit') is-invalid @enderror" id="unit" disabled>
                                        <option value="piece" {{ $currentUnit === 'piece' ? 'selected' : '' }}>Piece</option>
                                        <option value="kg" {{ $currentUnit === 'kg' ? 'selected' : '' }}>Kg</option>
                                    </select>
                                    <small class="form-text text-muted">This product's unit cannot be changed because it already has transaction history.</small>
                                @else
                                    <select class="form-control @error('unit') is-invalid @enderror" id="unit" name="unit" required>
                                        <option value="piece" {{ $currentUnit === 'piece' ? 'selected' : '' }}>Piece</option>
                                        <option value="kg" {{ $currentUnit === 'kg' ? 'selected' : '' }}>Kg</option>
                                    </select>
                                    <small class="form-text text-muted">Changing the unit does not convert stock. Review the stock quantity to match the new unit.</small>
                                @endif
                                @error('unit')
                                <div class="invalid-feedback d-block">
                                    {{ $message }}
                                </div>
                                @enderror
                            </div>
                            <div class="form-group col-md-6">
                                <label for="product_store">Stock</label>
                                <input type="number" min="0" step="{{ $currentUnit === 'kg' ? '0.001' : '1' }}" class="form-control @error('product_store') is-invalid @enderror" id="product_store" name="product_store" value="{{ old('product_store', $product->formattedQuantity()) }}">
                                @error('product_store')
                                <div class="invalid-feedback">
                                    {{ $message }}
                                </div>
                                @enderror
                            </div>
                            <div class="form-group col-md-6">
                                <label for="low_stock_warning">Low Stock Warning</label>
                                <input type="number" class="form-control @error('low_stock_warning') is-invalid @enderror" id="low_stock_warning" name="low_stock_warning" value="{{ old('low_stock_warning', $product->low_stock_warning ?? 10) }}" min="0">
                                <small class="form-text text-muted">Alert when stock falls below this number</small>
                                @error('low_stock_warning')
                                <div class="invalid-feedback">
                                    {{ $message }}
                                </div>
                                @enderror
                            </div>
                            <div class="form-group col-md-6">
                                <label for="buying_date">Buying Date</label>
                                <input id="buying_date" class="form-control @error('buying_date') is-invalid @enderror" name="buying_date" value="{{ old('buying_date', $product->buying_date) }}" />
                                @error('buying_date')
                                <div class="invalid-feedback">
                                    {{ $message }}
                                </div>
                                @enderror
                            </div>
                            {{-- Expire Date field removed --}}
                            {{-- <div class="form-group col-md-6">
                                <label for="expire_date">Expire Date</label>
                                <input id="expire_date" class="form-control @error('expire_date') is-invalid @enderror" name="expire_date" value="{{ old('expire_date', $product->expire_date) }}" />
                                @error('expire_date')
                                <div class="invalid-feedback">
                                    {{ $message }}
                                </div>
                                @enderror
                            </div> --}}
                            <div class="form-group col-md-6">
                                <label for="buying_price">Buying Price</label>
                                <input type="text" class="form-control @error('buying_price') is-invalid @enderror" id="buying_price" name="buying_price" value="{{ old('buying_price', $product->buying_price) }}">
                                <small class="form-text text-muted">If prices are not provided, the product will be saved as Ordered and won't be available for sale.</small>
                                @error('buying_price')
                                <div class="invalid-feedback">
                                    {{ $message }}
                                </div>
                                @enderror
                            </div>
                            <div class="form-group col-md-6">
                                <label for="selling_price">Selling Price</label>
                                <input type="text" class="form-control @error('selling_price') is-invalid @enderror" id="selling_price" name="selling_price" value="{{ old('selling_price', $product->selling_price) }}">
                                <small class="form-text text-muted">If prices are not provided, the product will be saved as Ordered and won't be available for sale.</small>
                                @error('selling_price')
                                <div class="invalid-feedback">
                                    {{ $message }}
                                </div>
                                @enderror
                            </div>
                        </div>
                        <!-- end: Input Data -->
                        <div class="mt-2">
                            <button type="submit" class="btn btn-primary mr-2">Save</button>
                            <a class="btn bg-danger" href="{{ route('products.index') }}">Cancel</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
    <!-- Page end  -->
</div>

@include('products.partials.unit-stock-script')

<script>
    $('#buying_date').datepicker({
        uiLibrary: 'bootstrap4',
        format: 'yyyy-mm-dd'
        // https://gijgo.com/datetimepicker/configuration/format
    });
    {{-- Expire date datepicker removed --}}
    {{-- $('#expire_date').datepicker({
        uiLibrary: 'bootstrap4',
        format: 'yyyy-mm-dd'
        // https://gijgo.com/datetimepicker/configuration/format
    }); --}}
    if (typeof window.bindProductUnitInputs === 'function') {
        window.bindProductUnitInputs('unit', 'product_store', 'low_stock_warning');
    }
</script>

@include('components.preview-img-form')

@can('category.menu')
{{-- Add Category modal (AJAX) - on success close after 5s and select new category --}}
<div class="modal fade" id="addCategoryModalProduct" tabindex="-1" role="dialog" aria-labelledby="addCategoryModalProductLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="addCategoryModalProductLabel">Add Category</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <div id="add-category-error-product" class="alert alert-danger d-none" role="alert"></div>
                <div id="add-category-success-product" class="alert alert-success d-none" role="alert"></div>
                <form id="add-category-form-product">
                    @csrf
                    <div class="form-group">
                        <label for="category_name_product">Category Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="category_name_product" name="name" required placeholder="Enter category name">
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                <button type="button" class="btn btn-primary" id="add-category-submit-product">Save</button>
            </div>
        </div>
    </div>
</div>
<script>
(function() {
    var modal = $('#addCategoryModalProduct');
    var successEl = document.getElementById('add-category-success-product');
    var errorEl = document.getElementById('add-category-error-product');
    var selectEl = document.getElementById('category_id');

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

    document.getElementById('add-category-submit-product').addEventListener('click', function() {
        var name = document.getElementById('category_name_product').value.trim();
        if (!name) {
            showError('Category name is required.');
            return;
        }
        showError('');
        this.disabled = true;
        var token = document.querySelector('#add-category-form-product input[name="_token"]').value;
        fetch('{{ route("categories.store") }}', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': token
            },
            body: JSON.stringify({ name: name, _token: token })
        })
        .then(function(r) { return r.json().then(function(data) { return { ok: r.ok, data: data }; }); })
        .then(function(res) {
            if (res.ok && res.data.success && res.data.category) {
                var cat = res.data.category;
                showSuccess(res.data.message || 'Category has been created!');
                var opt = document.createElement('option');
                opt.value = cat.id;
                opt.textContent = cat.name;
                opt.selected = true;
                selectEl.appendChild(opt);
                selectEl.value = cat.id;
                var closeTimer = setTimeout(function() {
                    modal.modal('hide');
                    showSuccess('');
                    showError('');
                    document.getElementById('category_name_product').value = '';
                }, 5000);
                modal.one('hidden.bs.modal', function() {
                    clearTimeout(closeTimer);
                });
            } else {
                showError(res.data.message || (res.data.errors && Object.values(res.data.errors).flat().join(' ')) || 'Request failed.');
            }
        })
        .catch(function() {
            showError('Network error. Please try again.');
        })
        .finally(function() {
            document.getElementById('add-category-submit-product').disabled = false;
        });
    });

    modal.on('hidden.bs.modal', function() {
        showSuccess('');
        showError('');
        document.getElementById('category_name_product').value = '';
    });
})();
</script>
@endcan
@endsection
