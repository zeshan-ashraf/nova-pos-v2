@extends('dashboard.body.main')

@section('container')
<div class="container-fluid">
    <div class="row">
        <div class="col-lg-12">
            <div class="d-flex flex-wrap align-items-center justify-content-between mb-3">
                <div>
                    <h4 class="mb-1">Reports</h4>
                    <p class="text-muted mb-0 small">Select a report to view</p>
                </div>
                @if(!empty($categories))
                <div>
                    <button type="button" id="reportsViewToggle" class="btn btn-secondary btn-sm">
                        <i class="fas fa-table mr-1"></i> <span id="reportsViewToggleLabel">Show in Table</span>
                    </button>
                </div>
                @endif
            </div>
        </div>

        <div id="reportsGridView">
        @foreach($categories as $key => $category)
        <div class="col-lg-12 mb-3">
            <div class="card shadow-sm">
                <div class="card-header bg-primary py-2">
                    <h6 class="mb-0 text-white">
                        <i class="{{ $category['icon'] }} mr-2"></i>
                        {{ $category['name'] }}
                    </h6>
                </div>
                <div class="card-body p-2">
                    <div class="row g-2">
                        @foreach($category['reports'] as $report)
                        <div class="col-md-6 col-lg-3">
                            <a href="{{ route($report['route']) }}" class="text-decoration-none">
                                <div class="card border h-100 hover-shadow transition-all" style="transition: all 0.2s;">
                                    <div class="card-body p-3">
                                        <div class="d-flex align-items-center">
                                            <div class="flex-grow-1">
                                                <h6 class="mb-1 text-dark">{{ $report['name'] }}</h6>
                                                <p class="mb-0 text-muted small" style="font-size: 0.75rem; line-height: 1.3;">
                                                    {{ Str::limit($report['description'], 50) }}
                                                </p>
                                            </div>
                                            <div class="ml-2">
                                                <i class="ri-arrow-right-s-line text-primary" style="font-size: 1.2rem;"></i>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </a>
                        </div>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>
        @endforeach
        </div>

        <div id="reportsTableView" class="col-lg-12 mb-3" style="display: none;">
            <div class="card shadow-sm">
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover table-striped mb-0">
                            <thead class="thead-light">
                                <tr>
                                    <th style="width: 1%;">#</th>
                                    <th>Category</th>
                                    <th>Report Name</th>
                                    <th>Description</th>
                                    <th style="width: 100px;" class="text-center">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                @php $sr = 1; @endphp
                                @foreach($categories as $category)
                                    @foreach($category['reports'] as $report)
                                    <tr>
                                        <td>{{ $sr++ }}</td>
                                        <td><i class="{{ $category['icon'] }} mr-1 text-primary"></i> {{ $category['name'] }}</td>
                                        <td>{{ $report['name'] }}</td>
                                        <td class="text-muted small">{{ $report['description'] }}</td>
                                        <td class="text-center">
                                            <a href="{{ route($report['route']) }}" class="btn btn-sm btn-primary">Open</a>
                                        </td>
                                    </tr>
                                    @endforeach
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

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

<style>
.hover-shadow:hover {
    box-shadow: 0 4px 8px rgba(0,0,0,0.1) !important;
    transform: translateY(-2px);
}
.transition-all {
    transition: all 0.2s ease;
}
</style>
<script>
(function() {
    var toggleBtn = document.getElementById('reportsViewToggle');
    var toggleLabel = document.getElementById('reportsViewToggleLabel');
    var gridView = document.getElementById('reportsGridView');
    var tableView = document.getElementById('reportsTableView');
    if (!toggleBtn || !gridView || !tableView) return;
    var isTableView = false;
    toggleBtn.addEventListener('click', function() {
        isTableView = !isTableView;
        if (isTableView) {
            gridView.style.display = 'none';
            tableView.style.display = 'block';
            toggleLabel.textContent = 'Show in Grid';
            toggleBtn.querySelector('i').className = 'fas fa-th-large mr-1';
        } else {
            gridView.style.display = 'block';
            tableView.style.display = 'none';
            toggleLabel.textContent = 'Show in Table';
            toggleBtn.querySelector('i').className = 'fas fa-table mr-1';
        }
    });
})();
</script>
@endsection
