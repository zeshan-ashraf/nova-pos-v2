@extends('dashboard.body.main')

@section('specificpagestyles')
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
        #stockReportTable { width: 100% !important; min-width: 100% !important; max-width: 100% !important; table-layout: fixed !important; box-sizing: border-box !important; }
        #stockReportTable th, #stockReportTable td { border: 1px solid #dee2e6 !important; box-sizing: border-box !important; }
        #stockReportTable th:nth-child(1), #stockReportTable td:nth-child(1) { width: 4% !important; }
        #stockReportTable th:nth-child(2), #stockReportTable td:nth-child(2) { width: 18% !important; }
        #stockReportTable th:nth-child(3), #stockReportTable td:nth-child(3) { width: 9% !important; }
        #stockReportTable th:nth-child(4), #stockReportTable td:nth-child(4) { width: 11% !important; }
        #stockReportTable th:nth-child(5), #stockReportTable td:nth-child(5) { width: 8% !important; }
        #stockReportTable th:nth-child(6), #stockReportTable td:nth-child(6) { width: 8% !important; }
        #stockReportTable th:nth-child(7), #stockReportTable td:nth-child(7) { width: 10% !important; }
        #stockReportTable th:nth-child(8), #stockReportTable td:nth-child(8) { width: 10% !important; }
        #stockReportTable th:nth-child(9), #stockReportTable td:nth-child(9) { width: 11% !important; }
        #stockReportTable th:nth-child(10), #stockReportTable td:nth-child(10) { width: 11% !important; }
        .badge { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
    }
</style>
@endsection

