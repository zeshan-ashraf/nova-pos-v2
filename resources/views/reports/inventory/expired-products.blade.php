@extends('dashboard.body.main')

@section('container')
<div class="container-fluid">
    <div class="row">
        <div class="col-lg-12">
            <div class="d-flex flex-wrap align-items-center justify-content-between mb-4">
                <div>
                    <h4 class="mb-3">Expired Products Report</h4>
                    <p class="mb-0 text-muted">Products with expiry date on or before today.</p>
                </div>
                <div>
                    <a href="{{ route('reports.index') }}" class="btn btn-secondary"><i class="ri-arrow-left-line mr-1"></i> Back to Reports</a>
                </div>
            </div>
        </div>

        <div class="col-lg-12 mb-3">
            <div class="card report-filter-card border-primary shadow-sm">
                <div class="card-header border-0 py-2">
                    <h6 class="mb-0 text-primary"><i class="ri-filter-3-line mr-1"></i> Filters</h6>
                </div>
                <div class="card-body pt-0">
                    <form action="{{ route('reports.inventory.expired-products') }}" method="GET">
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
            <div class="row">
                <div class="col-md-4 col-lg-3">
                    <div class="card card-block card-stretch card-height shadow-sm sales-report-kpi border-danger h-100">
                        <div class="card-body d-flex align-items-center">
                            <div class="icon iq-icon-box-2 bg-danger-light d-flex align-items-center justify-content-center mr-3 flex-shrink-0">
                                <i class="ri-calendar-event-line text-danger" style="font-size: 1.75rem;"></i>
                            </div>
                            <div class="flex-grow-1 min-w-0">
                                <p class="text-muted mb-0 small font-weight-500">Total Expired Products</p>
                                <h4 class="mb-0 font-weight-bold text-dark">{{ number_format($totalCount ?? 0, 0) }}</h4>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-12">
            <div class="card">
                <div class="card-header bg-primary text-white d-flex align-items-center"><h5 class="mb-0">Expired Products</h5></div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-striped table-sm">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Product</th>
                                    <th>Code</th>
                                    <th>Category</th>
                                    <th class="text-right">Qty</th>
                                    <th>Expire date</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($products ?? [] as $product)
                                <tr>
                                    <td>{{ ($products->currentPage() - 1) * $products->perPage() + $loop->iteration }}</td>
                                    <td>{{ $product->product_name }}</td>
                                    <td>{{ $product->product_code ?? '–' }}</td>
                                    <td>{{ $product->same_shop_category?->name ?? '–' }}</td>
                                    <td class="text-right">{{ number_format((int) ($product->product_store ?? 0), 0) }}</td>
                                    <td>{{ $product->expire_date ? \Carbon\Carbon::parse($product->expire_date)->format('d M Y') : '–' }}</td>
                                </tr>
                                @empty
                                <tr>
                                    <td colspan="6" class="text-center">No expired products for the selected filters.</td>
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
