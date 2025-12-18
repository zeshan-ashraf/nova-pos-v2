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
                    <h4 class="mb-3">Orders List</h4>
                </div>
                <div>
                    <a href="{{ route('order.index') }}" class="btn btn-danger add-list"><i class="las la-trash mr-3"></i>Clear Search</a>
                </div>
            </div>
        </div>

        <div class="col-lg-12">
            <form action="{{ route('order.index') }}" method="get">
                <div class="d-flex flex-wrap align-items-center justify-content-between">
                    <div class="form-group row">
                        <label for="row" class="col-sm-3 align-self-center">Row:</label>
                        <div class="col-sm-9">
                            <select class="form-control" name="row" onchange="this.form.submit()">
                                <option value="10" @if(request('row') == '10') selected="selected" @endif>10</option>
                                <option value="25" @if(request('row') == '25') selected="selected" @endif>25</option>
                                <option value="50" @if(request('row') == '50') selected="selected" @endif>50</option>
                                <option value="100" @if(request('row') == '100') selected="selected" @endif>100</option>
                            </select>
                        </div>
                    </div>

                    <div class="form-group row">
                        <label class="control-label col-sm-3 align-self-center" for="search">Search:</label>
                        <div class="col-sm-8">
                            <div class="input-group">
                                <input type="text" id="search" class="form-control" name="search" placeholder="Search order" value="{{ request('search') }}">
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
                            <th>Invoice No</th>
                            <th>@sortablelink('customer.name', 'name')</th>
                            <th>@sortablelink('order_date', 'order date')</th>
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
                            <td>{{ $order->customer->name }}</td>
                            <td>{{ $order->order_date }}</td>
                            <td>{{ $order->pay }}</td>
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
                                    <a class="btn btn-info mr-2" data-toggle="tooltip" data-placement="top" title="" data-original-title="Details" href="{{ route('order.orderDetails', $order->id) }}">
                                        Details
                                    </a>
                                    <a class="btn btn-success mr-2" data-toggle="tooltip" data-placement="top" title="" data-original-title="Print" href="{{ route('order.invoiceDownload', $order->id) }}">
                                        Print
                                    </a>
                                    <a class="btn btn-secondary mr-2" data-toggle="tooltip" data-placement="top" title="View Payment Log" data-original-title="View Stock Log" href="{{ route('order.paymentLog', $order->id) }}">
                                        <i class="ri-archive-line mr-0"></i>
                                    </a>
                                    <button type="button" class="btn btn-danger mr-2 border-none" data-toggle="tooltip" data-placement="top" title="" data-original-title="Delete" onclick="showDeleteModal({{ $order->id }})">
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
                        <table class="table table-sm table-borderless">
                            <tr>
                                <td width="40%"><strong>Invoice No:</strong></td>
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
                            <tr>
                                <td><strong>Due Amount:</strong></td>
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
