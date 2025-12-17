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
                        <h4 class="card-title">View Activity</h4>
                    </div>
                </div>

                <div class="card-body">
                    <!-- View Activity Form (Read-Only) -->
                    <!-- Activity Title -->
                    <div class="form-group row">
                        <div class="col-md-12">
                            <label for="title">Activity Title</label>
                            <input type="text" class="form-control" id="title" value="{{ $activity->title }}" readonly>
                        </div>
                    </div>

                    <!-- Activity Description -->
                    <div class="form-group row">
                        <div class="col-md-12">
                            <label for="description">Activity Description</label>
                            <textarea class="form-control" id="description" rows="4" readonly>{{ $activity->description }}</textarea>
                        </div>
                    </div>

                    <!-- Activity Date and Activity Cost in a Single Row -->
                    <div class="form-group row">
                        <!-- Activity Date -->
                        <div class="col-md-6">
                            <label for="date">Activity Date</label>
                            <input type="text" class="form-control" id="date" value="{{ $activity->date }}" readonly>
                        </div>

                        <!-- Activity Cost -->
                        <div class="col-md-6">
                            <label for="activity_cost">Activity Cost</label>
                            <input type="text" class="form-control" id="activity_cost" value="{{ $activity->activity_cost }}" readonly>
                        </div>
                    </div>


                    <!-- Activity Images -->
                    <div class="form-group row">
                        <!-- Image 1 -->
                        <div class="col-md-6">
                            <label for="image_1">Image 1</label>
                            <div id="image-preview-1" class="mt-2">
                                <!-- Wrap the image in an anchor tag to open it in a new tab -->
                                <a href="{{ is_array($activity->images) && isset($activity->images[0]) ? asset('storage/' . $activity->images[0]) : asset('assets/images/product/default.webp') }}" target="_blank">
                                    <!-- Set a larger size for the image -->
                                    <img class="img-fluid rounded" id="preview-image-1"
                                        src="{{ is_array($activity->images) && isset($activity->images[0]) ? asset('storage/' . $activity->images[0]) : asset('assets/images/product/default.webp') }}"
                                        alt="Image 1">
                                </a>
                            </div>
                        </div>

                        <!-- Image 2 -->
                        <div class="col-md-6">
                            <label for="image_2">Image 2</label>
                            <div id="image-preview-2" class="mt-2">
                                <!-- Wrap the image in an anchor tag to open it in a new tab -->
                                <a href="{{ is_array($activity->images) && isset($activity->images[1]) ? asset('storage/' . $activity->images[1]) : asset('assets/images/product/default.webp') }}" target="_blank">
                                    <!-- Set a larger size for the image -->
                                    <img class="img-fluid rounded" id="preview-image-2"
                                        src="{{ is_array($activity->images) && isset($activity->images[1]) ? asset('storage/' . $activity->images[1]) : asset('assets/images/product/default.webp') }}"
                                        alt="Image 2">
                                </a>
                            </div>
                        </div>
                    </div>


                    <!-- Back to Index Button -->
                    <div class="mt-2">
                        <a class="btn bg-danger" href="{{ route('activities.index') }}">Back to Activities</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

@include('components.preview-img-form')
@endsection
