@extends('dashboard.body.main')

@section('container')
<div class="container-fluid">
    <div class="row">
        <div class="col-lg-12">
            @if (session()->has('success'))
                <div class="alert text-white bg-success" role="alert">
                    <div class="iq-alert-text">{!! session('success') !!}</div>
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
                    <h4 class="mb-3">Shop List</h4>
                    <p class="mb-0">Manage your shop hierarchy, update details, and control availability from a single place.</p>
                </div>
                <div class="d-flex">
                    @if (auth()->user()->can('shop.create'))
                        <a href="{{ route('shops.create') }}" class="btn btn-primary add-list mr-2">
                            <i class="fa-solid fa-plus mr-3"></i>Add Shop
                        </a>
                    @endif
                    <a href="{{ route('shops.index') }}" class="btn btn-danger add-list">
                        <i class="las la-trash mr-3"></i>Clear Search
                    </a>
                </div>
            </div>
        </div>

        <div class="col-lg-12">
            <form action="{{ route('shops.index') }}" method="get">
                <div class="d-flex flex-wrap align-items-center justify-content-between">
                    <div class="form-group row">
                        <label for="row" class="col-sm-3 align-self-center">Row:</label>
                        <div class="col-sm-9">
                            <select class="form-control" name="row" onchange="this.form.submit()">
                                <option value="10" @if(request('row') == '10')selected="selected"@endif>10</option>
                                <option value="25" @if(request('row') == '25')selected="selected"@endif>25</option>
                                <option value="50" @if(request('row', '50') == '50')selected="selected"@endif>50</option>
                                <option value="100" @if(request('row') == '100')selected="selected"@endif>100</option>
                            </select>
                        </div>
                    </div>

                    <div class="form-group row">
                        <label class="control-label col-sm-3 align-self-center" for="status">Status:</label>
                        <div class="col-sm-8">
                            <select class="form-control" id="status" name="status">
                                <option value="">All</option>
                                <option value="1" @if(request('status') === '1')selected="selected"@endif>Enabled</option>
                                <option value="0" @if(request('status') === '0')selected="selected"@endif>Disabled</option>
                            </select>
                        </div>
                    </div>

                    <div class="form-group row">
                        <label class="control-label col-sm-3 align-self-center" for="search">Search:</label>
                        <div class="col-sm-8">
                            <div class="input-group flex-nowrap">
                                <input type="text" id="search" class="form-control" name="search" placeholder="Search shop" value="{{ request('search') }}" style="min-width: 200px;">
                                <div class="input-group-append">
                                    <button type="submit" class="input-group-text bg-primary"><i class="las la-search"></i></button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </form>
        </div>

        <div class="col-lg-12">
            <div class="table-responsive rounded mb-3">
                <table class="table mb-0">
                    <thead class="bg-white text-uppercase">
                        <tr class="ligth ligth-data">
                            <th>No.</th>
                            <th>@sortablelink('name', 'Shop Name')</th>
                            <th>@sortablelink('owner_name', 'Owner')</th>
                            <th>@sortablelink('phone', 'Phone')</th>
                            <th>Parent Shop</th>
                            <th>@sortablelink('is_parent', 'Type')</th>
                            <th>@sortablelink('status', 'Status')</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody class="ligth-body">
                        @forelse ($shops as $shop)
                        <tr>
                            <td>{{ (($shops->currentPage() - 1) * $shops->perPage()) + $loop->iteration }}</td>
                            <td>{{ $shop->name }}</td>
                            <td>{{ $shop->owner_name }}</td>
                            <td>{{ $shop->phone }}</td>
                            <td>{{ $shop->is_parent ? '—' : ($shop->parent->name ?? '-') }}</td>
                            <td>
                                @if ($shop->is_parent)
                                    <span class="badge badge-info">Mother</span>
                                @else
                                    <span class="badge badge-secondary">Child</span>
                                @endif
                            </td>
                            <td>
                                @if ($shop->status)
                                    <span class="badge badge-success">Enabled</span>
                                @else
                                    <span class="badge badge-danger">Disabled</span>
                                @endif
                            </td>
                            <td>
                                <div class="d-flex align-items-center list-action">
                                    <a class="badge bg-info mr-2" data-toggle="tooltip" data-placement="top" title="" data-original-title="View"
                                        href="{{ route('shops.show', $shop->id) }}"><i class="ri-eye-line mr-0"></i>
                                    </a>
                                    @if (auth()->user()->can('shop.update'))
                                        <a class="badge bg-success mr-2" data-toggle="tooltip" data-placement="top" title="" data-original-title="Edit"
                                            href="{{ route('shops.edit', $shop->id) }}"><i class="ri-pencil-line mr-0"></i>
                                        </a>
                                    @endif

                                    @if (auth()->user()->can('shop.delete'))
                                        <form action="{{ route('shops.destroy', $shop->id) }}" method="POST" style="margin-bottom: 5px">
                                            @csrf
                                            @method('delete')
                                            <button type="submit" class="badge bg-warning mr-2 border-none"
                                                    onclick="return confirm('Are you sure you want to delete this shop?')"
                                                    data-toggle="tooltip" data-placement="top" title=""
                                                    data-original-title="Delete">
                                                <i class="ri-delete-bin-line mr-0"></i>
                                            </button>
                                        </form>
                                    @endif
                                </div>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="8">
                                <div class="alert text-white bg-danger mb-0" role="alert">
                                    <div class="iq-alert-text">Data not Found.</div>
                                </div>
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            {{ $shops->appends(request()->query())->links() }}
        </div>
    </div>
</div>
@endsection


