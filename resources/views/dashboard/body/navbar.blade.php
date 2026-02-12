<div class="iq-top-navbar">
    <div class="iq-navbar-custom">
        <nav class="navbar navbar-expand-lg navbar-light p-0">
            <div class="iq-navbar-logo d-flex align-items-center justify-content-between">
                <i class="ri-menu-line wrapper-menu"></i>
                <a href="{{ route('dashboard') }}" class="header-logo">
                    <img src="{{ asset('assets/images/login/company-logo.png') }}" class="img-fluid rounded-normal" alt="logo">
                    <h5 class="logo-title ml-3">Mahab</h5>
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
                    {{-- Right menu: Active Shop + User (aligned to far right with padding) --}}
                    <ul class="navbar-nav navbar-list align-items-center navbar-right-menu">
                        @auth
                            @php
                                $activeShop = \App\Support\ActiveShop::current();
                                $canSwitchShop = \App\Support\ActiveShop::canSwitch(auth()->user());
                                $switchableShops = $canSwitchShop ? \App\Support\ActiveShop::switchable(auth()->user()) : collect();
                            @endphp
                            <li class="nav-item nav-icon dropdown caption-content">
                                @if($canSwitchShop && $switchableShops->isNotEmpty())
                                    <a href="#" class="search-toggle dropdown-toggle" id="dropdownActiveShop"
                                        data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                                        <div class="d-flex align-items-center">
                                            <div class="rounded-circle bg-primary text-white d-flex align-items-center justify-content-center" style="width: 36px; height: 36px;">
                                                <i class="fa-solid fa-store"></i>
                                            </div>
                                            <div class="ml-2 text-left">
                                                <!--<small class="text-muted d-block">Active Shop</small>-->
                                                <span class="font-weight-bold">{{ $activeShop ? $activeShop->name : 'Select shop' }}</span>
                                            </div>
                                        </div>
                                    </a>
                                @else
                                    <div class="d-flex align-items-center" style="cursor: default;">
                                        <div class="rounded-circle bg-primary text-white d-flex align-items-center justify-content-center" style="width: 36px; height: 36px;">
                                            <i class="fa-solid fa-store"></i>
                                        </div>
                                        <div class="ml-2 text-left">
                                            <!--<small class="text-muted d-block">Active Shop</small>-->
                                            <span class="font-weight-bold">{{ $activeShop ? $activeShop->name : 'Select shop' }}</span>
                                        </div>
                                    </div>
                                @endif
                                @if($canSwitchShop && $switchableShops->isNotEmpty())
                                    <div class="iq-sub-dropdown dropdown-menu" aria-labelledby="dropdownActiveShop">
                                        <div class="card shadow-none m-0">
                                            <div class="card-body p-0">
                                                <div class="list-group list-group-flush">
                                                    @foreach($switchableShops as $shopOption)
                                                        <form action="{{ route('active-shop.update') }}" method="POST">
                                                            @csrf
                                                            <input type="hidden" name="shop_id" value="{{ $shopOption->id }}">
                                                            <button type="submit" class="list-group-item list-group-item-action d-flex align-items-center justify-content-between {{ $activeShop && $activeShop->id === $shopOption->id ? 'active' : '' }}">
                                                                <span>{{ $shopOption->name }}</span>
                                                                @if($activeShop && $activeShop->id === $shopOption->id)
                                                                    <i class="ri-check-line"></i>
                                                                @elseif($shopOption->parent)
                                                                    <small class="text-muted">Child of {{ $shopOption->parent->name }}</small>
                                                                @endif
                                                            </button>
                                                        </form>
                                                    @endforeach
                                                </div>
                                            </div>
                                        </div>
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
