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
                        <h4 class="card-title">View Expense</h4>
                    </div>
                </div>

                <div class="card-body">
                    <!-- View Expense Form (Read-Only) -->
                    <!-- Expense Title -->
                    <div class="form-group row">
                        <div class="col-md-12">
                            <label for="title">Expense Title</label>
                            <input type="text" class="form-control" id="title" value="{{ $expense->title }}" readonly>
                        </div>
                    </div>

                    <!-- Expense Description -->
                    <div class="form-group row">
                        <div class="col-md-12">
                            <label for="description">Expense Description</label>
                            <textarea class="form-control" id="description" rows="4" readonly>{{ $expense->description }}</textarea>
                        </div>
                    </div>

                    <!-- Expense Date and Cost in a Single Row -->
                    <div class="form-group row">
                        <!-- Expense Date -->
                        <div class="col-md-6">
                            <label for="date">Expense Date</label>
                            <input type="text" class="form-control" id="date" value="{{ $expense->date }}" readonly>
                        </div>

                        <!-- Cost -->
                        <div class="col-md-6">
                            <label for="activity_cost">Cost</label>
                            <input type="text" class="form-control" id="activity_cost" value="{{ $expense->activity_cost }}" readonly>
                        </div>
                    </div>


                    <!-- Expense Images -->
                    <div class="form-group row">
                        <!-- Image 1 -->
                        <div class="col-md-6">
                            <label for="image_1">Image 1</label>
                            <div id="image-preview-1" class="mt-2">
                                <a href="{{ is_array($expense->images) && isset($expense->images[0]) ? asset('storage/' . $expense->images[0]) : asset('assets/images/product/default.webp') }}" target="_blank">
                                    <img class="img-fluid rounded" id="preview-image-1"
                                        src="{{ is_array($expense->images) && isset($expense->images[0]) ? asset('storage/' . $expense->images[0]) : asset('assets/images/product/default.webp') }}"
                                        alt="Image 1">
                                </a>
                            </div>
                        </div>

                        <!-- Image 2 -->
                        <div class="col-md-6">
                            <label for="image_2">Image 2</label>
                            <div id="image-preview-2" class="mt-2">
                                <a href="{{ is_array($expense->images) && isset($expense->images[1]) ? asset('storage/' . $expense->images[1]) : asset('assets/images/product/default.webp') }}" target="_blank">
                                    <img class="img-fluid rounded" id="preview-image-2"
                                        src="{{ is_array($expense->images) && isset($expense->images[1]) ? asset('storage/' . $expense->images[1]) : asset('assets/images/product/default.webp') }}"
                                        alt="Image 2">
                                </a>
                            </div>
                        </div>
                    </div>


                    <!-- Back to Index Button -->
                    <div class="mt-2">
                        <a class="btn bg-danger" href="{{ route('expenses.index') }}">Back to Expenses</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

@include('components.preview-img-form')
@endsection
