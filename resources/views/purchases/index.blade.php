@extends('dashboard.body.main')

@section('specificpagestyles')
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<link href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" rel="stylesheet" />
<style>
#purchases-filter-form .form-control,
#purchases-filter-form .select2-container { max-width: 100%; box-sizing: border-box; }
#purchases-filter-form .row [class^="col-"] { min-width: 0; }

/* Same KPI strip as orders/all */
.orders-page-kpis .orders-kpi-strip {
    display: flex;
    flex-wrap: wrap;
    align-items: stretch;
    gap: 0.75rem 1rem;
}
.orders-page-kpis .orders-kpi-col {
    flex: 0 1 auto;
    width: 100%;
    max-width: 100%;
}
@media (min-width: 576px) and (max-width: 991.98px) {
    .orders-page-kpis .orders-kpi-col {
        flex: 1 1 calc((100% - 1rem) / 2);
        min-width: 0;
        max-width: calc((100% - 1rem) / 2);
    }
}
@media (min-width: 992px) {
    .orders-page-kpis .orders-kpi-strip { flex-wrap: nowrap; }
    .orders-page-kpis .orders-kpi-col {
        flex: 1 1 0;
        min-width: 0;
        max-width: none;
    }
}
.orders-page-kpis .orders-kpi-card {
    position: relative;
    border: 1px solid rgba(30, 41, 59, 0.16);
    border-radius: 14px;
    background: #fff;
    width: 100%;
    box-shadow:
        0 1px 0 rgba(255, 255, 255, 0.9) inset,
        0 1px 2px rgba(15, 23, 42, 0.05),
        0 4px 12px rgba(67, 89, 113, 0.07);
    overflow: hidden;
    transition: border-color 0.2s ease, box-shadow 0.2s ease;
}
.orders-page-kpis .orders-kpi-card:hover {
    border-color: rgba(30, 41, 59, 0.26);
    box-shadow:
        0 1px 0 rgba(255, 255, 255, 0.9) inset,
        0 2px 4px rgba(15, 23, 42, 0.06),
        0 8px 20px rgba(67, 89, 113, 0.1);
}
.orders-page-kpis .orders-kpi-card .card-body { padding: 0.85rem 1rem 0.75rem; }
.orders-page-kpis .orders-kpi-top {
    display: flex;
    align-items: flex-start;
    gap: 0.65rem;
    margin-bottom: 0.5rem;
}
.orders-page-kpis .orders-kpi-main { flex: 1; min-width: 0; }
.orders-page-kpis .orders-kpi-icon-wrap {
    width: 48px;
    height: 48px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.2rem;
    flex-shrink: 0;
    border: 1px solid rgba(30, 41, 59, 0.08);
    box-shadow: 0 1px 2px rgba(15, 23, 42, 0.04) inset;
}
.orders-page-kpis .orders-kpi-card--primary .orders-kpi-icon-wrap {
    background: rgba(13, 110, 253, 0.1);
    color: #0a58ca;
    border-color: rgba(13, 110, 253, 0.22);
}
.orders-page-kpis .orders-kpi-card--success .orders-kpi-icon-wrap {
    background: rgba(25, 135, 84, 0.1);
    color: #146c43;
    border-color: rgba(25, 135, 84, 0.22);
}
.orders-page-kpis .orders-kpi-card--info .orders-kpi-icon-wrap {
    background: rgba(13, 202, 240, 0.12);
    color: #0aa2c0;
    border-color: rgba(13, 202, 240, 0.28);
}
.orders-page-kpis .orders-kpi-card--warning .orders-kpi-icon-wrap {
    background: rgba(255, 193, 7, 0.15);
    color: #cc9a06;
    border-color: rgba(255, 193, 7, 0.35);
}
.orders-page-kpis .orders-kpi-value {
    font-size: 1.6rem;
    font-weight: 700;
    line-height: 1.15;
    color: #1e293b;
    letter-spacing: -0.02em;
    font-variant-numeric: tabular-nums;
    word-break: break-word;
}
.orders-page-kpis .orders-kpi-label {
    font-size: 0.875rem;
    font-weight: 600;
    color: #566a7f;
    margin-bottom: 0.25rem;
    line-height: 1.3;
}
.orders-page-kpis .orders-kpi-meta {
    font-size: 0.78rem;
    color: #8592a3;
    line-height: 1.35;
    max-width: 36em;
}
.orders-page-kpis .orders-kpi-accent {
    position: absolute;
    left: 0;
    right: 0;
    bottom: 0;
    height: 3px;
    border-radius: 0 0 14px 14px;
}
.orders-page-kpis .orders-kpi-card--primary .orders-kpi-accent {
    background: linear-gradient(90deg, #0d6efd, #4dabf7);
}
.orders-page-kpis .orders-kpi-card--success .orders-kpi-accent {
    background: linear-gradient(90deg, #198754, #51cf66);
}
.orders-page-kpis .orders-kpi-card--info .orders-kpi-accent {
    background: linear-gradient(90deg, #17a2b8, #3dd5f3);
}
.orders-page-kpis .orders-kpi-card--warning .orders-kpi-accent {
    background: linear-gradient(90deg, #ffc107, #ffda6a);
}

/* Same right drawer shell as orders/all */
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
.order-drawer.open { right: 0; }
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
.order-drawer-header-spacer { width: 1.5rem; flex: 0 0 1.5rem; }
.order-drawer-close {
    border: 0;
    background: transparent;
    font-size: 1.5rem;
    line-height: 1;
    color: #566a7f;
    padding: 0;
}
.order-drawer-close:hover { color: #111; }
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
.content-page #purchaseDrawer .table {
    background: #ffffff;
    border-radius: 0.35rem;
    overflow: hidden;
    margin-bottom: 0;
}
.content-page #purchaseDrawer .table thead th {
    background-color: #a7e7fc !important;
    color: #000 !important;
    border-color: #0b5ed7;
    font-weight: 600;
    font-size: 0.8rem;
    text-transform: uppercase;
    letter-spacing: 0.02em;
}
.content-page #purchaseDrawer .table tbody td {
    color: #344054;
    border-color: #e9ecef;
}
</style>
@endsection

@section('container')
@php
    $dateRange = $dateRange ?? [];
    $dateFilter = $dateRange['date_filter'] ?? 'all';
@endphp
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
                    <h4 class="mb-3">Purchases List</h4>
                </div>
                <div>
                    <a href="{{ route('purchases.create') }}" class="btn btn-primary add-list"><i class="fas fa-plus mr-3"></i>Create Purchase</a>
                    <a href="{{ route('purchases.index') }}" class="btn btn-danger add-list" title="Clear all filters and search"><i class="las la-trash mr-3"></i>Clear filters</a>
                </div>
            </div>
        </div>

        <div class="col-lg-12 mb-3">
            <div class="card report-filter-card border-primary shadow-sm">
                <div class="card-header border-0 py-2">
                    <h6 class="mb-0 text-primary"><i class="ri-filter-3-line mr-1"></i> Filters</h6>
                </div>
                <div class="card-body pt-0">
                    <form action="{{ route('purchases.index') }}" method="get" id="purchases-filter-form">
                        <input type="hidden" name="row" value="{{ request('row', '50') }}">
                        <input type="hidden" name="search" value="{{ request('search') }}">
                        @if(request()->has('sort'))
                            <input type="hidden" name="sort" value="{{ request('sort') }}">
                        @endif
                        @if(request()->has('direction'))
                            <input type="hidden" name="direction" value="{{ request('direction') }}">
                        @endif
                        <div class="row align-items-end mb-3">
                            <div class="col-md-3 mb-2 mb-md-0">
                                <label for="date_filter" class="form-label">Date Filter</label>
                                <select class="form-control" name="date_filter" id="date_filter" onchange="togglePurchaseCustomDates()">
                                    <option value="all" {{ $dateFilter == 'all' ? 'selected' : '' }}>All</option>
                                    <option value="today" {{ $dateFilter == 'today' ? 'selected' : '' }}>Today</option>
                                    <option value="yesterday" {{ $dateFilter == 'yesterday' ? 'selected' : '' }}>Yesterday</option>
                                    <option value="this_week" {{ $dateFilter == 'this_week' ? 'selected' : '' }}>This Week</option>
                                    <option value="last_week" {{ $dateFilter == 'last_week' ? 'selected' : '' }}>Last Week</option>
                                    <option value="this_month" {{ $dateFilter == 'this_month' ? 'selected' : '' }}>This Month</option>
                                    <option value="last_month" {{ $dateFilter == 'last_month' ? 'selected' : '' }}>Last Month</option>
                                    <option value="this_year" {{ $dateFilter == 'this_year' ? 'selected' : '' }}>This Year</option>
                                    <option value="last_year" {{ $dateFilter == 'last_year' ? 'selected' : '' }}>Last Year</option>
                                    <option value="custom" {{ $dateFilter == 'custom' ? 'selected' : '' }}>Custom Range</option>
                                </select>
                            </div>
                            <div class="col-md-3 mb-2 mb-md-0">
                                <label for="supplier_id" class="form-label">Supplier</label>
                                <select name="supplier_id" id="supplier_id" class="form-control purchase-supplier-select" style="width: 100%;">
                                    <option value="">— All —</option>
                                    @foreach ($suppliers ?? [] as $supplier)
                                        <option value="{{ $supplier->id }}" {{ (string) request('supplier_id') === (string) $supplier->id ? 'selected' : '' }}>
                                            {{ $supplier->shopname ?? $supplier->name ?? ('Supplier #' . $supplier->id) }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-3 mb-2 mb-md-0">
                                <label for="product_id" class="form-label">Product</label>
                                <select name="product_id" id="product_id" class="form-control purchase-product-filter-select" style="width: 100%;">
                                    <option value="">— All —</option>
                                    @if (!empty($selectedProduct))
                                        <option value="{{ $selectedProduct->id }}" selected>
                                            {{ ($selectedProduct->product_code ? $selectedProduct->product_code . ' - ' : '') . ($selectedProduct->product_name ?? ('Product #' . $selectedProduct->id)) }}
                                        </option>
                                    @endif
                                </select>
                            </div>
                            <div class="col-md-3 mb-2 mb-md-0">
                                <label for="purchase_invoice_no" class="form-label">Purchase invoice no.</label>
                                <input type="text" class="form-control" name="purchase_invoice_no" id="purchase_invoice_no" placeholder="Partial match" value="{{ request('purchase_invoice_no') }}">
                            </div>
                        </div>
                        <div class="row align-items-end">
                            <div class="col-md-2 mb-2 mb-md-0" id="purchase_start_date_group" style="display: {{ $dateFilter == 'custom' ? 'block' : 'none' }};">
                                <label for="start_date" class="form-label">Start Date</label>
                                <input type="date" class="form-control" name="start_date" id="start_date" value="{{ $dateRange['start_date'] ?? '' }}">
                            </div>
                            <div class="col-md-2 mb-2 mb-md-0" id="purchase_end_date_group" style="display: {{ $dateFilter == 'custom' ? 'block' : 'none' }};">
                                <label for="end_date" class="form-label">End Date</label>
                                <input type="date" class="form-control" name="end_date" id="end_date" value="{{ $dateRange['end_date'] ?? '' }}">
                            </div>
                            <div class="col-md-2 mb-2 mb-md-0 d-flex align-items-end flex-wrap">
                                <button type="submit" class="btn btn-primary px-3 py-2 mr-2 mb-2 mb-md-0">
                                    <i class="ri-search-line mr-1"></i> Filter
                                </button>
                                <a href="{{ route('purchases.index') }}" class="btn btn-outline-secondary px-3 py-2" title="Clear all filters">Clear</a>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-lg-12 mb-3">
            <form action="{{ route('purchases.index') }}" method="get" id="purchases-toolbar-form">
                @foreach (request()->except(['row', 'search', 'page']) as $key => $value)
                    @if (is_array($value))
                        @foreach ($value as $v)
                            <input type="hidden" name="{{ $key }}[]" value="{{ $v }}">
                        @endforeach
                    @else
                        <input type="hidden" name="{{ $key }}" value="{{ $value }}">
                    @endif
                @endforeach
                <div class="d-flex flex-wrap align-items-center justify-content-between">
                    <div class="form-group row mb-0">
                        <label for="row" class="col-sm-3 align-self-center col-form-label col-form-label-sm">Row:</label>
                        <div class="col-sm-9">
                            <select class="form-control form-control-sm" name="row" onchange="this.form.submit()">
                                <option value="10" {{ request('row') == '10' ? 'selected' : '' }}>10</option>
                                <option value="25" {{ request('row') == '25' ? 'selected' : '' }}>25</option>
                                <option value="50" {{ request('row', '50') == '50' ? 'selected' : '' }}>50</option>
                                <option value="100" {{ request('row') == '100' ? 'selected' : '' }}>100</option>
                            </select>
                        </div>
                    </div>
                    <div class="form-group row mb-0">
                        <label class="control-label col-sm-3 align-self-center col-form-label col-form-label-sm" for="search">Search:</label>
                        <div class="col-sm-8">
                            <div class="input-group input-group-sm flex-nowrap">
                                <input type="text" id="search" class="form-control" name="search" placeholder="Search purchase" value="{{ request('search') }}" style="min-width: 200px;">
                                <div class="input-group-append">
                                    <button type="submit" class="input-group-text bg-primary"><i class="las la-search"></i></button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </form>
        </div>

        @php
            $stats = $purchaseStats ?? [
                'total_purchases' => 0,
                'total_amount' => 0,
                'avg_purchase_value' => 0,
                'largest_purchase' => 0,
            ];
        @endphp
        <div class="col-lg-12 mb-3 orders-page-kpis">
            <div class="orders-kpi-strip">
                <div class="orders-kpi-col">
                    <div class="card orders-kpi-card orders-kpi-card--primary h-100">
                        <div class="card-body">
                            <div class="orders-kpi-top">
                                <div class="orders-kpi-icon-wrap" aria-hidden="true">
                                    <i class="fas fa-shopping-cart"></i>
                                </div>
                                <div class="orders-kpi-main">
                                    <div class="orders-kpi-value">{{ number_format($stats['total_purchases']) }}</div>
                                </div>
                            </div>
                            <div class="orders-kpi-label">Total purchases</div>
                            <div class="orders-kpi-meta">Matching current filters</div>
                        </div>
                        <div class="orders-kpi-accent" aria-hidden="true"></div>
                    </div>
                </div>
                <div class="orders-kpi-col">
                    <div class="card orders-kpi-card orders-kpi-card--success h-100">
                        <div class="card-body">
                            <div class="orders-kpi-top">
                                <div class="orders-kpi-icon-wrap" aria-hidden="true">
                                    <i class="fas fa-money-bill-wave"></i>
                                </div>
                                <div class="orders-kpi-main">
                                    <div class="orders-kpi-value">{{ number_format($stats['total_amount'], 2) }}</div>
                                </div>
                            </div>
                            <div class="orders-kpi-label">Total amount</div>
                            <div class="orders-kpi-meta">Sum of purchase totals</div>
                        </div>
                        <div class="orders-kpi-accent" aria-hidden="true"></div>
                    </div>
                </div>
                <div class="orders-kpi-col">
                    <div class="card orders-kpi-card orders-kpi-card--info h-100">
                        <div class="card-body">
                            <div class="orders-kpi-top">
                                <div class="orders-kpi-icon-wrap" aria-hidden="true">
                                    <i class="fas fa-balance-scale"></i>
                                </div>
                                <div class="orders-kpi-main">
                                    <div class="orders-kpi-value">{{ number_format($stats['avg_purchase_value'], 2) }}</div>
                                </div>
                            </div>
                            <div class="orders-kpi-label">Avg purchase value</div>
                            <div class="orders-kpi-meta">Total amount ÷ purchase count</div>
                        </div>
                        <div class="orders-kpi-accent" aria-hidden="true"></div>
                    </div>
                </div>
                <div class="orders-kpi-col">
                    <div class="card orders-kpi-card orders-kpi-card--warning h-100">
                        <div class="card-body">
                            <div class="orders-kpi-top">
                                <div class="orders-kpi-icon-wrap" aria-hidden="true">
                                    <i class="fas fa-arrow-up"></i>
                                </div>
                                <div class="orders-kpi-main">
                                    <div class="orders-kpi-value">{{ number_format($stats['largest_purchase'], 2) }}</div>
                                </div>
                            </div>
                            <div class="orders-kpi-label">Largest purchase</div>
                            <div class="orders-kpi-meta">Max purchase total in range</div>
                        </div>
                        <div class="orders-kpi-accent" aria-hidden="true"></div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-12">
            <div class="table-responsive rounded mb-3">
                <table class="table mb-0">
                    <thead class="bg-white text-uppercase">
                        <tr class="ligth ligth-data">
                            <th>No.</th>
                            <th>@sortablelink('purchase_no', 'Purchase No')</th>
                            <th>@sortablelink('supplier.shopname', 'Supplier')</th>
                            <th>@sortablelink('purchase_date', 'Purchase Date')</th>
                            <th>@sortablelink('total', 'Total')</th>
                            <th>Expenses</th>
                            <th>@sortablelink('pay')</th>
                            <th>Payment</th>
                            <th>Status</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody class="ligth-body">
                        @foreach ($purchases as $purchase)
                        <tr>
                            <td>{{ (($purchases->currentPage() * $purchases->perPage()) - $purchases->perPage()) + $loop->iteration }}</td>
                            <td>
                                <a href="javascript:void(0)"
                                   class="open-purchase-drawer text-primary fw-bold"
                                   data-id="{{ $purchase->id }}"
                                   data-label="{{ e($purchase->purchase_no ?: '#' . $purchase->id) }}">
                                    {{ $purchase->purchase_no ?: '#' . $purchase->id }}
                                </a>
                            </td>
                            <td>{{ $purchase->supplier->shopname ?? $purchase->supplier->name ?? 'N/A' }}</td>
                            <td>{{ $purchase->purchase_date }}</td>
                            <td>{{ number_format($purchase->total ?? 0, 2) }}</td>
                            <td>{{ number_format($purchase->activities_sum_activity_cost ?? 0, 2) }}</td>
                            <td>{{ number_format($purchase->pay ?? 0, 2) }}</td>
                            <td>{{ $purchase->payment_status }}</td>
                            <td>
                                <span class="badge
                                    @if($purchase->purchase_status == 'complete' || $purchase->purchase_status == \App\Support\InterShopTransferStatus::COMPLETED)
                                        badge-success
                                    @elseif($purchase->purchase_status == 'pending' || $purchase->purchase_status == \App\Support\InterShopTransferStatus::PENDING)
                                        badge-danger
                                    @elseif($purchase->purchase_status == \App\Support\InterShopTransferStatus::APPROVED)
                                        badge-info
                                    @elseif($purchase->purchase_status == \App\Support\InterShopTransferStatus::CANCELLED)
                                        badge-secondary
                                    @else
                                        badge-secondary
                                    @endif">
                                    {{ $purchase->purchaseStatusDisplayLabel() }}
                                </span>
                            </td>

                            <td>
                                <div class="d-flex align-items-center list-action">
                                    <a class="btn btn-info mr-2" data-toggle="tooltip" data-placement="top" title="" data-original-title="Details" href="{{ route('purchases.show', $purchase->id) }}">
                                        Details
                                    </a>
                                    @php
                                        $purchaseLinkedToSourceSale = $purchase->source_sale_id !== null && $purchase->source_sale_id !== '' && (int) $purchase->source_sale_id !== 0;
                                    @endphp
                                    @if(($purchase->landed_cost_status ?? 'pending') !== 'approved' && !$purchaseLinkedToSourceSale)
                                    <a class="btn btn-warning mr-2" data-toggle="tooltip" data-placement="top" title="" data-original-title="Edit" href="{{ route('purchases.edit', $purchase->id) }}">
                                        Edit
                                    </a>
                                    @endif
                                    @if(auth()->user()->can('purchases.delete') && !$purchaseLinkedToSourceSale)
                                    <button type="button" class="btn btn-danger mr-2 border-none" data-toggle="tooltip" data-placement="top" title="" data-original-title="Delete" onclick="showDeleteModal({{ $purchase->id }})">
                                        <i class="ri-delete-bin-line mr-0"></i>
                                    </button>
                                    @endif
                                </div>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            {{ $purchases->appends(request()->query())->links() }}
        </div>

    </div>
</div>

<div id="purchaseDrawerBackdrop" class="order-drawer-backdrop d-none"></div>
<div id="purchaseDrawer" class="order-drawer" aria-hidden="true">
    <div class="order-drawer-header">
        <div class="order-drawer-header-spacer" aria-hidden="true"></div>
        <h5 id="purchaseDrawerTitle" class="order-drawer-header-title">Purchase details</h5>
        <button id="closePurchaseDrawer" class="order-drawer-close" type="button" aria-label="Close drawer">&times;</button>
    </div>
    <div id="purchaseDrawerContent" class="order-drawer-body"></div>
</div>

<!-- Delete Confirmation Modal -->
<div class="modal fade" id="deletePurchaseModal" tabindex="-1" role="dialog" aria-labelledby="deletePurchaseModalLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title" id="deletePurchaseModalLabel">Delete Purchase</h5>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to delete this purchase? This will:</p>
                <ul>
                    <li>Reverse stock increases (decrease stock)</li>
                    <li>Reverse supplier credit adjustments</li>
                    <li>Delete payment logs</li>
                </ul>
                <p class="text-danger"><strong>This action cannot be undone!</strong></p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                <form id="deletePurchaseForm" method="POST" style="display: inline;">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="btn btn-danger">Delete Purchase</button>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
function showDeleteModal(purchaseId) {
    $('#deletePurchaseForm').attr('action', `/purchases/${purchaseId}`);
    $('#deletePurchaseModal').modal('show');
}
</script>

@endsection

@section('specificpagescripts')
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script>
    function togglePurchaseCustomDates() {
        var v = document.getElementById('date_filter').value;
        var startGroup = document.getElementById('purchase_start_date_group');
        var endGroup = document.getElementById('purchase_end_date_group');
        if (startGroup) startGroup.style.display = v === 'custom' ? 'block' : 'none';
        if (endGroup) endGroup.style.display = v === 'custom' ? 'block' : 'none';
    }
    document.addEventListener('DOMContentLoaded', function() {
        togglePurchaseCustomDates();

        $('.purchase-supplier-select').select2({
            theme: 'bootstrap-5',
            placeholder: '— All —',
            allowClear: true,
            width: '100%'
        });

        $('.purchase-product-filter-select').select2({
            theme: 'bootstrap-5',
            placeholder: '— All —',
            allowClear: true,
            width: '100%',
            minimumInputLength: 2,
            ajax: {
                url: '{{ route("api.purchases.products.search") }}',
                dataType: 'json',
                delay: 250,
                data: function (params) {
                    return {
                        q: params.term,
                        page: params.page || 1
                    };
                },
                processResults: function (data, params) {
                    params.page = params.page || 1;
                    return {
                        results: (data.results || []).map(function(item) {
                            return { id: item.id, text: item.text };
                        }),
                        pagination: data.pagination || { more: false }
                    };
                },
                cache: true
            }
        });

        $('[data-toggle="tooltip"]').tooltip();

        var purchaseDrawerRouteTemplate = @json(route('purchases.drawer', ['purchase_id' => '__PURCHASE_ID__']));

        function openPurchaseDrawer(purchaseId, label) {
            $('#purchaseDrawerBackdrop').removeClass('d-none');
            $('#purchaseDrawer').addClass('open').attr('aria-hidden', 'false');
            $('body').addClass('overflow-hidden');
            $('#purchaseDrawerTitle').text(label ? String(label) : 'Purchase details');
            $('#purchaseDrawerContent').html('<p class="text-muted mb-0">Loading...</p>');

            var url = purchaseDrawerRouteTemplate.replace('__PURCHASE_ID__', String(purchaseId));
            $.get(url, function (response) {
                $('#purchaseDrawerContent').html(response);
            }).fail(function () {
                $('#purchaseDrawerContent').html('<p class="text-danger mb-0">Failed to load purchase. Please try again.</p>');
            });
        }

        function closePurchaseDrawer() {
            $('#purchaseDrawer').removeClass('open').attr('aria-hidden', 'true');
            $('#purchaseDrawerBackdrop').addClass('d-none');
            $('body').removeClass('overflow-hidden');
            $('#purchaseDrawerTitle').text('Purchase details');
        }

        $(document).on('click', '.open-purchase-drawer', function () {
            var id = $(this).data('id');
            var label = $(this).data('label') || $.trim($(this).text());
            if (!id) return;
            openPurchaseDrawer(String(id), label);
        });

        $('#closePurchaseDrawer, #purchaseDrawerBackdrop').on('click', function () {
            closePurchaseDrawer();
        });

        $(document).on('keydown', function (e) {
            if (e.key === 'Escape' && $('#purchaseDrawer').hasClass('open')) {
                closePurchaseDrawer();
            }
        });
    });
</script>
@endsection
