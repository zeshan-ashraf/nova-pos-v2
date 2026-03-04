@extends('dashboard.body.main')

@section('container')
<div class="container-fluid">
    <div class="row">
        <div class="col-lg-12">
            <div class="d-flex flex-wrap align-items-center justify-content-between mb-4">
                <div>
                    <h4 class="mb-3">Product Sales Report</h4>
                </div>
                <div class="d-print-none">
                    <a href="{{ route('reports.index') }}" class="btn btn-secondary">
                        <i class="ri-arrow-left-line mr-1"></i> Back to Reports
                    </a>
                </div>
            </div>
        </div>

        <!-- Filter Section -->
        <div class="col-lg-12 mb-3 d-print-none">
            <div class="card report-filter-card border-primary shadow-sm">
                <div class="card-header border-0 py-2">
                    <h6 class="mb-0 text-primary"><i class="ri-filter-3-line mr-1"></i> Filters</h6>
                </div>
                <div class="card-body pt-0">
                    <form action="{{ route('reports.sales.product') }}" method="GET" id="filterForm">
                        <div class="row align-items-end">
                            <div class="col-md-3">
                                <label for="date_filter" class="form-label">Date Filter</label>
                                <select class="form-control" name="date_filter" id="date_filter" onchange="toggleCustomDates()">
                                    <option value="today" {{ $dateRange['date_filter'] == 'today' ? 'selected' : '' }}>Today</option>
                                    <option value="yesterday" {{ $dateRange['date_filter'] == 'yesterday' ? 'selected' : '' }}>Yesterday</option>
                                    <option value="this_week" {{ $dateRange['date_filter'] == 'this_week' ? 'selected' : '' }}>This Week</option>
                                    <option value="last_week" {{ $dateRange['date_filter'] == 'last_week' ? 'selected' : '' }}>Last Week</option>
                                    <option value="this_month" {{ $dateRange['date_filter'] == 'this_month' ? 'selected' : '' }}>This Month</option>
                                    <option value="last_month" {{ $dateRange['date_filter'] == 'last_month' ? 'selected' : '' }}>Last Month</option>
                                    <option value="this_year" {{ $dateRange['date_filter'] == 'this_year' ? 'selected' : '' }}>This Year</option>
                                    <option value="last_year" {{ $dateRange['date_filter'] == 'last_year' ? 'selected' : '' }}>Last Year</option>
                                    <option value="custom" {{ $dateRange['date_filter'] == 'custom' ? 'selected' : '' }}>Custom Range</option>
                                </select>
                            </div>
                            <div class="col-md-3" id="start_date_group" style="display: {{ $dateRange['date_filter'] == 'custom' ? 'block' : 'none' }};">
                                <label for="start_date" class="form-label">Start Date</label>
                                <input type="date" class="form-control" name="start_date" id="start_date" value="{{ $dateRange['start_date'] }}">
                            </div>
                            <div class="col-md-3" id="end_date_group" style="display: {{ $dateRange['date_filter'] == 'custom' ? 'block' : 'none' }};">
                                <label for="end_date" class="form-label">End Date</label>
                                <input type="date" class="form-control" name="end_date" id="end_date" value="{{ $dateRange['end_date'] }}">
                            </div>
                            @if(auth()->user()->shop_id == null)
                            <div class="col-md-3">
                                <label for="shop_id" class="form-label">Shop</label>
                                <select class="form-control" name="shop_id" id="shop_id">
                                    <option value="all" {{ $shopFilter['selected_shop_id'] == 'all' ? 'selected' : '' }}>All Shops</option>
                                    @foreach($shopFilter['shops'] as $shop)
                                    <option value="{{ $shop->id }}" {{ $shopFilter['selected_shop_id'] == $shop->id ? 'selected' : '' }}>
                                        {{ $shop->name }}
                                    </option>
                                    @endforeach
                                </select>
                            </div>
                            @endif
                            <div class="col-md-3">
                                <label for="product_id" class="form-label">Product</label>
                                <select class="form-control" name="product_id" id="product_id">
                                    <option value="" {{ !$selectedProductId ? 'selected' : '' }}>All Products</option>
                                    @if($selectedProductId && $selectedProduct)
                                    <option value="{{ $selectedProduct->id }}" selected>
                                        {{ $selectedProduct->product_name }}
                                        @if($selectedProduct->product_code) ({{ $selectedProduct->product_code }}) @endif
                                    </option>
                                    @endif
                                </select>
                            </div>
                            <div class="col-md-3">
                                <button type="submit" class="btn btn-primary">
                                    <i class="ri-search-line mr-1"></i> Filter
                                </button>
                                <button type="button" class="btn btn-outline-secondary btn-sm ml-2" onclick="window.print()"><i class="ri-printer-line mr-1"></i> Print</button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- KPI Cards Row 1 -->
        <div class="col-lg-12 mb-3">
            <div class="row sales-kpi-row-1">
                <div class="col-md-3 mb-3 mb-md-0">
                    <div class="card card-block card-stretch card-height shadow-sm sales-report-kpi h-100">
                        <div class="card-body d-flex align-items-center">
                            <div class="icon iq-icon-box-2 bg-primary-light d-flex align-items-center justify-content-center mr-3 flex-shrink-0">
                                <i class="ri-barcode-box-line text-primary" style="font-size: 1.75rem;"></i>
                            </div>
                            <div class="flex-grow-1 min-w-0">
                                <p class="text-muted mb-0 small font-weight-500">Total Products</p>
                                <h4 class="mb-0 font-weight-bold text-dark">{{ number_format($totalProducts, 0) }}</h4>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 mb-3 mb-md-0">
                    <div class="card card-block card-stretch card-height shadow-sm sales-report-kpi h-100">
                        <div class="card-body d-flex align-items-center">
                            <div class="icon iq-icon-box-2 bg-info-light d-flex align-items-center justify-content-center mr-3 flex-shrink-0">
                                <i class="ri-stack-line text-info" style="font-size: 1.75rem;"></i>
                            </div>
                            <div class="flex-grow-1 min-w-0">
                                <p class="text-muted mb-0 small font-weight-500">Total Quantity Sold</p>
                                <h4 class="mb-0 font-weight-bold text-dark">{{ number_format($totalQuantity, 0) }}</h4>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 mb-3 mb-md-0">
                    <div class="card card-block card-stretch card-height shadow-sm sales-report-kpi h-100">
                        <div class="card-body d-flex align-items-center">
                            <div class="icon iq-icon-box-2 bg-success-light d-flex align-items-center justify-content-center mr-3 flex-shrink-0">
                                <i class="ri-money-dollar-circle-line text-success" style="font-size: 1.75rem;"></i>
                            </div>
                            <div class="flex-grow-1 min-w-0">
                                <p class="text-muted mb-0 small font-weight-500">Total Revenue</p>
                                <h4 class="mb-0 font-weight-bold text-dark">{{ number_format($totalRevenue, 2) }}</h4>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 mb-3 mb-md-0">
                    <div class="card card-block card-stretch card-height shadow-sm sales-report-kpi h-100">
                        <div class="card-body d-flex align-items-center">
                            <div class="icon iq-icon-box-2 bg-warning-light d-flex align-items-center justify-content-center mr-3 flex-shrink-0">
                                <i class="ri-line-chart-line text-warning" style="font-size: 1.75rem;"></i>
                            </div>
                            <div class="flex-grow-1 min-w-0">
                                <p class="text-muted mb-0 small font-weight-500">Total Profit</p>
                                <h4 class="mb-0 font-weight-bold text-dark">{{ number_format($totalProfit ?? 0, 2) }}</h4>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Product Sales Table -->
        <div class="col-lg-12 sales-orders-table-wrap">
            <div class="card">
                <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">Product Sales Summary</h5>
                    <div>
                        <form action="{{ route('reports.sales.product') }}" method="GET" class="d-inline">
                            <input type="hidden" name="date_filter" value="{{ $dateRange['date_filter'] }}">
                            <input type="hidden" name="start_date" value="{{ $dateRange['start_date'] }}">
                            <input type="hidden" name="end_date" value="{{ $dateRange['end_date'] }}">
                            @if(auth()->user()->shop_id == null)
                            <input type="hidden" name="shop_id" value="{{ $shopFilter['selected_shop_id'] }}">
                            @endif
                            @if($selectedProductId)
                            <input type="hidden" name="product_id" value="{{ $selectedProductId }}">
                            @endif
                            <select name="row" class="form-control form-control-sm d-inline-block" style="width: auto;" onchange="this.form.submit()">
                                <option value="10" {{ $row == 10 ? 'selected' : '' }}>10</option>
                                <option value="25" {{ $row == 25 ? 'selected' : '' }}>25</option>
                                <option value="50" {{ $row == 50 ? 'selected' : '' }}>50</option>
                                <option value="100" {{ $row == 100 ? 'selected' : '' }}>100</option>
                            </select>
                        </form>
                    </div>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-striped" id="productSalesTable">
                            <thead>
                                <tr>
                                    <th>No.</th>
                                    <th>Product Name</th>
                                    <th>Product Code</th>
                                    <th>Shop</th>
                                    <th>Total Quantity</th>
                                    <th>Total Revenue</th>
                                    <th>Cost</th>
                                    <th>Profit</th>
                                    <th>Order Count</th>
                                    <th>Avg. Price</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($productSales as $productSale)
                                @php
                                    $product = $productSale->product;
                                    $avgPrice = $productSale->total_quantity > 0 
                                        ? $productSale->total_revenue / $productSale->total_quantity 
                                        : 0;
                                    $totalCost = (float) ($productSale->total_cost ?? 0);
                                    $profit = (float) ($productSale->total_revenue ?? 0) - $totalCost;
                                    // Get shop from product
                                    $shopName = 'N/A';
                                    if ($product && $product->shop) {
                                        $productShop = $product->shop;
                                        if ($productShop->parent) {
                                            $shopName = $productShop->parent->name . ' - ' . $productShop->name;
                                        } else {
                                            $shopName = $productShop->name;
                                        }
                                    }
                                @endphp
                                <tr>
                                    <td>{{ (($productSales->currentPage() * $productSales->perPage()) - $productSales->perPage()) + $loop->iteration }}</td>
                                    <td>{{ $product->product_name ?? 'N/A' }}</td>
                                    <td>{{ $product->product_code ?? 'N/A' }}</td>
                                    <td>{{ $shopName }}</td>
                                    <td>{{ number_format($productSale->total_quantity, 0) }}</td>
                                    <td>{{ number_format($productSale->total_revenue, 2) }}</td>
                                    <td>{{ number_format($totalCost, 2) }}</td>
                                    <td>{{ number_format($profit, 2) }}</td>
                                    <td>{{ $productSale->order_count }}</td>
                                    <td>{{ number_format($avgPrice, 2) }}</td>
                                </tr>
                                @empty
                                <tr>
                                    <td colspan="10" class="text-center">No product sales data found for the selected period.</td>
                                </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                    <div class="mt-3">
                        {{ $productSales->appends(request()->query())->links() }}
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

