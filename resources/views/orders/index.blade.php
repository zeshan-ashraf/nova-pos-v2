@extends('dashboard.body.main')

@section('specificpagestyles')
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<link href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" rel="stylesheet" />
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
            @if (session()->has('error'))
                <div class="alert text-white bg-danger" role="alert">
                    <div class="iq-alert-text">{{ session('error') }}</div>
                    <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                    <i class="ri-close-line"></i>
                    </button>
                </div>
            @endif
            @if (!empty($debugSql))
                <div class="alert alert-secondary mb-3" role="alert">
                    <strong>Debug SQL (add ?debug_sql=1 to URL):</strong>
                    <pre class="mb-0 mt-2 small text-dark" style="white-space: pre-wrap; word-break: break-all; max-height: 200px; overflow: auto;">{{ $debugSql }}</pre>
                </div>
            @endif
            <div class="d-flex flex-wrap align-items-center justify-content-between mb-4">
                <div>
                    <h4 class="mb-3">Orders List</h4>
                </div>
                <div>
                    <a href="{{ route('order.index') }}" class="btn btn-danger add-list" title="Clear all filters and search"><i class="las la-trash mr-3"></i>Clear filters</a>
                </div>
            </div>
        </div>

        @php
            $dateRange = $dateRange ?? [];
            $dateFilter = $dateRange['date_filter'] ?? 'all';
        @endphp
        <div class="col-lg-12">
            <form action="{{ route('order.index') }}" method="get" id="orders-filter-form">
                {{-- Row 1: Filters (date, invoice no, total range, customer, Apply) --}}
                <div class="d-flex flex-wrap align-items-end mb-3">
                    <div class="form-group mr-3 mb-2">
                        <label for="date_filter" class="small mb-0">Date</label>
                        <select class="form-control form-control-sm" name="date_filter" id="date_filter" onchange="toggleOrderCustomDates()">
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
                    <div class="form-group mr-3 mb-2" id="order_start_date_group" style="display: {{ $dateFilter == 'custom' ? 'block' : 'none' }};">
                        <label for="start_date" class="small mb-0">Start Date</label>
                        <input type="date" class="form-control form-control-sm" name="start_date" id="start_date" value="{{ $dateRange['start_date'] ?? '' }}">
                    </div>
                    <div class="form-group mr-3 mb-2" id="order_end_date_group" style="display: {{ $dateFilter == 'custom' ? 'block' : 'none' }};">
                        <label for="end_date" class="small mb-0">End Date</label>
                        <input type="date" class="form-control form-control-sm" name="end_date" id="end_date" value="{{ $dateRange['end_date'] ?? '' }}">
                    </div>
                    <div class="form-group mr-3 mb-2">
                        <label for="invoice_no" class="small mb-0">Invoice No</label>
                        <input type="text" class="form-control form-control-sm" name="invoice_no" id="invoice_no" placeholder="Partial match" value="{{ request('invoice_no') }}" style="min-width: 120px;">
                    </div>
                    <div class="form-group mr-3 mb-2">
                        <label for="total_min" class="small mb-0">Min Total</label>
                        <input type="number" step="0.01" min="0" class="form-control form-control-sm" name="total_min" id="total_min" placeholder="0" value="{{ request('total_min') }}" style="min-width: 100px;">
                    </div>
                    <div class="form-group mr-3 mb-2">
                        <label for="total_max" class="small mb-0">Max Total</label>
                        <input type="number" step="0.01" min="0" class="form-control form-control-sm" name="total_max" id="total_max" placeholder="—" value="{{ request('total_max') }}" style="min-width: 100px;">
                    </div>
                    <div class="form-group mr-3 mb-2">
                        <label for="customer_id" class="small mb-0">Customer</label>
                        <select name="customer_id" id="customer_id" class="form-control customer-select" style="min-width: 320px;">
                            <option value="">— All —</option>
                            @foreach ($customers ?? [] as $customer)
                                <option value="{{ $customer->id }}" {{ request('customer_id') == $customer->id ? 'selected' : '' }}>{{ $customer->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="form-group mb-2">
                        <button type="submit" class="btn btn-primary btn-sm">Apply</button>
                    </div>
                </div>
                {{-- Row 2: Row count + Search (datatable toolbar) --}}
                <div class="d-flex flex-wrap align-items-center justify-content-between mb-3">
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
                                <input type="text" id="search" class="form-control" name="search" placeholder="Search order" value="{{ request('search') }}" style="min-width: 200px;">
                                <div class="input-group-append">
                                    <button type="submit" class="input-group-text bg-primary"><i class="las la-search"></i></button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </form>
        </div>

        {{-- Stats (after filters, same filter applies) --}}
        @php
            $stats = $orderStats ?? ['total_orders' => 0, 'total_amount' => 0];
        @endphp
        <div class="col-lg-12">
            <div class="row mb-3">
                <div class="col-md-6">
                    <div class="card border shadow-none">
                        <div class="card-body py-3 d-flex align-items-center">
                            <i class="fas fa-shopping-cart text-primary mr-3" style="font-size: 1.75rem;"></i>
                            <div class="flex-grow-1">
                                <div class="text-muted" style="font-size: 1rem; padding-bottom: 10px;">Total Orders</div>
                                <div class="font-weight-bold" style="font-size: 1.5rem;">{{ number_format($stats['total_orders']) }}</div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="card border shadow-none">
                        <div class="card-body py-3 d-flex align-items-center">
                            <i class="fas fa-money-bill-wave text-success mr-3" style="font-size: 1.75rem;"></i>
                            <div class="flex-grow-1">
                                <div class="text-muted" style="font-size: 1rem; padding-bottom: 10px;">Total Amount</div>
                                <div class="font-weight-bold" style="font-size: 1.5rem;">{{ number_format($stats['total_amount'], 2) }}</div>
                            </div>
                        </div>
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
                            <th>Invoice No</th>
                            <th>@sortablelink('customer.name', 'name')</th>
                            <th>@sortablelink('order_date', 'order date')</th>
                            <th>@sortablelink('total', 'Total')</th>
                            <th>@sortablelink('pay')</th>
                            <th>Payment</th>
                            <th>Status</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody class="ligth-body">
                        @foreach ($orders as $order)
                        <tr>
                            <td>{{ (($orders->currentPage() * $orders->perPage()) - $orders->perPage()) + $loop->iteration }}</td>
                            <td>{{ $order->invoice_no }}</td>
                            <td>{{ $order->customer?->name ?? $order->customer?->shopname ?? '—' }}</td>
                            <td>{{ $order->order_date }}</td>
                            <td>{{ number_format($order->total ?? 0, 2) }}</td>
                            <td>{{ strtolower($order->payment_status ?? '') === 'credit' ? number_format(0, 2) : number_format($order->pay ?? 0, 2) }}</td>
                            <td>{{ $order->payment_status }}</td>
                            <td>
                                <span class="badge
                                    @if($order->order_status == 'complete')
                                        badge-success
                                    @elseif($order->order_status == 'pending')
                                        badge-danger
                                    @else
                                        badge-secondary
                                    @endif">
                                    {{ $order->order_status }}
                                </span>
                            </td>

                            <td>
                                <div class="d-flex align-items-center list-action">
                                    <a class="btn btn-sm btn-info mr-2" data-toggle="tooltip" data-placement="top" title="" data-original-title="Details" href="{{ route('order.orderDetails', $order->id) }}">
                                        Details
                                    </a>
                                    <a class="btn btn-sm btn-success mr-2" data-toggle="tooltip" data-placement="top" title="" data-original-title="Print" href="{{ route('order.invoiceDownload', $order->id) }}">
                                        Print
                                    </a>
                                    <button type="button" class="btn btn-sm btn-danger mr-2 border-none" data-toggle="tooltip" data-placement="top" title="" data-original-title="Delete" onclick="showDeleteModal({{ $order->id }})">
                                        <i class="ri-delete-bin-line mr-0"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            {{ $orders->appends(request()->query())->links() }}
        </div>

    </div>
    <!-- Page end  -->
</div>

<!-- Delete Confirmation Modal -->
<div class="modal fade" id="deleteOrderModal" tabindex="-1" role="dialog" aria-labelledby="deleteOrderModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title" id="deleteOrderModalLabel">Confirm Order Deletion</h5>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <div class="alert alert-warning" role="alert">
                    <strong><i class="ri-alert-line"></i> Warning:</strong> This action cannot be undone. The following will happen:
                    <ul class="mb-0 mt-2">
                        <li><strong>Stock will be reversed</strong> - All products in this order will have their stock quantities restored</li>
                        <li><strong>Payments will be removed</strong> - All payment records attached to this order will be deleted</li>
                        <li><strong>Customer credit will be adjusted</strong> - Pending amounts and payment credits will be reversed</li>
                    </ul>
                </div>
                
                <div class="card">
                    <div class="card-body">
                        <h6 class="card-title mb-3">Order Information:</h6>
                        <div class="row">
                            <div class="col-md-6">
                                <table class="table table-sm table-borderless mb-0">
                                    <tr>
                                        <td width="50%"><strong>Invoice No:</strong></td>
                                        <td id="modal-invoice-no">-</td>
                                    </tr>
                                    <tr>
                                        <td><strong>Customer:</strong></td>
                                        <td id="modal-customer-name">-</td>
                                    </tr>
                                    <tr>
                                        <td><strong>Order Date:</strong></td>
                                        <td id="modal-order-date">-</td>
                                    </tr>
                                    <tr>
                                        <td><strong>Order Status:</strong></td>
                                        <td id="modal-order-status">-</td>
                                    </tr>
                                    <tr>
                                        <td><strong>Total Amount:</strong></td>
                                        <td id="modal-total">-</td>
                                    </tr>
                                    <tr>
                                        <td><strong>Paid Amount:</strong></td>
                                        <td id="modal-pay">-</td>
                                    </tr>
                                </table>
                            </div>
                            <div class="col-md-6">
                                <table class="table table-sm table-borderless mb-0">
                                    <tr>
                                        <td width="50%"><strong>Due Amount:</strong></td>
                                        <td id="modal-due">-</td>
                                    </tr>
                                    <tr>
                                        <td><strong>Number of Products:</strong></td>
                                        <td id="modal-total-products">-</td>
                                    </tr>
                                    <tr>
                                        <td><strong>Total Stock to Reverse:</strong></td>
                                        <td><span class="badge badge-info" id="modal-total-stock">-</span></td>
                                    </tr>
                                    <tr>
                                        <td><strong>Number of Payments:</strong></td>
                                        <td><span class="badge badge-warning" id="modal-total-payments">-</span></td>
                                    </tr>
                                    <tr>
                                        <td><strong>Total Payment Amount:</strong></td>
                                        <td><span class="badge badge-success" id="modal-total-payment-amount">-</span></td>
                                    </tr>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                <form id="deleteOrderForm" method="POST" style="display: inline;">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="btn btn-danger">
                        <i class="ri-delete-bin-line mr-1"></i> Delete Order
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
function showDeleteModal(orderId) {
    // Show loading state
    $('#deleteOrderModal').modal('show');
    $('#deleteOrderForm').attr('action', `/orders/${orderId}`);
    
    // Reset modal content
    $('#modal-invoice-no').text('Loading...');
    $('#modal-customer-name').text('Loading...');
    $('#modal-order-date').text('Loading...');
    $('#modal-order-status').text('Loading...');
    $('#modal-total').text('Loading...');
    $('#modal-pay').text('Loading...');
    $('#modal-due').text('Loading...');
    $('#modal-total-products').text('Loading...');
    $('#modal-total-stock').text('Loading...');
    $('#modal-total-payments').text('Loading...');
    $('#modal-total-payment-amount').text('Loading...');
    
    // Fetch order information
    $.ajax({
        url: `/orders/${orderId}/delete-info`,
        method: 'GET',
        success: function(response) {
            $('#modal-invoice-no').text(response.order.invoice_no || '-');
            $('#modal-customer-name').text(response.order.customer_name || '-');
            $('#modal-order-date').text(response.order.order_date || '-');
            $('#modal-order-status').html(`<span class="badge ${response.order.order_status === 'complete' ? 'badge-success' : response.order.order_status === 'pending' ? 'badge-danger' : 'badge-secondary'}">${response.order.order_status}</span>`);
            $('#modal-total').text(formatCurrency(response.order.total || 0));
            $('#modal-pay').text(formatCurrency(response.order.pay || 0));
            $('#modal-due').text(formatCurrency(response.order.due || 0));
            $('#modal-total-products').text(response.total_products || 0);
            $('#modal-total-stock').text(response.total_stock_to_reverse || 0);
            $('#modal-total-payments').text(response.total_payments || 0);
            $('#modal-total-payment-amount').text(formatCurrency(response.total_payment_amount || 0));
        },
        error: function(xhr) {
            alert('Failed to load order information. Please try again.');
            $('#deleteOrderModal').modal('hide');
        }
    });
}

function formatCurrency(amount) {
    return new Intl.NumberFormat('en-US', {
        minimumFractionDigits: 0,
        maximumFractionDigits: 0
    }).format(amount);
}
</script>

@endsection

@section('specificpagescripts')
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script>
    function toggleOrderCustomDates() {
        var v = document.getElementById('date_filter').value;
        var startGroup = document.getElementById('order_start_date_group');
        var endGroup = document.getElementById('order_end_date_group');
        if (startGroup) startGroup.style.display = v === 'custom' ? 'block' : 'none';
        if (endGroup) endGroup.style.display = v === 'custom' ? 'block' : 'none';
    }
    document.addEventListener('DOMContentLoaded', function() {
        toggleOrderCustomDates();
        $('.customer-select').select2({
            theme: 'bootstrap-5',
            placeholder: '— All —',
            allowClear: true,
            width: '320px'
        });
    });
</script>
@if(session('open_print_tab') && session('print_order_id'))
<script>
    (function() {
        // Get order_id from session (passed via PHP)
        const orderId = {{ session('print_order_id') }};
        
        // Open invoice download page in new tab
        const printUrl = '{{ route("order.invoiceDownload", ":id") }}'.replace(':id', orderId) + '?print=1';
        window.open(printUrl, '_blank');
    })();
</script>
@endif
@endsection
