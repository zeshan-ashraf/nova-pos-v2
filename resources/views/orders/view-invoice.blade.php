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
            table-layout: fixed;
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
            padding-top: 10px;
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
                @include('orders.partials.invoice-detail-content', [
                    'order' => $order,
                    'orderDetails' => $orderDetails,
                    'paymentBankName' => $paymentBankName ?? null,
                    'salePayments' => $salePayments ?? collect(),
                    'in_modal' => false,
                    'linkedInterShopPurchase' => $linkedInterShopPurchase ?? null,
                    'linkedShopPurchaseRequest' => $linkedShopPurchaseRequest ?? null,
                ])
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
