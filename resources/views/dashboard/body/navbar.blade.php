@php
    $navbarViewingAs = auth()->check() && \App\Support\ActiveShop::current() && (\App\Support\ActiveShop::isSuperAdmin(auth()->user()) ? session('active_shop_id') !== null : (int) session('active_shop_id') !== (int) auth()->user()->shop_id);
@endphp
<div class="iq-top-navbar" @if($navbarViewingAs) style="border-top: 2px solid #17a2b8;" @endif>
    <div class="iq-navbar-custom">
        <nav class="navbar navbar-expand-lg navbar-light p-0">
            <div class="iq-navbar-logo d-flex align-items-center justify-content-between">
                <i class="ri-menu-line wrapper-menu"></i>
                <a href="{{ route('dashboard') }}" class="header-logo">
                    <img src="{{ auth()->user()->shop?->logo_url ?? asset('assets/images/login/company-logo.png') }}" class="img-fluid rounded-normal" alt="logo">
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
                            @if(auth()->user()->can('advance.pos.menu') || auth()->user()->can('purchases.menu') || auth()->user()->can('sale-returns.menu') || auth()->user()->can('expense.menu') || auth()->user()->can('shop_expense.view') || auth()->user()->can('reports.menu') || auth()->user()->can('product.menu'))
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
                        @auth
                            @php
                                $shopNotifUnread = auth()->user()->shop_id
                                    ? \App\Models\ShopNotification::where('shop_id', auth()->user()->shop_id)->where('is_read', false)->count()
                                    : 0;
                            @endphp
                            @if(auth()->user()->shop_id)
                            <style>
                                .shop-notif-dropdown-panel {
                                    min-width: 320px;
                                    max-width: 360px;
                                    border: 2px solid #ccc !important;
                                    min-height: 200px;
                                    top: 40px !important;
                                    overflow: hidden;
                                    box-shadow: 0 0.5rem 1.25rem rgba(15, 23, 42, 0.12) !important;
                                }
                                .shop-notif-menu-header {
                                    background: linear-gradient(180deg, #edf4ff 0%, #d9e8fa 100%);
                                    border: 1px solid #9bb9e0;
                                    border-bottom: 2px solid #2c6cb0;
                                    box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.75), 0 1px 0 rgba(44, 108, 176, 0.12);
                                }
                                .shop-notif-dropdown-panel .list-group-item.notif-item {
                                    border-left: none;
                                    border-right: none;
                                    padding: 0.65rem 0.85rem;
                                }
                                .shop-notif-icon {
                                    width: 38px;
                                    height: 38px;
                                    border-radius: 10px;
                                    display: flex;
                                    align-items: center;
                                    justify-content: center;
                                    flex-shrink: 0;
                                    font-size: 1.2rem;
                                    line-height: 1;
                                }
                                .shop-notif-icon--purchase {
                                    background: linear-gradient(145deg, #dbeafe 0%, #bfdbfe 100%);
                                    color: #1d4ed8;
                                    box-shadow: 0 1px 2px rgba(29, 78, 216, 0.2);
                                }
                                .shop-notif-icon--order {
                                    background: linear-gradient(145deg, #ffedd5 0%, #fed7aa 100%);
                                    color: #c2410c;
                                    box-shadow: 0 1px 2px rgba(194, 65, 12, 0.2);
                                }
                                .shop-notif-icon--default {
                                    background: linear-gradient(145deg, #f3f4f6 0%, #e5e7eb 100%);
                                    color: #4b5563;
                                }
                                .shop-notif-item-msg {
                                    line-height: 1.35;
                                    word-break: break-word;
                                }
                            </style>
                            <li class="nav-item nav-icon dropdown caption-content mr-2">
                                <a href="#" class="search-toggle dropdown-toggle position-relative d-inline-flex align-items-center justify-content-center p-2 rounded border bg-white" id="shopNotifDropdown"
                                    data-toggle="dropdown" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false" title="Shop notifications"
                                    onclick="setTimeout(function(){ if (window.__loadShopNotifications) { window.__loadShopNotifications(); } }, 150);">
                                    <i class="ri-notification-3-line text-primary" style="font-size: 1.25rem;"></i>
                                    @if($shopNotifUnread > 0)
                                        <span class="badge badge-danger position-absolute" style="top: 2px; right: 2px; font-size: 0.65rem;">{{ $shopNotifUnread > 99 ? '99+' : $shopNotifUnread }}</span>
                                    @endif
                                </a>
                                <div class="dropdown-menu dropdown-menu-right p-0 shop-notif-dropdown-panel" aria-labelledby="shopNotifDropdown">
                                    <div class="d-flex justify-content-between align-items-center px-3 py-2 rounded-top shop-notif-menu-header">
                                        <span class="font-weight-bold small text-uppercase" style="color: #3a5a7a; letter-spacing: 0.03em;">Shop notifications</span>
                                        <button type="button" class="btn btn-link btn-sm p-0" id="shopNotifMarkAll">Mark all read</button>
                                    </div>
                                    <div id="shopNotifList" class="list-group list-group-flush" style="max-height: 360px; overflow-y: auto;">
                                        <div class="px-3 py-3 text-muted small js-shop-notif-placeholder">Open the menu to load notifications.</div>
                                    </div>
                                </div>
                            </li>
                            @endif
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
@auth
@if(auth()->user()->shop_id)
<script>
(function () {
    var notifUrl = @json(route('shop-notifications.index'));
    var readAllUrl = @json(route('shop-notifications.readAll'));
    var orderDetailsPrefix = @json(url('/orders/details'));
    var shopPurchaseRequestPrefix = @json(url('/shop-purchase-requests'));
    var csrfMeta = document.querySelector('meta[name="csrf-token"]');
    var token = csrfMeta ? csrfMeta.getAttribute('content') : '';

    function notifRow(n) {
        var d = n.data || {};
        var rid = d.shop_purchase_request_id || n.shop_purchase_request_id || '';
        var oid = d.order_id || '';
        var msg = (d.message || '').replace(/</g, '&lt;');
        var href = rid ? (shopPurchaseRequestPrefix + '/' + rid) : (oid ? (orderDetailsPrefix + '/' + oid) : '#');
        var muted = n.is_read ? ' text-muted' : ' font-weight-bold';
        var iconWrap = '';
        if (rid) {
            iconWrap = '<span class="shop-notif-icon shop-notif-icon--purchase mr-2" title="Purchase request"><i class="ri-shopping-bag-3-fill" aria-hidden="true"></i></span>';
        } else if (oid) {
            iconWrap = '<span class="shop-notif-icon shop-notif-icon--order mr-2" title="Sale order"><i class="ri-file-list-3-fill" aria-hidden="true"></i></span>';
        } else {
            iconWrap = '<span class="shop-notif-icon shop-notif-icon--default mr-2" title="Notification"><i class="ri-notification-3-fill" aria-hidden="true"></i></span>';
        }
        return '<a href="' + href + '" class="list-group-item list-group-item-action small notif-item d-flex align-items-start' + muted + '" data-id="' + n.id + '">' +
            iconWrap + '<span class="shop-notif-item-msg flex-grow-1">' + msg + '</span></a>';
    }

    var __shopNotifLastFetch = 0;
    function loadShopNotifications() {
        var el = document.getElementById('shopNotifList');
        if (!el) return;
        var now = Date.now();
        if (now - __shopNotifLastFetch < 400) {
            return;
        }
        __shopNotifLastFetch = now;
        el.innerHTML = '<div class="px-3 py-2 text-muted small">Loading…</div>';
        fetch(notifUrl, {
            credentials: 'same-origin',
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        })
            .then(function (r) {
                if (!r.ok) {
                    throw new Error('HTTP ' + r.status);
                }
                return r.json();
            })
            .then(function (data) {
                var rows = data.notifications;
                if (!Array.isArray(rows)) {
                    rows = rows ? Object.values(rows) : [];
                }
                if (!rows.length) {
                    el.innerHTML = '<div class="px-3 py-3 text-muted small">No notifications.</div>';
                    return;
                }
                el.innerHTML = rows.map(notifRow).join('');
                el.querySelectorAll('.notif-item').forEach(function (a) {
                    a.addEventListener('click', function () {
                        var id = a.getAttribute('data-id');
                        if (!id || !token) return;
                        fetch(@json(url('/shop-notifications')) + '/' + id + '/read', {
                            method: 'POST',
                            headers: { 'X-CSRF-TOKEN': token, 'Accept': 'application/json' },
                            credentials: 'same-origin',
                        });
                    });
                });
            })
            .catch(function () {
                el.innerHTML = '<div class="px-3 py-2 text-danger small">Could not load notifications. Refresh the page or check the Network tab for <code>/shop-notifications</code>.</div>';
            });
    }

    // Exposed for inline onclick on the bell (works even when theme does not fire Bootstrap dropdown events).
    window.__loadShopNotifications = loadShopNotifications;
    (function wireBootstrapNotifHook() {
        var toggle = document.getElementById('shopNotifDropdown');
        if (!toggle) {
            return;
        }
        var root = toggle.closest('.dropdown');
        if (root) {
            root.addEventListener('shown.bs.dropdown', function () {
                window.setTimeout(loadShopNotifications, 50);
            });
        }
    })();

    var markAllBtn = document.getElementById('shopNotifMarkAll');
    if (markAllBtn) {
        markAllBtn.addEventListener('click', function (e) {
            e.preventDefault();
            e.stopPropagation();
            if (!token) return;
            fetch(readAllUrl, {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': token, 'Accept': 'application/json' },
                credentials: 'same-origin'
            }).then(function () { window.location.reload(); });
        });
    }
})();
</script>
@endif
@endauth
