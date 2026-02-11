@extends('dashboard.body.main')

@section('container')
<div class="container-fluid">
    <div class="row">
        <div class="col-lg-12">
            <div class="d-flex flex-wrap align-items-center justify-content-between mb-4">
                <div>
                    <h4 class="mb-3">Stock Report</h4>
                    <p class="mb-0 text-muted">Current inventory levels by product.</p>
                </div>
                <div>
                    <a href="{{ route('reports.index') }}" class="btn btn-secondary"><i class="ri-arrow-left-line mr-1"></i> Back to Reports</a>
                </div>
            </div>
        </div>

        <div class="col-lg-12 mb-3">
            <div class="card">
                <div class="card-body">
                    <form action="{{ route('reports.inventory.stock') }}" method="GET">
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
                                <button type="button" class="btn btn-info ml-2" onclick="window.print()"><i class="ri-printer-line mr-1"></i> Print</button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-lg-12 mb-3">
            <div class="row">
                <div class="col-md-3">
                    <div class="card">
                        <div class="card-body">
                            <p class="text-muted mb-1">Total Products</p>
                            <h4 class="mb-0">{{ number_format($totalProducts ?? 0, 0) }}</h4>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card">
                        <div class="card-body">
                            <p class="text-muted mb-1">Low Stock</p>
                            <h4 class="mb-0">{{ number_format($lowStockCount ?? 0, 0) }}</h4>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card">
                        <div class="card-body">
                            <p class="text-muted mb-1">Out of Stock</p>
                            <h4 class="mb-0">{{ number_format($outOfStockCount ?? 0, 0) }}</h4>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card">
                        <div class="card-body">
                            <p class="text-muted mb-1">Total Stock Value</p>
                            <h4 class="mb-0">{{ number_format($totalStockValue ?? 0, 2) }}</h4>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-12">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">Stock by Product</h5>
                    <form action="{{ route('reports.inventory.stock') }}" method="GET" class="d-inline">
                        @if(auth()->user()->shop_id == null)
                        <input type="hidden" name="shop_id" value="{{ $shopFilter['selected_shop_id'] ?? 'all' }}">
                        @endif
                        <input type="hidden" name="stock_status" value="{{ request('stock_status') }}">
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
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Product</th>
                                    <th>Code</th>
                                    <th>Category</th>
                                    <th>Shop</th>
                                    <th class="text-right">Qty</th>
                                    <th class="text-right">Low threshold</th>
                                    <th>Status</th>
                                    <th class="text-right">Buying price</th>
                                    <th class="text-right">Stock value</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($products ?? [] as $product)
                                @php
                                    $qty = (int) $product->product_store;
                                    $threshold = (int) ($product->low_stock_warning ?? 0);
                                    $isOut = $qty <= 0;
                                    $isLow = !$isOut && $threshold > 0 && $qty <= $threshold;
                                    $value = $qty * (float) ($product->buying_price ?? 0);
                                    $shopName = 'N/A';
                                    if ($product->shop) {
                                        $shopName = $product->shop->parent ? $product->shop->parent->name . ' – ' . $product->shop->name : $product->shop->name;
                                    }
                                @endphp
                                <tr>
                                    <td>{{ ($products->currentPage() - 1) * $products->perPage() + $loop->iteration }}</td>
                                    <td>{{ $product->product_name }}</td>
                                    <td>{{ $product->product_code ?? '–' }}</td>
                                    <td>{{ $product->same_shop_category?->name ?? '–' }}</td>
                                    <td>{{ $shopName }}</td>
                                    <td class="text-right">{{ number_format($qty, 0) }}</td>
                                    <td class="text-right">{{ number_format($threshold, 0) }}</td>
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
                                    <td class="text-right">{{ number_format($value, 2) }}</td>
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
