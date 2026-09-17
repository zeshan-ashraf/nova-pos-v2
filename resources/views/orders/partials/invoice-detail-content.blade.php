{{-- Reusable invoice detail content: used by orders/details page and ledger modal. Single source of truth. --}}
@php
    $in_modal = $in_modal ?? false;
    $salePayments = $salePayments ?? collect();
    $paymentBankName = $paymentBankName ?? null;
    $linkedInterShopPurchase = $linkedInterShopPurchase ?? null;
    $linkedShopPurchaseRequest = $linkedShopPurchaseRequest ?? null;
    $interShopMotherContext = $linkedInterShopPurchase || $linkedShopPurchaseRequest;
@endphp
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
    <div class="col-md-6 col-12 mb-3 mb-md-0">
        <div class="border rounded p-3" style="background-color: #f8f9fa;">
            <h6 class="text-primary mb-3"><i class="ri-user-3-line mr-2"></i>Customer Details</h6>
            <div class="customer-info-item"><strong>Name:</strong> {{ $order->customer->name ?? '-' }}</div>
            <div class="customer-info-item"><strong>Shop Name:</strong> {{ $order->customer->shopname ?? '-' }}</div>
            <div class="customer-info-item"><strong>Phone:</strong> {{ $order->customer->phone ?? '-' }}</div>
            <div class="customer-info-item"><strong>Address:</strong> {{ $order->customer->address ?? '-' }}</div>
        </div>
    </div>
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
                <div class="col-6"><div class="balance-info-item"><strong>Credit Limit:</strong> <span class="text-primary">{{ number_format($creditLimit, 2) }}</span></div></div>
                <div class="col-6"><div class="balance-info-item"><strong>Previous Balance:</strong> <span class="text-danger">{{ number_format($creditAmount, 2) }}</span></div></div>
            </div>
            <div class="row mb-2">
                <div class="col-6"><div class="balance-info-item"><strong>Available Credit:</strong> <span class="text-success">{{ number_format($availableCredit, 2) }}</span></div></div>
                <div class="col-6"><div class="balance-info-item"><strong>Credit Days:</strong> {{ $order->customer->credit_days ?? 0 }}</div></div>
            </div>
            <div class="row">
                <div class="col-12"><div class="balance-info-item"><strong>Last Payment Received At:</strong> {{ $lastPaymentDate ? \Carbon\Carbon::parse($lastPaymentDate)->format('Y-m-d H:i:s') : 'No payment history' }}</div></div>
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
                    <th class="quantity-col">Quantity</th>
                    <th class="unit-price-col">Unit Price</th>
                    <th class="total-col">Total</th>
                </tr>
            </thead>
            <tbody>
                @foreach($orderDetails as $item)
                <tr>
                    <td>{{ $item->product ? $item->product->product_name : 'N/A' }}</td>
                    <td>{{ $item->product ? ($item->product->product_code ?? '-') : '-' }}</td>
                    <td>{{ $item->quantityWithUnit() }}</td>
                    <td>{{ $item->unitPriceWithUnit() }}</td>
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
        <div class="col-md-6">
            <div class="comment-section">
                <div class="form-group">
                    <label>Note</label>
                    <div class="readonly-field" style="min-height: 100px;">{{ $order->comment ?? 'No notes' }}</div>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="summary-row"><span class="summary-label">Subtotal:</span><span class="summary-value">{{ number_format($order->sub_total ?? 0, 2) }}</span></div>
            <div class="summary-row"><span class="summary-label">Labor Charges:</span><span class="summary-value">{{ number_format($order->vat ?? 0, 2) }}</span></div>
            <div class="summary-row"><span class="summary-label">Discount on Invoice:</span><span class="summary-value">{{ number_format($order->invoice_discount ?? 0, 2) }}</span></div>
            <div class="summary-row total"><span class="summary-label">Invoice Total:</span><span class="summary-value">{{ number_format($order->total ?? 0, 2) }}</span></div>
            <div class="summary-row" style="margin-top: 20px; padding-top: 15px; border-top: 1px solid #dee2e6;"><span class="summary-label">Payment Method:</span><span class="summary-value text-capitalize">{{ $order->payment_status ?? 'N/A' }}</span></div>
            @if($salePayments->isNotEmpty())
            <div class="summary-row">
                <span class="summary-label">Payments:</span>
                <span class="summary-value">
                    <ul class="list-unstyled mb-0">@foreach($salePayments as $payment)<li>{{ $payment->bank_name }}: {{ number_format($payment->amount, 2) }}</li>@endforeach</ul>
                </span>
            </div>
            @elseif(in_array(strtolower($order->payment_status ?? ''), ['bank', 'cheque']) && !empty($paymentBankName))
            <div class="summary-row"><span class="summary-label">Bank:</span><span class="summary-value">{{ $paymentBankName }}</span></div>
            @endif
            <div class="summary-row"><span class="summary-label">Payment Amount:</span><span class="summary-value">{{ number_format($order->pay ?? 0, 2) }}</span></div>
            <div class="summary-row"><span class="summary-label">Due Amount:</span><span class="summary-value">{{ number_format($order->due ?? 0, 2) }}</span></div>
            <div class="summary-row">
                <span class="summary-label">Order Status:</span>
                <span class="summary-value">
                    @if($order->order_status == 'complete' || $order->order_status == \App\Support\InterShopTransferStatus::COMPLETED)
                        <span class="badge badge-success">Complete</span>
                    @elseif($order->order_status == \App\Support\InterShopTransferStatus::PENDING)
                        <span class="badge badge-warning">Pending approval</span>
                    @elseif($order->order_status == \App\Support\InterShopTransferStatus::APPROVED)
                        <span class="badge badge-info">Approved — ready to dispatch</span>
                    @elseif($order->order_status == \App\Support\InterShopTransferStatus::CANCELLED)
                        <span class="badge badge-secondary">Cancelled</span>
                    @elseif($order->order_status == 'pending')
                        <span class="badge badge-warning">Pending</span>
                    @elseif($order->order_status == \App\Services\HoldInvoiceService::STATUS_HOLD)
                        <span class="badge badge-info">On hold</span>
                    @elseif($order->order_status == \App\Services\HoldInvoiceService::STATUS_CANCELLED)
                        <span class="badge badge-secondary">Cancelled (hold)</span>
                    @else
                        <span class="badge badge-secondary">{{ $order->order_status }}</span>
                    @endif
                </span>
            </div>
        </div>
    </div>