@endsection

@section('specificpagestyles')
<!-- Select2 CSS -->
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<link href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" rel="stylesheet" />
<style>
    @media print {
        @page { margin: 8mm; size: auto; }
        .iq-sidebar, .iq-top-navbar, .iq-footer,
        .report-filter-card, .d-print-none { display: none !important; }
        .wrapper { display: block !important; }
        .content-page { padding: 0 !important; margin: 0 !important; width: 100% !important; max-width: none !important; }
        body, .wrapper { padding: 0 !important; margin: 0 !important; }
        body { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        .container-fluid { width: 100% !important; max-width: none !important; padding-left: 8px !important; padding-right: 8px !important; }
        .sales-kpi-row-1 { display: flex !important; flex-wrap: nowrap !important; break-inside: avoid; }
        .sales-kpi-row-1 .col-md-3 { flex: 0 0 25% !important; max-width: 25% !important; padding: 0 6px !important; }
        .sales-report-kpi, .sales-report-kpi.card { border: 1px solid #dee2e6 !important; box-shadow: none !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        .sales-report-kpi .card-body, .sales-report-kpi .iq-icon-box-2 { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        .card { border: 1px solid #dee2e6 !important; box-shadow: none !important; }
        .card-header.bg-primary { background: #0d6efd !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        .card-header .form-control, .card-header form { display: none !important; }
        .card-body > .mt-3 { display: none !important; }
        .sales-orders-table-wrap { width: 100vw !important; max-width: 100vw !important; position: relative !important; left: 50% !important; margin-left: -50vw !important; padding-left: 4px !important; padding-right: 4px !important; box-sizing: border-box !important; }
        .sales-orders-table-wrap .card { width: 100% !important; max-width: none !important; }
        .sales-orders-table-wrap .card-body { padding: 4px !important; width: 100% !important; }
        .sales-orders-table-wrap .table-responsive { width: 100% !important; max-width: none !important; overflow: visible !important; }
        #productSalesTable { width: 100% !important; min-width: 100% !important; max-width: 100% !important; table-layout: fixed !important; box-sizing: border-box !important; }
        #productSalesTable th, #productSalesTable td { border: 1px solid #dee2e6 !important; box-sizing: border-box !important; }
        #productSalesTable th:nth-child(1), #productSalesTable td:nth-child(1) { width: 4% !important; }
        #productSalesTable th:nth-child(2), #productSalesTable td:nth-child(2) { width: 18% !important; }
        #productSalesTable th:nth-child(3), #productSalesTable td:nth-child(3) { width: 10% !important; }
        #productSalesTable th:nth-child(4), #productSalesTable td:nth-child(4) { width: 12% !important; }
        #productSalesTable th:nth-child(5), #productSalesTable td:nth-child(5) { width: 8% !important; }
        #productSalesTable th:nth-child(6), #productSalesTable td:nth-child(6) { width: 12% !important; }
        #productSalesTable th:nth-child(7), #productSalesTable td:nth-child(7) { width: 10% !important; }
        #productSalesTable th:nth-child(8), #productSalesTable td:nth-child(8) { width: 10% !important; }
        #productSalesTable th:nth-child(9), #productSalesTable td:nth-child(9) { width: 8% !important; }
        #productSalesTable th:nth-child(10), #productSalesTable td:nth-child(10) { width: 8% !important; }
    }
</style>
@endsection

@section('specificpagescripts')
<!-- Select2 JS -->
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script>
$(document).ready(function() {
    // Initialize Select2 for product search
    $('#product_id').select2({
        theme: 'bootstrap-5',
        placeholder: 'Type to search product...',
        allowClear: true,
        minimumInputLength: 1,
        ajax: {
            url: '{{ route("api.reports.products.search") }}',
            dataType: 'json',
            delay: 300,
            data: function (params) {
                return {
                    q: params.term || '', // search term
                    page: params.page || 1
                };
            },
            processResults: function (data, params) {
                params.page = params.page || 1;
                if (!data || !data.results) {
                    console.error('Invalid response from server:', data);
                    return { results: [], pagination: { more: false } };
                }
                return {
                    results: data.results.map(function(item) {
                        return {
                            id: item.id,
                            text: item.text,
                            name: item.name,
                            code: item.code
                        };
                    }),
                    pagination: data.pagination || { more: false }
                };
            },
            cache: true,
            error: function(xhr, status, error) {
                console.error('AJAX error:', status, error);
                console.error('Response:', xhr.responseText);
            }
        }
    }).on('select2:open', function() {
        // Auto-focus on search input when dropdown opens
        setTimeout(function() {
            const $searchInput = $('.select2-container--open .select2-search__field');
            if ($searchInput.length > 0) {
                $searchInput[0].focus();
            }
        }, 100);
    });

    // Auto-submit form when product selection changes (including clear)
    $('#product_id').on('change', function() {
        $('#filterForm').submit();
    });
});

function toggleCustomDates() {
    const dateFilter = document.getElementById('date_filter').value;
    const startDateGroup = document.getElementById('start_date_group');
    const endDateGroup = document.getElementById('end_date_group');
    
    if (dateFilter === 'custom') {
        startDateGroup.style.display = 'block';
        endDateGroup.style.display = 'block';
    } else {
        startDateGroup.style.display = 'none';
        endDateGroup.style.display = 'none';
    }
}
</script>
@endsection
