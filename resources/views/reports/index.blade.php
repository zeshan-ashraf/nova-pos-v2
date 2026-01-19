@extends('dashboard.body.main')

@section('container')
<div class="container-fluid">
    <div class="row">
        <div class="col-lg-12">
            <div class="d-flex flex-wrap align-items-center justify-content-between mb-4">
                <div>
                    <h4 class="mb-3">Reports</h4>
                    <p class="text-muted">Select a report from the categories below</p>
                </div>
            </div>
        </div>

        @foreach($categories as $key => $category)
        <div class="col-lg-12 mb-4">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="{{ $category['icon'] }} mr-2"></i>
                        {{ $category['name'] }}
                    </h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        @foreach($category['reports'] as $report)
                        <div class="col-md-6 col-lg-4 mb-3">
                            <div class="card border">
                                <div class="card-body">
                                    <h6 class="card-title">{{ $report['name'] }}</h6>
                                    <p class="card-text text-muted small">{{ $report['description'] }}</p>
                                    <a href="{{ route($report['route']) }}" class="btn btn-primary btn-sm">
                                        <i class="ri-file-chart-line mr-1"></i> View Report
                                    </a>
                                </div>
                            </div>
                        </div>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>
        @endforeach

        @if(empty($categories))
        <div class="col-lg-12">
            <div class="alert alert-info">
                <i class="ri-information-line mr-2"></i>
                No reports available. Please contact your administrator for access.
            </div>
        </div>
        @endif
    </div>
</div>
@endsection
