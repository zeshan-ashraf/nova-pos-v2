@extends('dashboard.body.main')

@section('container')
<div class="container-fluid">
    <div class="row">
        <div class="col-lg-12">
            @if (session()->has('success'))
                <div class="alert text-white bg-success" role="alert">
                    <div class="iq-alert-text">{{ session('success') }}</div>
                    <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                    <i class="ri-close-line"></i>
                    </button>
                </div>
            @endif
            @if (session()->has('error'))
                <div class="alert text-white bg-danger" role="alert">
                    <div class="iq-alert-text">{{ session('error') }}</div>
                    <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                    <i class="ri-close-line"></i>
                    </button>
                </div>
            @endif
            <div class="d-flex flex-wrap align-items-center justify-content-between mb-4">
                <div>
                    <h4 class="mb-3">Product List</h4>
                    <p class="mb-0">A product dashboard lets you easily gather and visualize product data from optimizing <br>
                        the product experience, ensuring product retention. </p>
                </div>
                <div>
                <a href="{{ route('products.importView') }}" class="btn btn-success add-list">Import</a>
                <a href="{{ route('products.exportData') }}" class="btn btn-warning add-list">Export</a>
                <a href="{{ route('products.create') }}" class="btn btn-primary add-list">Add Product</a>
                </div>
            </div>
        </div>

        <div class="col-lg-12">
            <form action="{{ route('products.index') }}" method="get">
                <div class="d-flex flex-wrap align-items-center justify-content-between">
                    <div class="form-group row">
                        <label for="row" class="col-sm-3 align-self-center">Row:</label>
                        <div class="col-sm-9">
                            <select class="form-control" name="row" onchange="this.form.submit()">
                                <option value="10" @if(request('row') == '10')selected="selected"@endif>10</option>
                                <option value="25" @if(request('row') == '25')selected="selected"@endif>25</option>
                                <option value="50" @if(request('row') == '50')selected="selected"@endif>50</option>
                                <option value="100" @if(request('row') == '100')selected="selected"@endif>100</option>
                            </select>
                        </div>
                    </div>

                    <div class="form-group row">
                        <label class="control-label col-sm-3 align-self-center" for="search">Search:</label>
                        <div class="input-group col-sm-8">
                            <input type="text" id="search" class="form-control" name="search" placeholder="Search product" value="{{ request('search') }}">
                            <div class="input-group-append">
                                <button type="submit" class="input-group-text bg-primary"><i class="las la-search"></i></button>
                                <a href="{{ route('products.index') }}" class="input-group-text bg-danger"><i class="las la-trash"></i></a>
                            </div>
                        </div>
                    </div>
                    @if(isset($isSuperAdmin) && $isSuperAdmin && isset($shops) && $shops->isNotEmpty())
                    <div class="form-group row">
                        <label class="control-label col-sm-3 align-self-center" for="shop_id">Filter by Shop:</label>
                        <div class="col-sm-8">
                            <select class="form-control" name="shop_id" onchange="this.form.submit()">
                                <option value="">All Shops</option>
                                @foreach ($shops as $shop)
                                    <option value="{{ $shop->id }}" {{ request('shop_id') == $shop->id ? 'selected' : '' }}>
                                        {{ $shop->name }}
                                        @if($shop->is_parent)
                                            (Parent)
                                        @endif
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    @endif
                </div>
            </form>
        </div>

        <div class="col-lg-12">
            <div class="table-responsive rounded mb-3">
                <table class="table mb-0">
                    <thead class="bg-white text-uppercase">
                        <tr class="ligth ligth-data">
                            <th>No.</th>
                            <th>@sortablelink('product_name', 'name')</th>
                            <th>@sortablelink('category.name', 'category')</th>
                            <th>@sortablelink('supplier.name', 'supplier')</th>
                            <th>Shop</th>
                            <th>Cost</th>
                            <th>Stock</th>
                            <th>Status</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody class="ligth-body">
                        @forelse ($products as $product)
                        <tr>
                            <td>{{ (($products->currentPage() * $products->perPage()) - $products->perPage()) + $loop->iteration  }}</td>
                            <td>{{ $product->product_name }}</td>
                            <td>{{ $product->category->name }}</td>
                            <td>{{ $product->supplier ? $product->supplier->name : 'N/A' }}</td>
                            <td>
                                @if($product->shop)
                                    <span class="badge bg-primary">{{ $product->shop->name }}</span>
                                    @if($product->shop->is_parent)
                                        <small class="text-muted d-block">Parent Shop</small>
                                    @elseif($product->shop->parent)
                                        <small class="text-muted d-block">Child of {{ $product->shop->parent->name }}</small>
                                    @endif
                                @else
                                    <span class="badge bg-secondary">Unassigned</span>
                                @endif
                            </td>
                            <td>{{ $product->buying_price }}</td>
                            <td>
                                @php
                                    $stock = $product->product_store ?? 0;
                                    $lowStockThreshold = $product->low_stock_warning ?? 10;
                                    
                                    if ($stock == 0) {
                                        $badgeClass = 'bg-danger';
                                        $badgeText = 'Out of Stock';
                                    } elseif ($stock < $lowStockThreshold) {
                                        $badgeClass = 'bg-warning';
                                        $badgeText = 'Low Stock';
                                    } else {
                                        $badgeClass = 'bg-success';
                                        $badgeText = 'In Stock';
                                    }
                                @endphp
                                <span class="badge rounded-pill {{ $badgeClass }}">{{ $stock }}</span>
                                @if ($stock < $lowStockThreshold)
                                    <small class="text-muted d-block">{{ $badgeText }}</small>
                                @endif
                            </td>
                            <td>
                                @if ($product->status === 'active')
                                    <span class="badge rounded-pill bg-success">Valid</span>
                                @else
                                    <span class="badge rounded-pill bg-danger">Invalid</span>
                                @endif
                            </td>
                            <td>
                                <form action="{{ route('products.destroy', $product->id) }}" method="POST" style="margin-bottom: 5px">
                                    @method('delete')
                                    @csrf
                                    <div class="d-flex align-items-center list-action">
                                        <a class="btn btn-info mr-2" data-toggle="tooltip" data-placement="top" title="" data-original-title="View"
                                            href="{{ route('products.show', $product->id) }}"><i class="ri-eye-line mr-0"></i>
                                        </a>
                                        <a class="btn btn-success mr-2" data-toggle="tooltip" data-placement="top" title="" data-original-title="Edit"
                                            href="{{ route('products.edit', $product->id) }}"><i class="ri-pencil-line mr-0"></i>
                                        </a>
                                        <a class="btn btn-secondary mr-2" data-toggle="tooltip" data-placement="top" title="View Stock Log" data-original-title="View Stock Log"
                                        href="{{ route('order.stockLog', $product->id) }}">
                                        <i class="ri-archive-line mr-0"></i>
                                     </a>


                                            <button type="submit" class="btn btn-warning mr-2 border-none" onclick="return confirm('Are you sure you want to delete this record?')" data-toggle="tooltip" data-placement="top" title="" data-original-title="Delete"><i class="ri-delete-bin-line mr-0"></i></button>
                                    </div>
                                </form>
                            </td>
                        </tr>

                        @empty
                        <div class="alert text-white bg-danger" role="alert">
                            <div class="iq-alert-text">Data not Found.</div>
                            <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                            <i class="ri-close-line"></i>
                            </button>
                        </div>
                        @endforelse
                    </tbody>
                </table>
            </div>
            {{ $products->appends(request()->query())->links() }}
        </div>
    </div>
    <!-- Page end  -->
</div>

@endsection
