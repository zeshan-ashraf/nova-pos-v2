@extends('dashboard.body.main')

@section('specificpagestyles')
    <style>
        .invoice-form-container {
            background: #fff;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .invoice-header {
            border-bottom: 2px solid #e9ecef;
            padding-bottom: 20px;
            margin-bottom: 30px;
        }
        .invoice-header h4 {
            margin: 0;
            color: #333;
        }
        .form-row-invoice {
            margin-bottom: 20px;
        }
        .product-table-wrapper {
            margin: 30px 0;
        }
        .product-table {
            width: 100%;
            border-collapse: collapse;
        }
        .product-table thead {
            background-color: #f8f9fa;
        }
        .product-table th,
        .product-table td {
            padding: 12px;
            border: 1px solid #dee2e6;
            text-align: left;
        }
        .product-table th {
            font-weight: 600;
            color: #495057;
        }
        .product-table .product-name-col {
            width: 25%;
        }
        .product-table .product-code-col {
            width: 10%;
        }
        .product-table .unit-price-col {
            width: 10%;
        }
        .product-table .stock-col {
            width: 8%;
        }
        .product-table .quantity-col {
            width: 10%;
        }
        .product-table .discount-col {
            width: 10%;
        }
        .product-table .total-col {
            width: 12%;
        }
        .invoice-summary {
            margin-top: 30px;
            padding-top: 20px;
            border-top: 2px solid #e9ecef;
        }
        .summary-row {
            display: flex;
            justify-content: space-between;
            padding: 10px 0;
            font-size: 16px;
        }
        .summary-row.total {
            font-size: 20px;
            font-weight: bold;
            color: #28a745;
            border-top: 2px solid #28a745;
            padding-top: 15px;
            margin-top: 10px;
        }
        .summary-label {
            font-weight: 600;
            color: #495057;
            padding-top: 10px;
            padding-bottom: 10px;
        }
        .summary-value {
            color: #212529;
        }
        .customer-info-item {
            margin-bottom: 8px;
        }
        .balance-info-item {
            margin-bottom: 8px;
        }
        .readonly-field {
            background-color: #f8f9fa;
            border: 1px solid #dee2e6;
            padding: 8px 12px;
            border-radius: 4px;
            color: #495057;
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
            @if (session()->has('error'))
                <div class="alert text-white bg-danger" role="alert">
                    <div class="iq-alert-text">{{ session('error') }}</div>
                    <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                        <i class="ri-close-line"></i>
                    </button>
                </div>
            @endif

            <div class="invoice-form-container">
                <div class="invoice-header">
                    <h4>Order Details - Invoice #{{ $order->invoice_no }}</h4>
                </div>

                <!-- Customer/Shop and Date Section -->
                <div class="form-row-invoice">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Customer</label>
                                <div class="readonly-field">
                                    {{ $order->customer->shopname ?? $order->customer->name ?? 'N/A' }}
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Date & Time</label>
                                <div class="readonly-field">
                                    {{ \Carbon\Carbon::parse($order->order_date)->format('Y-m-d H:i:s') }}
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Customer Information -->
                @if($order->customer && !($order->customer->is_walkin ?? false))
                <div class="row mt-3 mb-3">
                    <!-- Left Side: Customer Information -->
                    <div class="col-md-6 col-12 mb-3 mb-md-0">
                        <div class="border rounded p-3" style="background-color: #f8f9fa;">
                            <h6 class="text-primary mb-3"><i class="ri-user-3-line mr-2"></i>Customer Details</h6>
                            <div class="customer-info-item">
                                <strong>Name:</strong> {{ $order->customer->name ?? '-' }}
                            </div>
                            <div class="customer-info-item">
                                <strong>Shop Name:</strong> {{ $order->customer->shopname ?? '-' }}
                            </div>
                            <div class="customer-info-item">
                                <strong>Phone:</strong> {{ $order->customer->phone ?? '-' }}
                            </div>
                            <div class="customer-info-item">
                                <strong>Address:</strong> {{ $order->customer->address ?? '-' }}
                            </div>
                        </div>
                    </div>

                    <!-- Right Side: Balance Information -->
                    <div class="col-md-6 col-12">
                        <div class="border rounded p-3" style="background-color: #f8f9fa;">
                            <h6 class="text-success mb-3"><i class="ri-wallet-3-line mr-2"></i>Balance Information</h6>
                            @php
                                $creditLimit = $order->customer->credit_limit ?? 0;
                                $creditAmount = $order->customer->credit_amount ?? 0;
                                $availableCredit = max(0, $creditLimit - $creditAmount);
                                $lastPayment = \App\Models\PaymentLog::whereHas('order', function($query) use ($order) {
                                    $query->where('customer_id', $order->customer_id);
                                })->orderBy('created_at', 'desc')->first();
                                $lastPaymentDate = $lastPayment ? $lastPayment->created_at : null;
                            @endphp
                            <div class="row mb-2">
                                <div class="col-6">
                                    <div class="balance-info-item">
                                        <strong>Credit Limit:</strong> <span class="text-primary">{{ number_format($creditLimit, 2) }}</span>
                                    </div>
                                </div>
                                <div class="col-6">
                                    <div class="balance-info-item">
                                        <strong>Credit Amount:</strong> <span class="text-danger">{{ number_format($creditAmount, 2) }}</span>
                                    </div>
                                </div>
                            </div>
                            <div class="row mb-2">
                                <div class="col-6">
                                    <div class="balance-info-item">
                                        <strong>Available Credit:</strong> <span class="text-success">{{ number_format($availableCredit, 2) }}</span>
                                    </div>
                                </div>
                                <div class="col-6">
                                    <div class="balance-info-item">
                                        <strong>Credit Days:</strong> {{ $order->customer->credit_days ?? 0 }}
                                    </div>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-12">
                                    <div class="balance-info-item">
                                        <strong>Last Payment Received At:</strong> {{ $lastPaymentDate ? \Carbon\Carbon::parse($lastPaymentDate)->format('Y-m-d H:i:s') : 'No payment history' }}
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                @endif

                <!-- Product Grid Section -->
                <div class="product-table-wrapper">
                    <div class="table-responsive">
                        <table class="product-table">
                            <thead>
                                <tr>
                                    <th class="product-name-col">Product</th>
                                    <th class="product-code-col">Code</th>
                                    <th class="unit-price-col">Unit Price</th>
                                    <th class="quantity-col">Quantity</th>
                                    <th class="discount-col">Discount</th>
                                    <th class="total-col">Total</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($orderDetails as $item)
                                <tr>
                                    <td>{{ $item->product ? $item->product->product_name : 'N/A' }}</td>
                                    <td>{{ $item->product ? ($item->product->product_code ?? '-') : '-' }}</td>
                                    <td>{{ number_format($item->unitcost, 2) }}</td>
                                    <td>{{ $item->quantity }}</td>
                                    <td>{{ number_format($item->item_discount ?? 0, 2) }}</td>
                                    <td>{{ number_format($item->total, 2) }}</td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Invoice Summary and Comment Section -->
                <div class="invoice-summary">
                    <div class="row">
                        <!-- Comment Section - Left Side -->
                        <div class="col-md-6">
                            <div class="comment-section">
                                <div class="form-group">
                                    <label>Note</label>
                                    <div class="readonly-field" style="min-height: 100px;">
                                        {{ $order->comment ?? 'No notes' }}
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Invoice Summary - Right Side -->
                        <div class="col-md-6">
                            <div class="summary-row">
                                <span class="summary-label">Subtotal:</span>
                                <span class="summary-value">{{ number_format($order->sub_total ?? 0, 2) }}</span>
                            </div>
                            <div class="summary-row">
                                <span class="summary-label">VAT:</span>
                                <span class="summary-value">{{ number_format($order->vat ?? 0, 2) }}</span>
                            </div>
                            <div class="summary-row">
                                <span class="summary-label">Discount on Invoice:</span>
                                <span class="summary-value">{{ number_format($order->invoice_discount ?? 0, 2) }}</span>
                            </div>
                            <div class="summary-row total">
                                <span class="summary-label">Invoice Total:</span>
                                <span class="summary-value">{{ number_format($order->total ?? 0, 2) }}</span>
                            </div>
                            <div class="summary-row" style="margin-top: 20px; padding-top: 15px; border-top: 1px solid #dee2e6;">
                                <span class="summary-label">Payment Method:</span>
                                <span class="summary-value text-capitalize">{{ $order->payment_status ?? 'N/A' }}</span>
                            </div>
                            @if(in_array(strtolower($order->payment_status ?? ''), ['bank', 'cheque']) && !empty($paymentBankName ?? null))
                            <div class="summary-row">
                                <span class="summary-label">Bank:</span>
                                <span class="summary-value">{{ $paymentBankName }}</span>
                            </div>
                            @endif
                            <div class="summary-row">
                                <span class="summary-label">Payment Amount:</span>
                                <span class="summary-value">{{ number_format($order->pay ?? 0, 2) }}</span>
                            </div>
                            <div class="summary-row">
                                <span class="summary-label">Due Amount:</span>
                                <span class="summary-value">{{ number_format($order->due ?? 0, 2) }}</span>
                            </div>
                            <div class="summary-row">
                                <span class="summary-label">Order Status:</span>
                                <span class="summary-value">
                                    @if($order->order_status == 'complete')
                                        <span class="badge badge-success">Complete</span>
                                    @else
                                        <span class="badge badge-warning">Pending</span>
                                    @endif
                                </span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Action Buttons -->
                <div class="mt-4">
                    @if($order->order_status != 'complete')
                        <button type="button" class="btn btn-success btn-lg mr-2" data-toggle="modal" data-target="#completeOrderModal" onclick="showCompleteOrderModal({{ $order->id }}, '{{ $order->invoice_no }}')">
                            <i class="ri-check-line mr-1"></i> Complete Order
                        </button>
                    @endif
                    <a href="{{ route('order.invoiceDownload', $order->id) }}" class="btn btn-primary btn-lg" target="_blank">
                        <i class="ri-printer-line mr-1"></i> Print
                    </a>
                    <a href="{{ route('order.index') }}" class="btn btn-secondary btn-lg">Back</a>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Complete Order Confirmation Modal -->
<div class="modal fade" id="completeOrderModal" tabindex="-1" role="dialog" aria-labelledby="completeOrderModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title" id="completeOrderModalLabel">
                    <i class="ri-check-line mr-2"></i>Complete Order
                </h5>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to complete this order?</p>
                <p><strong>Invoice #:</strong> <span id="modalInvoiceNo"></span></p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                <form action="{{ route('order.updateStatus') }}" method="POST" id="completeOrderForm" style="display: inline;">
                    @method('put')
                    @csrf
                    <input type="hidden" name="id" id="completeOrderId">
                    <button type="submit" class="btn btn-success">
                        <i class="ri-check-line mr-1"></i> Complete Order
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
function showCompleteOrderModal(orderId, invoiceNo) {
    $('#completeOrderId').val(orderId);
    $('#modalInvoiceNo').text(invoiceNo);
}
</script>
@endsection