@section('container')
<div class="container-fluid">
    <div class="row">
        <div class="col-lg-12">
            <div class="d-flex flex-wrap align-items-center justify-content-between mb-4">
                <div>
                    <h4 class="mb-3">Stock Report</h4>
                    <p class="mb-0 text-muted">Current inventory levels by product.</p>
                </div>
                <div class="d-print-none">
                    <a href="{{ route('reports.index') }}" class="btn btn-secondary"><i class="ri-arrow-left-line mr-1"></i> Back to Reports</a>
                </div>
            </div>
        </div>

        <div class="col-lg-12 mb-3 d-print-none">
            <div class="card report-filter-card border-primary shadow-sm">
                <div class="card-header border-0 py-2">
                    <h6 class="mb-0 text-primary"><i class="ri-filter-3-line mr-1"></i> Filters</h6>
                </div>
                <div class="card-body pt-0">
                    <form action="{{ route('reports.inventory.stock') }}" method="GET">
                        <input type="hidden" name="sort" value="{{ $sort ?? 'product_name' }}">
                        <input type="hidden" name="order" value="{{ $order ?? 'asc' }}">
                        <div class="row align-items-end">
                            @if(auth()->user()->shop_id == null)
                            <div class="col-md-3">
                                <label for="shop_id" class="form-label">Shop</label>
                                <select class="form-control" name="shop_id" id="shop_id">
                                    <option value="all" {{ ($shopFilter['selected_shop_id'] ?? '') == 'all' ? 'selected' : '' }}>All Shops</option>
                                    @foreach($shopFilter['shops'] ?? [] as $shop)
                                    <option value="{{ $shop->id }}" {{ ($shopFilter['selected_shop_id'] ?? '') == $shop->id ? 'selected' : '' }}>{{ $shop->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            @endif
                            <div class="col-md-3">
                                <label for="stock_status" class="form-label">Stock Status</label>
                                <select class="form-control" name="stock_status" id="stock_status">
                                    <option value="" {{ request('stock_status') == '' ? 'selected' : '' }}>All</option>
                                    <option value="low" {{ request('stock_status') == 'low' ? 'selected' : '' }}>Low Stock</option>
                                    <option value="out" {{ request('stock_status') == 'out' ? 'selected' : '' }}>Out of Stock</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label for="row" class="form-label">Per page</label>
                                <select class="form-control" name="row" id="row">
                                    <option value="10" {{ ($row ?? 50) == 10 ? 'selected' : '' }}>10</option>
                                    <option value="25" {{ ($row ?? 50) == 25 ? 'selected' : '' }}>25</option>
                                    <option value="50" {{ ($row ?? 50) == 50 ? 'selected' : '' }}>50</option>
                                    <option value="100" {{ ($row ?? 50) == 100 ? 'selected' : '' }}>100</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <button type="submit" class="btn btn-primary"><i class="ri-search-line mr-1"></i> Filter</button>
                                <button type="button" class="btn btn-outline-secondary btn-sm ml-2" onclick="window.print()"><i class="ri-printer-line mr-1"></i> Print</button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>

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
                                <h4 class="mb-0 font-weight-bold text-dark">{{ number_format($totalProducts ?? 0, 0) }}</h4>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 mb-3 mb-md-0">
                    <div class="card card-block card-stretch card-height shadow-sm sales-report-kpi border-warning h-100">
                        <div class="card-body d-flex align-items-center">
                            <div class="icon iq-icon-box-2 bg-warning-light d-flex align-items-center justify-content-center mr-3 flex-shrink-0">
                                <i class="ri-alarm-warning-line text-warning" style="font-size: 1.75rem;"></i>
                            </div>
                            <div class="flex-grow-1 min-w-0">
                                <p class="text-muted mb-0 small font-weight-500">Low Stock</p>
                                <h4 class="mb-0 font-weight-bold text-dark">{{ number_format($lowStockCount ?? 0, 0) }}</h4>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 mb-3 mb-md-0">
                    <div class="card card-block card-stretch card-height shadow-sm sales-report-kpi border-danger h-100">
                        <div class="card-body d-flex align-items-center">
                            <div class="icon iq-icon-box-2 bg-danger-light d-flex align-items-center justify-content-center mr-3 flex-shrink-0">
                                <i class="ri-close-circle-line text-danger" style="font-size: 1.75rem;"></i>
                            </div>
                            <div class="flex-grow-1 min-w-0">
                                <p class="text-muted mb-0 small font-weight-500">Out of Stock</p>
                                <h4 class="mb-0 font-weight-bold text-dark">{{ number_format($outOfStockCount ?? 0, 0) }}</h4>
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
                                <p class="text-muted mb-0 small font-weight-500">Total Stock Value</p>
                                <h4 class="mb-0 font-weight-bold text-dark">{{ number_format($totalStockValue ?? 0, 2) }}</h4>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-12 sales-orders-table-wrap">
            <div class="card">
                <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">Stock by Product</h5>
                    <form action="{{ route('reports.inventory.stock') }}" method="GET" class="d-inline">
                        @if(auth()->user()->shop_id == null)
                        <input type="hidden" name="shop_id" value="{{ $shopFilter['selected_shop_id'] ?? 'all' }}">
                        @endif
                        <input type="hidden" name="stock_status" value="{{ request('stock_status') }}">
                        <input type="hidden" name="sort" value="{{ $sort ?? 'product_name' }}">
                        <input type="hidden" name="order" value="{{ $order ?? 'asc' }}">
                        <select name="row" class="form-control form-control-sm d-inline-block" style="width: auto;" onchange="this.form.submit()">
                            <option value="10" {{ ($row ?? 50) == 10 ? 'selected' : '' }}>10</option>
                            <option value="25" {{ ($row ?? 50) == 25 ? 'selected' : '' }}>25</option>
                            <option value="50" {{ ($row ?? 50) == 50 ? 'selected' : '' }}>50</option>
                            <option value="100" {{ ($row ?? 50) == 100 ? 'selected' : '' }}>100</option>
                        </select>
                    </form>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-striped" id="stockReportTable">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    @php
                                        $currentSort = $sort ?? 'product_name';
                                        $currentOrder = $order ?? 'asc';
                                        $queryParams = request()->only(['shop_id', 'stock_status', 'row', 'sort', 'order']);
                                        $sortUrl = function ($column) use ($queryParams, $currentSort, $currentOrder) {
                                            $order = ($currentSort === $column && $currentOrder === 'asc') ? 'desc' : 'asc';
                                            return route('reports.inventory.stock', array_merge($queryParams, ['sort' => $column, 'order' => $order]));
                                        };
                                    @endphp
                                    <th><a href="{{ $sortUrl('product_name') }}" class="text-decoration-none" style="color: #32BDEA;">Product @if($currentSort === 'product_name')<i class="ri-arrow-{{ $currentOrder === 'asc' ? 'up' : 'down' }}-line"></i>@endif</a></th>
                                    <th><a href="{{ $sortUrl('product_code') }}" class="text-decoration-none" style="color: #32BDEA;">Code @if($currentSort === 'product_code')<i class="ri-arrow-{{ $currentOrder === 'asc' ? 'up' : 'down' }}-line"></i>@endif</a></th>
                                    <th><a href="{{ $sortUrl('category') }}" class="text-decoration-none" style="color: #32BDEA;">Category @if($currentSort === 'category')<i class="ri-arrow-{{ $currentOrder === 'asc' ? 'up' : 'down' }}-line"></i>@endif</a></th>
                                    <th class="text-right"><a href="{{ $sortUrl('product_store') }}" class="text-decoration-none" style="color: #32BDEA;">Qty @if($currentSort === 'product_store')<i class="ri-arrow-{{ $currentOrder === 'asc' ? 'up' : 'down' }}-line"></i>@endif</a></th>
                                    <th>Unit</th>
                                    <th class="text-right">Low threshold</th>
                                    <th>Status</th>
                                    <th class="text-right">Buying price</th>
                                    <th class="text-right">Stock value</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($products ?? [] as $product)
                                @php
                                    $units = app(\App\Support\ProductUnitValidator::class);
                                    $qtyRaw = $units->formatQuantity($product->product_store ?? 0);
                                    $thresholdRaw = $units->formatQuantity($product->low_stock_warning ?? 0);
                                    $isOut = $units->compare($qtyRaw, '0') <= 0;
                                    $isLow = !$isOut && $units->compare($thresholdRaw, '0') > 0 && $units->compare($qtyRaw, $thresholdRaw) <= 0;
                                    $value = \App\Models\Product::moneyFromQuantity($product->product_store ?? 0, $product->buying_price ?? 0);
                                @endphp
                                <tr>
                                    <td>{{ ($products->currentPage() - 1) * $products->perPage() + $loop->iteration }}</td>
                                    <td>{{ $product->product_name }}</td>
                                    <td>{{ $product->product_code ?? '–' }}</td>
                                    <td>{{ $product->category?->name ?? '–' }}</td>
                                    <td class="text-right">{{ $product->formattedQuantity() }}</td>
                                    <td>{{ $product->unitLabel() }}</td>
                                    <td class="text-right">{{ $product->formattedQuantity($product->low_stock_warning ?? 0) }}</td>
                                    <td>
                                        @if($isOut)
                                        <span class="badge bg-danger">Out of stock</span>
                                        @elseif($isLow)
                                        <span class="badge bg-warning text-dark">Low stock</span>
                                        @else
                                        <span class="badge bg-success">In stock</span>
                                        @endif
                                    </td>
                                    <td class="text-right">{{ number_format($product->buying_price ?? 0, 2) }}</td>
                                    <td class="text-right">{{ number_format((float) $value, 2) }}</td>
                                </tr>
                                @empty
                                <tr>
                                    <td colspan="10" class="text-center">No products match the selected filters.</td>
                                </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                    @if(isset($products) && $products->hasPages())
                    <div class="mt-3">
                        {{ $products->appends(request()->query())->links() }}
                    </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
