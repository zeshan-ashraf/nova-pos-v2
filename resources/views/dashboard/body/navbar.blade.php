<div class="iq-top-navbar">
    <div class="iq-navbar-custom">
        <nav class="navbar navbar-expand-lg navbar-light p-0">
            <div class="iq-navbar-logo d-flex align-items-center justify-content-between">
                <i class="ri-menu-line wrapper-menu"></i>
                <a href="{{ route('dashboard') }}" class="header-logo">
                    <img src="../assets/images/logo.png" class="img-fluid rounded-normal" alt="logo">
                    <h5 class="logo-title ml-3">Nova POS</h5>
                </a>
            </div>
            <div class="iq-search-bar device-search">
                <form action="#" class="searchbox">
                    <a class="search-link" href="#"><i class="ri-search-line"></i></a>
                    <input type="text" class="text search-input" placeholder="Search here...">
                </form>
            </div>
            <div class="d-flex align-items-center">
                <button class="navbar-toggler" type="button" data-toggle="collapse"
                    data-target="#navbarSupportedContent" aria-controls="navbarSupportedContent"
                    aria-label="Toggle navigation">
                    <i class="ri-menu-3-line"></i>
                </button>

                <div class="collapse navbar-collapse" id="navbarSupportedContent">
                    <ul class="navbar-nav ml-auto navbar-list align-items-center">
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
                        <li class="nav-item nav-icon search-content">
                            <a href="#" class="search-toggle rounded" id="dropdownSearch" data-toggle="dropdown"
                                aria-haspopup="true" aria-expanded="false">
                                <i class="ri-search-line"></i>
                            </a>
                            <div class="iq-search-bar iq-sub-dropdown dropdown-menu" aria-labelledby="dropdownSearch">
                                <form action="#" class="searchbox p-2">
                                    <div class="form-group mb-0 position-relative">
                                        <input type="text" class="text search-input font-size-12"
                                            placeholder="type here to search...">
                                        <a href="#" class="search-link"><i class="las la-search"></i></a>
                                    </div>
                                </form>
                            </div>
                        </li>
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
