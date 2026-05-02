@extends('dashboard.body.main')

@section('specificpagestyles')
<style>
/* Right drawer — same pattern as orders list */
.order-drawer {
    position: fixed;
    top: 0;
    right: -520px;
    width: min(520px, 92vw);
    height: 100%;
    background: #fff;
    box-shadow: -2px 0 14px rgba(0, 0, 0, 0.14);
    transition: right 0.3s ease;
    z-index: 1050;
    display: flex;
    flex-direction: column;
}
.order-drawer.open {
    right: 0;
}
.order-drawer-header {
    padding: 0.9rem 1rem;
    border-bottom: 1px solid #eee;
    display: flex;
    justify-content: space-between;
    align-items: center;
    background-color: #ffd960;
}
.order-drawer-header-title {
    margin: 0;
    text-align: center;
    flex: 1 1 auto;
    font-size: 1.05rem;
}
.order-drawer-header-spacer {
    width: 1.5rem;
    flex: 0 0 1.5rem;
}
.order-drawer-close {
    border: 0;
    background: transparent;
    font-size: 1.5rem;
    line-height: 1;
    color: #566a7f;
    padding: 0;
}
.order-drawer-close:hover {
    color: #111;
}
.order-drawer-body {
    padding: 1rem;
    overflow-y: auto;
    flex: 1 1 auto;
    background: #f8f9fa;
}
.order-drawer-backdrop {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0, 0, 0, 0.4);
    z-index: 1040;
}
.content-page #shopUsersDrawer .table {
    background: #ffffff;
    border-radius: 0.35rem;
    margin-bottom: 0;
}
.content-page #shopUsersDrawer .table thead th {
    background-color: #a7e7fc !important;
    color: #000 !important;
    font-weight: 600;
    font-size: 0.8rem;
}
.content-page #shopUsersDrawer .table tbody td {
    color: #344054;
    border-color: #e9ecef;
}
/* View Users — label color */
.open-shop-users-drawer,
.open-shop-users-drawer:hover,
.open-shop-users-drawer:focus,
.open-shop-users-drawer:active {
    color: #000 !important;
}
</style>
@endsection

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
            <div class="d-flex flex-wrap align-items-center justify-content-between mb-4">
                <div>
                    <h4 class="mb-3">User List</h4>
                </div>
                <div>
                <a href="{{ route('users.create') }}" class="btn btn-primary add-list"><i class="fas fa-plus mr-3"></i>Create User</a>
                <a href="{{ route('users.index') }}" class="btn btn-danger add-list"><i class="las la-trash mr-3"></i>Clear Search</a>
                </div>
            </div>
        </div>

        <div class="col-lg-12">
            <form action="{{ route('users.index') }}" method="get">
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
                        <label class="control-label col-sm-3 align-self-center" for="search">Search:</label>
                        <div class="col-sm-8">
                            <div class="input-group flex-nowrap">
                                <input type="text" id="search" class="form-control" name="search" placeholder="Search user" value="{{ request('search') }}" style="min-width: 200px;">
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
                            <th>Shop @sortablelink('name')</th>
                            <th>@sortablelink('name')</th>
                            <th>@sortablelink('username')</th>
                            <th>Password</th>
                            <th>@sortablelink('email')</th>
                            <th>Role</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody class="ligth-body">
                        @forelse ($users as $item)
                        <tr>
                            <td>{{ (($users->currentPage() - 1) * $users->perPage()) + $loop->iteration }}</td>
                            <td>{{ $item->shop?->name ?? '—' }}</td>
                            <td>{{ $item->name }}</td>
                            <td>{{ $item->username }}</td>
                            <td>{{ $item->actual_password ?? '—' }}</td>
                            <td>{{ $item->email }}</td>
                            <td>
                                @foreach ($item->roles as $role)
                                    <span class="badge bg-danger">{{ $role->name }}</span>
                                @endforeach
                            </td>
                            <td>
                                <div class="d-flex align-items-center list-action flex-wrap">
                                    @if ($item->shop_id)
                                        <button type="button"
                                            class="btn btn-info mr-2 mb-1 open-shop-users-drawer"
                                            data-shop-id="{{ $item->shop_id }}"
                                            data-shop-name="{{ e($item->shop?->name ?? 'Shop') }}"
                                            data-toggle="tooltip"
                                            data-placement="top"
                                            data-original-title="View all users in this shop">View Users</button>
                                    @endif
                                    <form action="{{ route('users.destroy', $item->username) }}" method="POST" class="d-inline-flex align-items-center mb-1">
                                        @method('delete')
                                        @csrf
                                        <a class="btn btn-success mr-2" data-toggle="tooltip" data-placement="top" title="" data-original-title="Edit" href="{{ route('users.edit', $item->username) }}"><i class="ri-pencil-line mr-0"></i>
                                        </a>
                                        <button type="submit" class="btn btn-warning mr-2 border-none" onclick="return confirm('Are you sure you want to delete this record?')" data-toggle="tooltip" data-placement="top" title="" data-original-title="Delete"><i class="ri-delete-bin-line mr-0"></i></button>
                                    </form>
                                </div>
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
            {{ $users->appends(request()->query())->links() }}
        </div>
    </div>
    <!-- Page end  -->
