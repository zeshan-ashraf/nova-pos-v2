@php
    $navbarViewingAs = auth()->check() && \App\Support\ActiveShop::current() && (\App\Support\ActiveShop::isSuperAdmin(auth()->user()) ? session('active_shop_id') !== null : (int) session('active_shop_id') !== (int) auth()->user()->shop_id);
@endphp
<div class="iq-top-navbar" @if($navbarViewingAs) style="border-top: 2px solid #17a2b8;" @endif>
    <div class="iq-navbar-custom">
        <nav class="navbar navbar-expand-lg navbar-light p-0">
            <div class="iq-navbar-logo d-flex align-items-center justify-content-between">
                <i class="ri-menu-line wrapper-menu"></i>
                <a href="{{ route('dashboard') }}" class="header-logo">
                    <img src="{{ (auth()->user()->shop && auth()->user()->shop->logo) ? asset('storage/shops/' . auth()->user()->shop->logo) : asset('assets/images/login/company-logo.png') }}" class="img-fluid rounded-normal" alt="logo">
                </a>
            </div>
            <div class="d-flex align-items-center">
                <button class="navbar-toggler" type="button"
                    data-toggle="collapse" data-target="#navbarSupportedContent"
                    data-bs-toggle="collapse" data-bs-target="#navbarSupportedContent"
                    aria-controls="navbarSupportedContent" aria-expanded="false"
                    aria-label="Toggle navigation">
                    <i class="ri-menu-3-line"></i>
                </button>

                <div class="collapse navbar-collapse d-flex justify-content-between" id="navbarSupportedContent">
                    {{-- Left menu: Quick links (Create Sale Invoice, Create Purchase, Create Sale Return, Create Expense, Reports) --}}
                    <ul class="navbar-nav navbar-list align-items-center navbar-left-menu">
                        @auth
                            @if(auth()->user()->can('advance.pos.menu') || auth()->user()->can('purchases.menu') || auth()->user()->can('sale-returns.menu') || auth()->user()->can('expense.menu') || auth()->user()->can('reports.menu') || auth()->user()->can('product.menu'))
                                @can('product.menu')
                                    <li class="nav-item">
                                        <a href="{{ route('products.index') }}" class="nav-link navbar-quick-link {{ request()->routeIs('products.*') ? 'active' : '' }}">Products</a>
                                    </li>
                                @endcan
                                @can('advance.pos.menu')
                                    <li class="nav-item">
                                        <a href="{{ route('invoice.create') }}" class="nav-link navbar-quick-link {{ request()->routeIs('invoice.create') ? 'active' : '' }}">Create Sale Invoice</a>
                                    </li>
                                @endcan
                                @can('purchases.menu')
                                    <li class="nav-item">
                                        <a href="{{ route('purchases.create') }}" class="nav-link navbar-quick-link {{ request()->routeIs('purchases.create') ? 'active' : '' }}">Create Purchase</a>
                                    </li>
                                @endcan
                                @can('sale-returns.menu')
                                    <li class="nav-item">
                                        <a href="{{ route('sale-returns.create') }}" class="nav-link navbar-quick-link {{ request()->routeIs('sale-returns.*') ? 'active' : '' }}">Create Sale Return</a>
                                    </li>
                                @endcan
                                @can('expense.menu')
                                    <li class="nav-item">
                                        <a href="{{ route('expenses.create') }}" class="nav-link navbar-quick-link {{ request()->routeIs('expenses.create') ? 'active' : '' }}">Create Expense</a>
                                    </li>
                                @endcan
                                @can('reports.menu')
                                    <li class="nav-item">
                                        <a href="{{ route('reports.index') }}" class="nav-link navbar-quick-link {{ request()->routeIs('reports.*') ? 'active' : '' }}">Reports</a>
                                    </li>
                                @endcan
                            @endif
                        @endauth
                    </ul>
                    {{-- Right menu: Shop Switcher (Super Admin only) + Viewing-as badge + User --}}
                    <ul class="navbar-nav navbar-list align-items-center navbar-right-menu">
                        @auth
                            @php
                                $activeShop = \App\Support\ActiveShop::current();
                                $canSwitchShop = \App\Support\ActiveShop::canSwitch(auth()->user());
                                $switchableShops = $canSwitchShop ? \App\Support\ActiveShop::switchable(auth()->user()) : collect();
                                $viewingAsDifferent = $activeShop && auth()->user()->shop_id !== null && (int) session('active_shop_id') !== (int) auth()->user()->shop_id;
                                $isSuperAdmin = \App\Support\ActiveShop::isSuperAdmin(auth()->user());
                                $viewingAsDifferentSuperAdmin = $isSuperAdmin && session('active_shop_id') !== null;
                            @endphp
                            {{-- Viewing-as badge when switched (visible indicator) --}}
                            @if(($viewingAsDifferent || $viewingAsDifferentSuperAdmin) && $activeShop)
                                <li class="nav-item mr-2">
                                    <span class="badge badge-info align-middle">
                                        Viewing as: {{ $activeShop->name }}
                                    </span>
                                    <form action="{{ route('shop-switch.reset') }}" method="POST" class="d-inline ml-1">
                                        @csrf
                                        <button type="submit" class="btn btn-link btn-sm p-0 align-baseline">Return</button>
                                    </form>
                                </li>
                            @endif
                            <li class="nav-item nav-icon dropdown caption-content">
                                @if($canSwitchShop && $switchableShops->isNotEmpty())
                                    <a href="#" class="search-toggle dropdown-toggle d-flex align-items-center px-3 py-2 rounded border bg-white text-dark text-decoration-none" id="dropdownActiveShop"
                                        data-toggle="dropdown" aria-haspopup="true" aria-expanded="false" style="min-height: 40px;">
                                        <i class="ri-store-2-line text-primary mr-2" style="font-size: 1.1rem;"></i>
                                        <span class="font-weight-bold mr-2">{{ $activeShop ? $activeShop->name : 'Select shop' }}</span>
                                        <i class="ri-arrow-down-s-line text-muted" style="font-size: 1.1rem;"></i>
                                    </a>
                                    <div class="iq-sub-dropdown dropdown-menu" aria-labelledby="dropdownActiveShop">
                                        <div class="card shadow-none m-0">
                                            <div class="card-body p-0">
                                                <div class="list-group list-group-flush">
                                                    @foreach($switchableShops as $shopOption)
                                                        <form action="{{ route('shop-switch.switch', $shopOption->id) }}" method="POST">
                                                            @csrf
                                                            <button type="submit" class="list-group-item list-group-item-action d-flex align-items-center justify-content-between w-100 text-left border-0 {{ $activeShop && $activeShop->id === $shopOption->id ? 'active font-weight-bold' : '' }}">
                                                                <span>{{ $shopOption->name }}</span>
                                                                @if($activeShop && $activeShop->id === $shopOption->id)
                                                                    <i class="ri-check-line"></i>
                                                                @endif
                                                            </button>
                                                        </form>
                                                    @endforeach
                                                    <div class="list-group-item"></div>
                                                    <form action="{{ route('shop-switch.reset') }}" method="POST">
                                                        @csrf
                                                        <button type="submit" class="list-group-item list-group-item-action d-flex align-items-center w-100 text-left border-0">
                                                            <i class="ri-arrow-left-circle-line mr-2"></i> Return to My Default Shop
                                                        </button>
                                                    </form>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                @else
                                    <div class="d-flex align-items-center px-3 py-2 rounded border bg-light text-dark" style="min-height: 40px; cursor: default;">
                                        <i class="ri-store-2-line text-primary mr-2" style="font-size: 1.1rem;"></i>
                                        <span class="font-weight-bold">{{ $activeShop ? $activeShop->name : '—' }}</span>
                                    </div>
                                @endif
                            </li>
                        @endauth
                        <li class="nav-item nav-icon dropdown caption-content">
                            <a href="#" class="search-toggle dropdown-toggle" id="dropdownMenuButton4"
                                data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                                <img src="{{ auth()->user()->photo ? asset('storage/profile/'.auth()->user()->photo) : asset('assets/images/user/1.png') }}" class="img-fluid rounded" alt="user">
                            </a>
                            <div class="iq-sub-dropdown dropdown-menu" aria-labelledby="dropdownMenuButton">
                                <div class="card shadow-none m-0">
                                    <div class="card-body p-0 text-center">
                                        <div class="media-body profile-detail text-center">
                                            <img src="{{ asset('assets/images/page-img/profile-bg.jpg') }}" alt="profile-bg"
                                                class="rounded-top img-fluid mb-4">
                                            <img src="{{ auth()->user()->photo ? asset('storage/profile/'.auth()->user()->photo) : asset('assets/images/user/1.png') }}" alt="profile-img"
                                                class="rounded profile-img img-fluid avatar-70">
                                        </div>
                                        <div class="p-3">
                                            <h5 class="mb-1">{{  auth()->user()->name }}</h5>
                                            <p class="mb-0">Since {{ date('d M, Y', strtotime(auth()->user()->created_at)) }}</p>
                                            <div class="d-flex align-items-center justify-content-center mt-3">
                                                <a href="{{ route('profile') }}" class="btn border mr-2">Profile</a>
                                                <form action="{{ route('logout') }}" method="POST">
                                                    @csrf
                                                    <button type="submit" class="btn border">Sign Out</button>
                                                </form>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </li>
                    </ul>
                </div>
            </div>
        </nav>
    </div>
</div>