</div>

<!-- Action Buttons -->
<div class="mt-4">
    @if(!$in_modal)
        @if($interShopMotherContext && auth()->user()->shop_id && (int) auth()->user()->shop_id === (int) $order->shop_id)
            @if($order->order_status == \App\Support\InterShopTransferStatus::APPROVED)
                <form action="{{ route('order.interShop.complete', $order) }}" method="POST" class="d-inline mr-2">
                    @csrf
                    <button type="submit" class="btn btn-success btn-lg"><i class="ri-truck-line mr-1"></i> Complete transfer (apply stock &amp; ledger)</button>
                </form>
                <form action="{{ route('order.interShop.resetApproval', $order) }}" method="POST" class="d-inline mr-2" onsubmit="return confirm('Reset child approval? The order will return to pending.');">
                    @csrf
                    <button type="submit" class="btn btn-outline-warning btn-lg">Reset approval</button>
                </form>
            @endif
            @if(in_array($order->order_status, [\App\Support\InterShopTransferStatus::PENDING, \App\Support\InterShopTransferStatus::APPROVED], true))
                <form action="{{ route('order.interShop.cancel', $order) }}" method="POST" class="d-inline mr-2" onsubmit="return confirm('Cancel this transfer and release reserved stock?');">
                    @csrf
                    <button type="submit" class="btn btn-outline-danger btn-lg">Cancel transfer</button>
                </form>
            @endif
        @endif
        @if(!$interShopMotherContext && $order->order_status == \App\Services\HoldInvoiceService::STATUS_HOLD)
            <a href="{{ route('order.reload', $order->id) }}" class="btn btn-warning btn-lg mr-2"><i class="ri-play-line mr-1"></i> Reload &amp; complete</a>
            <form action="{{ route('order.cancelHold', $order->id) }}" method="POST" class="d-inline mr-2" onsubmit="return confirm('Cancel this held invoice and release reserved stock?');">
                @csrf
                <button type="submit" class="btn btn-outline-danger btn-lg">Cancel hold</button>
            </form>
        @elseif(!$interShopMotherContext && $order->order_status != 'complete')
            <button type="button" class="btn btn-success btn-lg mr-2" data-toggle="modal" data-target="#completeOrderModal" onclick="showCompleteOrderModal({{ $order->id }}, '{{ addslashes($order->invoice_no) }}')"><i class="ri-check-line mr-1"></i> Complete Order</button>
        @endif
        <a href="{{ route('order.invoiceDownload', $order->id) }}" class="btn btn-primary btn-lg" target="_blank"><i class="ri-printer-line mr-1"></i> Print</a>
        @if(($order->order_status == 'complete' || $order->order_status == \App\Support\InterShopTransferStatus::COMPLETED) && !$interShopMotherContext)
        <a href="{{ route('order.edit', $order->id) }}" class="btn btn-warning btn-lg mr-2"><i class="ri-edit-line mr-1"></i> Edit Invoice</a>
        @endif
        @if($interShopMotherContext && $order->order_status == \App\Support\InterShopTransferStatus::PENDING)
            <a href="{{ route('order.edit', $order->id) }}" class="btn btn-warning btn-lg mr-2"><i class="ri-edit-line mr-1"></i> Edit draft</a>
        @endif
        <a href="{{ route('order.index') }}" class="btn btn-secondary btn-lg">Back</a>
    @else
        <a href="{{ route('order.invoiceDownload', $order->id) }}" class="btn btn-primary btn-lg mr-2" target="_blank"><i class="ri-printer-line mr-1"></i> Print</a>
        <a href="{{ route('order.orderDetails', $order->id) }}" class="btn btn-secondary btn-lg mr-2">View full page</a>
        <button type="button" class="btn btn-secondary btn-lg" data-dismiss="modal">Close</button>
    @endif
</div>