</div>

<div id="shopUsersDrawerBackdrop" class="order-drawer-backdrop d-none"></div>
<div id="shopUsersDrawer" class="order-drawer" aria-hidden="true">
    <div class="order-drawer-header">
        <div class="order-drawer-header-spacer" aria-hidden="true"></div>
        <h5 id="shopUsersDrawerTitle" class="order-drawer-header-title">Shop users</h5>
        <button id="closeShopUsersDrawer" class="order-drawer-close" type="button" aria-label="Close drawer">&times;</button>
    </div>
    <div id="shopUsersDrawerContent" class="order-drawer-body"></div>
</div>

@endsection

@section('specificpagescripts')
<script>
    document.addEventListener('DOMContentLoaded', function () {
        var drawerRouteTemplate = @json(route('users.shopDrawer', ['shop' => '__SHOP_ID__']));
        var $backdrop = $('#shopUsersDrawerBackdrop');
        var $drawer = $('#shopUsersDrawer');
        var $title = $('#shopUsersDrawerTitle');
        var $content = $('#shopUsersDrawerContent');

        function openShopUsersDrawer(shopId, shopName) {
            $backdrop.removeClass('d-none');
            $drawer.addClass('open').attr('aria-hidden', 'false');
            $('body').addClass('overflow-hidden');
            $title.text(shopName || 'Shop users');
            $content.html('<p class="text-muted mb-0">Loading...</p>');

            var url = drawerRouteTemplate.replace('__SHOP_ID__', shopId);
            $.get(url, function (response) {
                $content.html(response);
            }).fail(function () {
                $content.html('<p class="text-danger mb-0">Failed to load users. Please try again.</p>');
            });
        }

        function closeShopUsersDrawer() {
            $drawer.removeClass('open').attr('aria-hidden', 'true');
            $backdrop.addClass('d-none');
            $('body').removeClass('overflow-hidden');
            $title.text('Shop users');
        }

        $(document).on('click', '.open-shop-users-drawer', function () {
            var shopId = $(this).data('shop-id');
            var shopName = $(this).data('shop-name');
            if (!shopId) return;
            openShopUsersDrawer(String(shopId), shopName);
        });

        $('#closeShopUsersDrawer, #shopUsersDrawerBackdrop').on('click', function () {
            closeShopUsersDrawer();
        });

        $(document).on('keydown', function (e) {
            if (e.key === 'Escape' && $drawer.hasClass('open')) {
                closeShopUsersDrawer();
            }
        });
    });
</script>
@endsection
