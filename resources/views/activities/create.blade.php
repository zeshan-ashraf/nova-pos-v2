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
                        <h4 class="card-title">Add Activity</h4>
                    </div>
                </div>

                <div class="card-body">
                    <form action="{{ route('activities.store') }}" method="POST" enctype="multipart/form-data">
                        @csrf
                        <!-- begin: Input Title -->
                        <div class="form-group row">
                            <div class="col-md-12">
                                <label for="title">Activity Title <span class="text-danger">*</span></label>
                                <input type="text" class="form-control @error('title') is-invalid @enderror" id="title" name="title" value="{{ old('title') }}" required>
                                @error('title')
                                <div class="invalid-feedback">
                                    {{ $message }}
                                </div>
                                @enderror
                            </div>
                        </div>
                        <!-- end: Input Title -->

                        <!-- begin: Input Description -->
                        <div class="form-group row">
                            <div class="col-md-12">
                                <label for="description">Activity Description <span class="text-danger">*</span></label>
                                <textarea class="form-control @error('description') is-invalid @enderror" id="description" name="description" rows="4" required>{{ old('description') }}</textarea>
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
                                <label for="date">Activity Date <span class="text-danger">*</span></label>
                                <input type="text" class="form-control @error('date') is-invalid @enderror" id="date" name="date" value="{{ old('date') }}" required readonly>
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
                                <input type="file" class="custom-file-input @error('image_1') is-invalid @enderror" id="image_1" name="image_1" accept="image/*" onchange="previewImages();">
                                <label class="custom-file-label" for="image_1">Choose file</label>
                                @error('image_1')
                                <div class="invalid-feedback">
                                    {{ $message }}
                                </div>
                                @enderror
                                <div id="image-preview-1" class="mt-2">
                                    <img class="avatar-60 rounded" id="preview-image-1" src="{{ asset('assets/images/product/default.webp') }}" alt="preview">
                                </div>
                            </div>

                            <div class="col-md-6">
                                <label for="image_2">Image 2</label>
                                <input type="file" class="custom-file-input @error('image_2') is-invalid @enderror" id="image_2" name="image_2" accept="image/*" onchange="previewImages();">
                                <label class="custom-file-label" for="image_2">Choose file</label>
                                @error('image_2')
                                <div class="invalid-feedback">
                                    {{ $message }}
                                </div>
                                @enderror
                                <div id="image-preview-2" class="mt-2">
                                    <img class="avatar-60 rounded" id="preview-image-2" src="{{ asset('assets/images/product/default.webp') }}" alt="preview">
                                </div>
                            </div>
                        </div>
                        <!-- end: Input Images -->

                        <!-- begin: Input Activity Cost -->
                        <div class="form-group row">
                            <div class="col-md-6">
                                <label for="activity_cost">Activity Cost <span class="text-danger">*</span></label>
                                <input type="text" class="form-control @error('activity_cost') is-invalid @enderror" id="activity_cost" name="activity_cost" value="{{ old('activity_cost') }}" required>
                                @error('activity_cost')
                                <div class="invalid-feedback">
                                    {{ $message }}
                                </div>
                                @enderror
                            </div>
                        </div>
                        <!-- end: Input Activity Cost -->

                        <!-- begin: Input Customer -->
                        <div class="form-group row">
                            <div class="col-md-6">
                                <label for="customer_id">Customer <span class="text-danger">*</span></label>
                                <select class="form-control @error('customer_id') is-invalid @enderror" name="customer_id" required>
                                    <option selected="" disabled>-- Select Customer --</option>
                                    @foreach ($customers as $customer)
                                        <option value="{{ $customer->id }}" {{ old('customer_id') == $customer->id ? 'selected' : '' }}>{{ $customer->name }}</option>
                                    @endforeach
                                </select>
                                @error('customer_id')
                                <div class="invalid-feedback">
                                    {{ $message }}
                                </div>
                                @enderror
                            </div>
                        </div>
                        <!-- end: Input Customer -->

                        <!-- Submit Button -->
                        <div class="mt-2">
                            <button type="submit" class="btn btn-primary mr-2">Save</button>
                            <a class="btn bg-danger" href="{{ route('activities.index') }}">Cancel</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
    <!-- Page end  -->
</div>

<script>
    // Initialize Datepicker for the activity date field
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

@include('components.preview-img-form')
@endsection
