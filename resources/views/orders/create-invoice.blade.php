@extends('dashboard.body.main')

@section('specificpagestyles')
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <!-- Select2 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <link href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" rel="stylesheet" />
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
        .product-table input[type="text"],
        .product-table input[type="number"],
        .product-table select {
            width: 100%;
            padding: 8px;
            border: 1px solid #ced4da;
            border-radius: 4px;
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
        .product-table .action-col {
            width: 6%;
            text-align: center;
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
        .payment-row-inline {
            display: flex !important;
            flex-wrap: wrap;
            align-items: center;
            gap: 10px 15px;
        }
        .payment-row-inline .summary-label {
            margin-right: 0;
        }
        .bank-inline-wrap {
            display: inline-block;
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
        }
        .summary-value {
            color: #212529;
        }
        .btn-add-row {
            margin: 15px 0;
        }
        .stock-warning {
            color: #dc3545;
            font-size: 12px;
            font-weight: bold;
        }
        .stock-label {
            font-weight: 600;
            color: #495057;
        }
        .delete-row-btn {
            background: #dc3545;
            color: white;
            border: none;
            padding: 8px 12px;
            border-radius: 4px;
            cursor: pointer;
        }
        .delete-row-btn:hover {
            background: #c82333;
        }
        .comment-section {
            margin-top: 30px;
        }
        .comment-section textarea {
            width: 100%;
            min-height: 100px;
            padding: 12px;
            border: 1px solid #ced4da;
            border-radius: 4px;
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
                    <h4>Create New Invoice</h4>
                </div>
                
                <!-- Error Messages -->
                @if ($errors->any())
                    <div class="alert alert-danger mb-3">
                        <ul class="mb-0">
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif
                
                <!-- Credit Limit Warning -->
                <div id="credit_warning_row" style="display: none; margin-bottom: 20px;">
                    <div class="alert alert-warning mb-0" id="credit_warning" style="padding: 15px; margin: 0;">
                        <i class="ri-alert-line"></i> <strong>Warning:</strong> <span id="credit_warning_text"></span>
                    </div>
                </div>

                <form id="invoiceForm" method="POST" action="{{ route('invoice.store') }}">
                    @csrf

                    <!-- Customer/Shop and Date Section -->
                    <div class="form-row-invoice">
                        <div class="row">
                            <div class="col-md-6">
                                @if($childShops->isNotEmpty())
                                    <!-- Parent Shop: Show both Customer and Shop dropdowns -->
                                    <div class="form-group">
                                        <label>Select Type <span class="text-danger">*</span></label>
                                        <div class="btn-group btn-group-toggle w-100" data-toggle="buttons">
                                            <label class="btn btn-outline-primary active" id="btn-customer-type">
                                                <input type="radio" name="select_type" value="customer" checked> Customer
                                            </label>
                                            <label class="btn btn-outline-primary" id="btn-shop-type">
                                                <input type="radio" name="select_type" value="shop"> Shop Transfer
                                            </label>
                                        </div>
                                    </div>
                                    <div class="form-group" id="customer-group">
                                        <div class="d-flex align-items-center justify-content-between mb-2">
                                            <label for="customer_id" class="mb-0">Customer <span class="text-danger">*</span></label>
                                            <button type="button" class="btn btn-primary btn-sm" id="selectWalkInBtn">
                                                <i class="ri-user-line mr-1"></i> Walk-In
                                            </button>
                                        </div>
                                        <div class="d-flex align-items-center">
                                            <select class="form-control" id="customer_id" name="customer_id" style="max-width: 70%;">
                                                <option value="">Select Customer</option>
                                                @foreach($customers as $customer)
                                                    <option value="{{ $customer->id }}" 
                                                        data-credit-limit="{{ $customer->credit_limit ?? 0 }}" 
                                                        data-credit-amount="{{ $customer->credit_amount ?? 0 }}"
                                                        data-is-walkin="{{ $customer->is_walkin ?? 0 }}">
                                                        {{ $customer->name ?: $customer->shopname }}
                                                    </option>
                                                @endforeach
                                            </select>
                                            <button type="button" class="btn btn-success btn-sm ml-auto" id="addCustomerBtn" data-toggle="modal" data-target="#addCustomerModal">
                                                <i class="ri-add-line"></i> Add Customer
                                            </button>
                                        </div>
                                        @error('customer_id')
                                            <div class="text-danger">{{ $message }}</div>
                                        @enderror
                                    </div>
                                    <div class="form-group" id="shop-group" style="display: none;">
                                        <label for="shop_id">Child Shop <span class="text-danger">*</span></label>
                                        <select class="form-control" id="shop_id" name="shop_id">
                                            <option value="">Select Child Shop</option>
                                            @foreach($childShops as $shop)
                                                <option value="{{ $shop->id }}">{{ $shop->name }}</option>
                                            @endforeach
                                        </select>
                                        @error('shop_id')
                                            <div class="text-danger">{{ $message }}</div>
                                        @enderror
                                    </div>
                                @else
                                    <!-- Regular Shop: Show only Customer dropdown -->
                                    <div class="form-group">
                                        <div class="d-flex align-items-center justify-content-between mb-2">
                                            <label for="customer_id" class="mb-0">Customer <span class="text-danger">*</span></label>
                                            <button type="button" class="btn btn-primary btn-sm" id="selectWalkInBtn">
                                                <i class="ri-user-line mr-1"></i> Walk-In
                                            </button>
                                        </div>
                                        <div class="d-flex align-items-center">
                                            <select class="form-control" id="customer_id" name="customer_id" required style="max-width: 70%;">
                                                <option value="">Select Customer</option>
                                                @foreach($customers as $customer)
                                                    <option value="{{ $customer->id }}" 
                                                        data-credit-limit="{{ $customer->credit_limit ?? 0 }}" 
                                                        data-credit-amount="{{ $customer->credit_amount ?? 0 }}"
                                                        data-is-walkin="{{ $customer->is_walkin ?? 0 }}">
                                                        {{ $customer->name ?: $customer->shopname }}
                                                    </option>
                                                @endforeach
                                            </select>
                                            <button type="button" class="btn btn-success btn-sm ml-auto" id="addCustomerBtn" data-toggle="modal" data-target="#addCustomerModal">
                                                <i class="ri-add-line"></i> Add Customer
                                            </button>
                                        </div>
                                        @error('customer_id')
                                            <div class="text-danger">{{ $message }}</div>
                                        @enderror
                                    </div>
                                @endif
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="order_date">Date & Time <span class="text-danger">*</span></label>
                                    <input type="datetime-local" class="form-control" id="order_date" name="order_date" required>
                                </div>
                            </div>
                        </div>

                        <!-- Customer Information Card -->
                        <div class="card mt-3 mb-3" id="customerInfoCard" style="display: none;">
                            <div class="card-header bg-primary text-white">
                                <h5 class="mb-0"><i class="ri-user-line mr-2"></i>Customer Information</h5>
                            </div>
                            <div class="card-body">
                                <!-- Loading State -->
                                <div id="customerInfoLoading" class="text-center py-4" style="display: none;">
                                    <div class="spinner-border text-primary" role="status">
                                        <span class="sr-only">Loading...</span>
                                    </div>
                                    <p class="mt-2 text-muted">Loading customer information...</p>
                                </div>

                                <!-- Warning State -->
                                <div id="customerInfoWarning" class="alert alert-warning mb-0" style="display: none;">
                                    <i class="ri-alert-line mr-2"></i>Please select a customer to view information.
                                </div>

                                <!-- Customer Details -->
                                <div id="customerInfoContent" style="display: none;">
                                    <div class="row">
                                        <!-- Left Side: Customer Information -->
                                        <div class="col-md-6 col-12 mb-3 mb-md-0">
                                            <div class="border rounded p-3" style="background-color: #f8f9fa;">
                                                <h6 class="text-primary mb-3"><i class="ri-user-3-line mr-2"></i>Customer Details</h6>
                                                <div class="customer-info-item">
                                                    <strong>Name:</strong> <span id="customerName">-</span>
                                                </div>
                                                <div class="customer-info-item">
                                                    <strong>Shop Name:</strong> <span id="customerShopName">-</span>
                                                </div>
                                                <div class="customer-info-item">
                                                    <strong>Phone:</strong> <span id="customerPhone">-</span>
                                                </div>
                                                <div class="customer-info-item">
                                                    <strong>Address:</strong> <span id="customerAddress">-</span>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Right Side: Balance Information -->
                                        <div class="col-md-6 col-12">
                                            <div class="border rounded p-3" style="background-color: #f8f9fa;">
                                                <h6 class="text-success mb-3"><i class="ri-wallet-3-line mr-2"></i>Balance Information</h6>
                                                <div class="row mb-2">
                                                    <div class="col-6">
                                                        <div class="balance-info-item">
                                                            <strong>Credit Limit:</strong> <span id="customerCreditLimit" class="text-primary">-</span>
                                                        </div>
                                                    </div>
                                                    <div class="col-6">
                                                        <div class="balance-info-item">
                                                            <strong>Credit Amount:</strong> <span id="customerCreditAmount" class="text-danger">-</span>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="row mb-2">
                                                    <div class="col-6">
                                                        <div class="balance-info-item">
                                                            <strong>Available Credit:</strong> <span id="customerAvailableCredit" class="text-success">-</span>
                                                        </div>
                                                    </div>
                                                    <div class="col-6">
                                                        <div class="balance-info-item">
                                                            <strong>Credit Days:</strong> <span id="customerCreditDays">-</span>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="row">
                                                    <div class="col-12">
                                                        <div class="balance-info-item">
                                                            <strong>Last Payment Received At:</strong> <span id="customerLastPayment">-</span>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <!-- End Customer Information Card -->
                    </div>

                    <!-- Product Grid Section -->
                    <div class="product-table-wrapper">
                        <div class="d-flex align-items-center mb-3">
                            <button type="button" class="btn btn-success btn-add-row" id="addRowBefore">
                                <i class="ri-add-line"></i> Add Row
                            </button>
                            {{-- Add Product Button (modal will be included outside the form) --}}
                            <button type="button" class="btn btn-success btn-add-row ml-4" id="addProductBtn" data-toggle="modal" data-target="#addProductModal">
                                <i class="ri-add-line"></i> Add Product
                            </button>
                        </div>

                        <div class="table-responsive">
                            <table class="product-table" id="productTable">
                                <thead>
                                    <tr>
                                        <th class="product-name-col">Product</th>
                                        <th class="product-code-col">Code</th>
                                        <th class="unit-price-col">Unit Price</th>
                                        <th class="stock-col">Stock</th>
                                        <th class="quantity-col">Quantity</th>
                                        <th class="discount-col">Discount</th>
                                        <th class="total-col">Total</th>
                                        <th class="action-col">Action</th>
                                    </tr>
                                </thead>
                                <tbody id="productTableBody">
                                    <!-- First row - always present, no delete button -->
                                    <tr class="product-row" data-row-index="0">
                                        <td>
                                            <select class="form-control product-select" name="products[0][product_id]" data-row="0" style="width: 100%;">
                                                <option value="">Select Product</option>
                                            </select>
                                            <input type="hidden" class="original-price" name="products[0][original_price]" value="0">
                                        </td>
                                        <td>
                                            <span class="product-code-display" data-row="0">-</span>
                                        </td>
                                        <td>
                                            <input type="number" step="0.01" class="form-control unit-price" name="products[0][unit_price]" value="0" data-row="0" min="0">
                                        </td>
                                        <td>
                                            <span class="stock-label stock-display" data-row="0">0</span>
                                        </td>
                                        <td>
                                            <input type="number" class="form-control quantity" name="products[0][quantity]" value="1" data-row="0" min="1">
                                            <span class="stock-warning stock-warning-msg" data-row="0" style="display: none;"></span>
                                        </td>
                                        <td>
                                            <span class="discount-display" data-row="0">0.00</span>
                                            <input type="hidden" class="item-discount-value" name="products[0][item_discount]" value="0">
                                        </td>
                                        <td>
                                            <span class="total-display" data-row="0">0.00</span>
                                            <input type="hidden" class="total-value" name="products[0][total]" value="0">
                                        </td>
                                        <td>
                                            <!-- Empty for first row -->
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <button type="button" class="btn btn-success btn-add-row" id="addRowAfter">
                            <i class="ri-add-line"></i> Add Row
                        </button>
                    </div>

                    <!-- Invoice Summary and Comment Section -->
                    <div class="invoice-summary">
                        <div class="row">
                            <!-- Comment Section - Left Side -->
                            <div class="col-md-6">
                                <div class="comment-section">
                                    <div class="form-group">
                                        <label for="comment">Note (Optional)</label>
                                        <textarea class="form-control" id="comment" name="comment" rows="8" placeholder="Add any additional notes here..."></textarea>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Invoice Summary - Right Side -->
                            <div class="col-md-6">
                                <div class="summary-row">
                                    <span class="summary-label">Subtotal:</span>
                                    <span class="summary-value" id="subtotal">0.00</span>
                                </div>
                                <div class="summary-row">
                                    <span class="summary-label">VAT:</span>
                                    <input type="number" step="0.01" class="form-control d-inline-block" id="vat" name="vat" value="0" min="0" style="width: 150px; display: inline-block;">
                                </div>
                                <div class="summary-row">
                                    <span class="summary-label">Discount on Invoice:</span>
                                    <input type="number" step="0.01" class="form-control d-inline-block" id="invoice_discount" name="invoice_discount" value="0" min="0" style="width: 150px; display: inline-block;">
                                </div>
                                <div class="summary-row total">
                                    <span class="summary-label">Invoice Total:</span>
                                    <span class="summary-value" id="invoice_total">0.00</span>
                                    <input type="hidden" name="invoice_total" id="invoice_total_hidden" value="0">
                                </div>
                                <div class="summary-row payment-row-inline" style="margin-top: 20px; padding-top: 15px; border-top: 1px solid #dee2e6;">
                                    <span class="summary-label">Payment 1 <span class="text-danger">*</span></span>
                                    <select class="form-control d-inline-block payment-method-select" id="payment_method_1" name="payment_method_1" required style="width: 130px;">
                                        <option value="">Select Method</option>
                                        <option value="cash">Cash</option>
                                        <option value="bank">Bank</option>
                                        <option value="cheque">Cheque</option>
                                        <option value="credit">Credit</option>
                                    </select>
                                    <span class="bank-inline-wrap" id="bank_select_row_1" style="display: none;">
                                        <select class="form-control d-inline-block" id="shop_bank_id_1" name="shop_bank_id_1" style="width: 180px;">
                                            <option value="">Select Bank</option>
                                            @foreach($shopBanks ?? [] as $sb)
                                            <option value="{{ $sb->id }}">{{ $sb->name }}</option>
                                            @endforeach
                                        </select>
                                    </span>
                                    <span class="summary-label ml-2">Amount:</span>
                                    <input type="number" step="0.01" class="form-control d-inline-block" id="pay_1" name="pay_1" value="0" min="0" style="width: 120px;">
                                </div>
                                <div class="summary-row payment-row-2 payment-row-inline mt-2" id="payment_row_2_block" style="display: none;">
                                    <span class="summary-label">Payment 2</span>
                                    <select class="form-control d-inline-block payment-method-select" id="payment_method_2" name="payment_method_2" style="width: 130px;">
                                        <option value="">Select Method</option>
                                        <option value="cash">Cash</option>
                                        <option value="bank">Bank</option>
                                        <option value="cheque">Cheque</option>
                                        <option value="credit">Credit</option>
                                    </select>
                                    <span class="bank-inline-wrap" id="bank_select_row_2" style="display: none;">
                                        <select class="form-control d-inline-block" id="shop_bank_id_2" name="shop_bank_id_2" style="width: 180px;">
                                            <option value="">Select Bank</option>
                                            @foreach($shopBanks ?? [] as $sb)
                                            <option value="{{ $sb->id }}">{{ $sb->name }}</option>
                                            @endforeach
                                        </select>
                                    </span>
                                    <span class="summary-label ml-2">Amount:</span>
                                    <input type="number" step="0.01" class="form-control d-inline-block" id="pay_2" name="pay_2" value="0" min="0" style="width: 120px;">
                                </div>
                                <div class="summary-row" style="margin-top: 15px; padding-top: 10px;">
                                    <span class="summary-label">Payment Total (Pay):</span>
                                    <span class="summary-value" id="pay_display">0.00</span>
                                    <input type="hidden" name="pay" id="pay_hidden" value="0">
                                </div>
                                <div class="summary-row">
                                    <span class="summary-label">Due Amount:</span>
                                    <span class="summary-value" id="due_display">0.00</span>
                                    <input type="hidden" name="due" id="due_hidden" value="0">
                                </div>
                                <div id="payment_validation_error" class="alert alert-danger mt-3 mb-0" role="alert" style="display: none;">
                                    <i class="ri-error-warning-line mr-2"></i><span id="payment_validation_error_text"></span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Hidden input to track print request -->
                    <input type="hidden" name="print_after_create" id="print_after_create" value="0">
                    
                    <!-- Submit Button -->
                    <div class="mt-4">
                        <button type="button" class="btn btn-primary btn-lg" id="createInvoiceBtn">
                            <i class="ri-file-add-line"></i> Save
                        </button>
                        <a href="{{ route('order.index') }}" class="btn btn-secondary btn-lg">Cancel</a>
                        <button type="button" class="btn btn-success btn-lg" id="createAndPrintInvoiceBtn">
                            <i class="ri-printer-line"></i> Save & Print
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

{{-- Add Product Modal - MUST be outside the invoice form to prevent conflicts --}}
@include('partials.add-product-modal')

<!-- Add Customer Modal -->
<div class="modal fade" id="addCustomerModal" tabindex="-1" role="dialog" aria-labelledby="addCustomerModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-md" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="addCustomerModalLabel">Add New Customer</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <form id="addCustomerForm" onsubmit="return false;">
                <div class="modal-body">
                    <div id="customerFormErrors" class="alert alert-danger" style="display: none;"></div>
                    <div id="customerFormSuccess" class="alert alert-success" style="display: none;"></div>
                    
                    <div class="form-group">
                        <label for="modal_shopname">Customer Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="modal_shopname" name="shopname" required>
                        <div class="invalid-feedback" id="error_shopname"></div>
                    </div>
                    
                    <div class="form-group">
                        <label for="modal_phone">Customer Phone <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="modal_phone" name="phone" required>
                        <div class="invalid-feedback" id="error_phone"></div>
                    </div>
                    
                    <!-- Credit Fields: Labels Row -->
                    <div class="row">
                        <div class="col-md-4">
                            <label for="modal_credit_limit">Credit Limit <span class="text-danger">*</span></label>
                        </div>
                        <div class="col-md-4">
                            <label for="modal_credit_amount">Credit Amount</label>
                        </div>
                        <div class="col-md-4">
                            <label for="modal_credit_days">Credit Days <span class="text-danger">*</span></label>
                        </div>
                    </div>
                    
                    <!-- Credit Fields: Inputs Row -->
                    <div class="row">
                        <div class="col-md-4 form-group">
                            <input type="number" step="0.01" min="0" class="form-control" id="modal_credit_limit" name="credit_limit" value="0" required>
                            <div class="invalid-feedback" id="error_credit_limit"></div>
                        </div>
                        <div class="col-md-4 form-group">
                            <input type="number" step="0.01" min="0" class="form-control" id="modal_credit_amount" name="credit_amount" value="0">
                            <div class="invalid-feedback" id="error_credit_amount"></div>
                        </div>
                        <div class="col-md-4 form-group">
                            <input type="number" min="0" class="form-control" id="modal_credit_days" name="credit_days" value="0" required>
                            <div class="invalid-feedback" id="error_credit_days"></div>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="modal_address">Customer Address <span class="text-danger">*</span></label>
                        <textarea class="form-control" id="modal_address" name="address" rows="3" required></textarea>
                        <div class="invalid-feedback" id="error_address"></div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary" id="saveCustomerBtn">
                        <span class="spinner-border spinner-border-sm d-none" id="saveCustomerSpinner" role="status" aria-hidden="true"></span>
                        <span id="saveCustomerBtnText">Save</span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

@endsection

@section('specificpagescripts')
<!-- Select2 JS -->
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script>
(function($) {
    'use strict';
    
    $(document).ready(function() {
        console.log('Invoice form page loaded');
        let rowCount = 0;

        // Old input (flashed by Laravel when redirecting back with errors) - for repopulating form
        @php
            $invoiceOldInput = [
                'customer_id' => old('customer_id'),
                'shop_id' => old('shop_id'),
                'order_date' => old('order_date'),
                'select_type' => old('select_type', 'customer'),
                'products' => old('products', []),
                'payment_method_1' => old('payment_method_1'),
                'pay_1' => old('pay_1'),
                'shop_bank_id_1' => old('shop_bank_id_1'),
                'payment_method_2' => old('payment_method_2'),
                'pay_2' => old('pay_2'),
                'shop_bank_id_2' => old('shop_bank_id_2'),
                'vat' => old('vat', 0),
                'invoice_discount' => old('invoice_discount', 0),
                'comment' => old('comment'),
            ];
            $productIdToText = collect($products ?? [])->keyBy('id')->map(function ($p) {
                return (isset($p->product_code) && $p->product_code) ? $p->product_code . ' - ' . $p->product_name : $p->product_name;
            })->toArray();
            $productIdToCode = collect($products ?? [])->keyBy('id')->map(function ($p) {
                return $p->product_code ?? '';
            })->toArray();
        @endphp
        var invoiceOldInput = @json($invoiceOldInput);
        var productIdToText = @json($productIdToText);
        var productIdToCode = @json($productIdToCode);
        
        // Re-enable buttons if there are errors on the page
        // This handles the case when form submission fails and page reloads with errors
        // Check for Laravel validation errors, invalid form fields, or error alerts
        const hasErrors = $('.alert-danger').length > 0 || 
                         $('.is-invalid').length > 0 || 
                         $('.text-danger').length > 0 ||
                         $('.invalid-feedback:visible').length > 0 ||
                         $('#invoiceForm').find('.form-control.is-invalid').length > 0;
        
        if (hasErrors) {
            $('#createInvoiceBtn').prop('disabled', false).html('<i class="ri-file-add-line"></i> Save');
            $('#createAndPrintInvoiceBtn').prop('disabled', false).html('<i class="ri-printer-line"></i> Save & Print');
            $('#invoiceForm').data('submitting', false);
            console.log('Errors detected on page load, re-enabling buttons');
        }
        
        // Test if form exists
        if ($('#invoiceForm').length === 0) {
            console.error('Invoice form not found!');
        } else {
            console.log('Invoice form found');
        }
        
        // Test if submit button exists
        if ($('#createInvoiceBtn').length === 0) {
            console.error('Submit button not found!');
        } else {
            console.log('Submit button found');
            // Add click handler as backup
            $('#createInvoiceBtn').on('click', function(e) {








                const $invoiceForm = $('#invoiceForm');
               // Check if form validation passes before submitting
        if ($invoiceForm.length === 0) {
            console.error('Invoice form not found!');
            return false;
        }
     
        // Check if form is already submitting (prevent double submission)
        if ($invoiceForm.data('submitting')) {
            console.log('Form is already submitting, ignoring click');
            return false;
        }
       
        // Check HTML5 validation first and log which fields are invalid
        if (!$invoiceForm[0].checkValidity()) {
            console.log('HTML5 validation failed');
            
            // Find and log all invalid fields
            const invalidFields = [];
            $invoiceForm[0].querySelectorAll(':invalid').forEach(function(field) {
                const fieldInfo = {
                    name: field.name || field.id,
                    value: field.value,
                    validationMessage: field.validationMessage,
                    type: field.type,
                    tagName: field.tagName
                };
                invalidFields.push(fieldInfo);
                console.error('Invalid field:', fieldInfo);
                
                // Highlight invalid field
                $(field).addClass('is-invalid').focus();
            });
            // Show native validation so user sees which field is missing
            $invoiceForm[0].reportValidity();
            return false;
        }
        
        // Prevent default button behavior and manually submit form
        e.preventDefault();
        e.stopPropagation();
        
        // Manually trigger form submit
        console.log('Manually triggering form submit via jQuery...');
        $invoiceForm.data('submitting', true);
        
        // Trigger jQuery submit event (this will call our validation handler)
        $invoiceForm.submit();
        
        // Reset flag after a delay
        setTimeout(function() {
            $invoiceForm.data('submitting', false);
        }, 1000);
                // Don't prevent default, let form submit naturally
            });
        }

    // Initialize Select2 on existing product selects
    function initializeSelect2($select) {
        const rowIndex = $select.data('row');
        $select.select2({
            theme: 'bootstrap-5',
            placeholder: 'Select Product',
            allowClear: true,
            minimumInputLength: 2,
            ajax: {
                url: '{{ route("api.products.search") }}',
                dataType: 'json',
                delay: 250,
                data: function (params) {
                    return {
                        q: params.term, // search term
                        page: params.page
                    };
                },
                processResults: function (data, params) {
                    params.page = params.page || 1;
                    return {
                        results: data.results.map(function(item) {
                            return {
                                id: item.id,
                                text: item.text,
                                name: item.name,
                                price: item.price,
                                stock: item.stock,
                                code: item.code
                            };
                        }),
                        pagination: data.pagination || { more: false }
                    };
                },
                cache: true
            },
            templateResult: formatProduct,
            templateSelection: formatProductSelection
        }).on('select2:open', function() {
            // Auto-focus on search input when dropdown opens
            // Try multiple times with increasing delays to ensure Select2 is ready
            const focusAttempts = [50, 100, 150, 200];
            focusAttempts.forEach(function(delay) {
                setTimeout(function() {
                    // Find the search input in the opened dropdown
                    const $searchInput = $('.select2-container--open .select2-search__field');
                    if ($searchInput.length > 0 && document.activeElement !== $searchInput[0]) {
                        $searchInput[0].focus();
                        $searchInput.focus();
                    }
                }, delay);
            });
        });

        // Handle product selection change
        $select.on('select2:select', function (e) {
            let data = e.params.data;
            const rowIdx = $(this).data('row');
            const $row = $('tr[data-row-index="' + rowIdx + '"]');
            const $selectElement = $(this);
            
            // If data doesn't have required properties (manually added option), try to get from stored data
            if (!data.price && !data.stock && typeof window.newProductData !== 'undefined') {
                const productId = data.id;
                if (window.newProductData[productId]) {
                    data = window.newProductData[productId];
                }
            }
            
            // Ensure the select element has the value set (Select2 sometimes doesn't set it properly)
            $selectElement.val(data.id).trigger('change');
            
            $row.find('.original-price').val(data.price || 0);
            $row.find('.unit-price').val(data.price || 0);
            $row.find('.stock-display').text(data.stock || 0);
            $row.find('.product-code-display').text(data.code || '-');
            
            console.log('Product selected - Row:', rowIdx, 'Product ID:', data.id, 'Select value:', $selectElement.val());
            
            calculateRowTotal(rowIdx);
        });

        // Handle clear selection
        $select.on('select2:clear', function (e) {
            const rowIdx = $(this).data('row');
            const $row = $('tr[data-row-index="' + rowIdx + '"]');
            
            $row.find('.original-price').val(0);
            $row.find('.unit-price').val(0);
            $row.find('.stock-display').text(0);
            $row.find('.product-code-display').text('-');
            
            calculateRowTotal(rowIdx);
        });

    }

    // Format product display in dropdown
    function formatProduct(product) {
        if (product.loading) {
            return product.text;
        }
        return $('<span>' + product.text + '</span>');
    }

    // Format selected product display
    function formatProductSelection(product) {
        return product.text || product.id;
    }

    // Initialize Select2 on first product select
    initializeSelect2($('.product-select[data-row="0"]'));

    // Global event handler for all Select2 product dropdowns (backup method)
    $(document).on('select2:open', '.product-select', function() {
        // Try multiple times with increasing delays to ensure Select2 is ready
        const focusAttempts = [50, 100, 150, 200];
        focusAttempts.forEach(function(delay) {
            setTimeout(function() {
                // Find the search input in the currently open Select2 dropdown
                const $searchInput = $('.select2-container--open .select2-search__field');
                if ($searchInput.length > 0 && document.activeElement !== $searchInput[0]) {
                    // Use native focus for better compatibility
                    $searchInput[0].focus();
                    // Also trigger jQuery focus as backup
                    $searchInput.focus();
                }
            }, delay);
        });
    });

    // Initialize date/time with current date/time (or from old() when repopulating after error)
    var orderDateVal = invoiceOldInput && invoiceOldInput.order_date ? invoiceOldInput.order_date : null;
    if (!orderDateVal) {
        const now = new Date();
        const year = now.getFullYear();
        const month = String(now.getMonth() + 1).padStart(2, '0');
        const day = String(now.getDate()).padStart(2, '0');
        const hours = String(now.getHours()).padStart(2, '0');
        const minutes = String(now.getMinutes()).padStart(2, '0');
        orderDateVal = `${year}-${month}-${day}T${hours}:${minutes}`;
    }
    $('#order_date').val(orderDateVal);

    // Repopulate form from old() when returning with validation/exception errors
    var hasOldInput = invoiceOldInput && (
        (invoiceOldInput.products && invoiceOldInput.products.length > 0) ||
        invoiceOldInput.customer_id || invoiceOldInput.shop_id
    );
    if (hasOldInput) {
        var o = invoiceOldInput;
        @if($childShops->isNotEmpty())
        var selectType = o.select_type || 'customer';
        $('input[name="select_type"][value="' + selectType + '"]').prop('checked', true).parent().addClass('active');
        if (selectType === 'shop') {
            $('#customer-group').hide();
            $('#shop-group').show();
            $('#shop_id').val(o.shop_id || '').prop('required', true);
            $('#customer_id').prop('required', false).val('');
            $('#btn-shop-type').addClass('active');
            $('#btn-customer-type').removeClass('active');
        } else {
            $('#customer-group').show();
            $('#shop-group').hide();
            $('#customer_id').val(o.customer_id || '').prop('required', true);
            $('#shop_id').prop('required', false).val('');
            $('#btn-customer-type').addClass('active');
            $('#btn-shop-type').removeClass('active');
        }
        @else
        $('#customer_id').val(o.customer_id || '');
        @endif
        if (o.vat != null && o.vat !== '') $('#vat').val(o.vat);
        if (o.invoice_discount != null && o.invoice_discount !== '') $('#invoice_discount').val(o.invoice_discount);
        if (o.comment != null && o.comment !== '') $('#comment').val(o.comment);
        if (o.payment_method_1) {
            $('#payment_method_1').val(o.payment_method_1);
            $('#payment_method_1').trigger('change');
            if (o.pay_1 != null && o.pay_1 !== '') $('#pay_1').val(o.pay_1);
            if (o.shop_bank_id_1) $('#shop_bank_id_1').val(o.shop_bank_id_1);
        }
        if (o.payment_method_2) {
            $('#payment_method_2').val(o.payment_method_2);
            $('#payment_method_2').trigger('change');
            if (o.pay_2 != null && o.pay_2 !== '') $('#pay_2').val(o.pay_2);
            if (o.shop_bank_id_2) $('#shop_bank_id_2').val(o.shop_bank_id_2);
        }
        var products = o.products || [];
        if (products.length > 0) {
            var $tbody = $('#productTableBody');
            var $firstRow = $tbody.find('tr[data-row-index="0"]');
            $tbody.find('tr[data-row-index]').not($firstRow).each(function() {
                var $row = $(this);
                var $sel = $row.find('.product-select');
                if ($sel.data('select2')) $sel.select2('destroy');
                $row.remove();
            });
            rowCount = 0;
            for (var i = 0; i < products.length; i++) {
                var p = products[i];
                var pid = String(p.product_id || '');
                if (!pid) continue;
                if (i > 0) addRow();
                var $row = $tbody.find('tr[data-row-index="' + i + '"]');
                var $select = $row.find('.product-select');
                if ($select.data('select2')) $select.select2('destroy');
                var text = productIdToText && productIdToText[pid] ? productIdToText[pid] : 'Product #' + pid;
                var code = productIdToCode && productIdToCode[pid] ? productIdToCode[pid] : '-';
                $select.append(new Option(text, pid, true, true));
                initializeSelect2($select);
                $select.val(pid).trigger('change');
                $row.find('.original-price').val(p.original_price || p.unit_price || 0);
                $row.find('.unit-price').val(p.unit_price || 0);
                $row.find('.quantity').val(p.quantity || 1);
                $row.find('.item-discount-value').val(p.item_discount || 0);
                $row.find('.total-value').val(p.total || 0);
                $row.find('.product-code-display').text(code);
                $row.find('.discount-display').text(parseFloat(p.item_discount || 0).toFixed(2));
                $row.find('.total-display').text(parseFloat(p.total || 0).toFixed(2));
                var stock = p.stock != null ? p.stock : 0;
                $row.find('.stock-display').text(stock);
            }
            calculateInvoiceTotal();
            calculateDue();
        }
    }

    // Focus on customer dropdown on page load
    $('#customer_id').focus();

    // Handle customer/shop type toggle (if exists)
    @if($childShops->isNotEmpty())
    $('input[name="select_type"]').on('change', function() {
        const selectedType = $(this).val();
        if (selectedType === 'customer') {
            $('#customer-group').show();
            $('#shop-group').hide();
            $('#customer_id').prop('required', true);
            $('#shop_id').prop('required', false).val('');
            $('#btn-customer-type').addClass('active');
            $('#btn-shop-type').removeClass('active');
        } else if (selectedType === 'shop') {
            $('#customer-group').hide();
            $('#shop-group').show();
            $('#customer_id').prop('required', false).val('');
            $('#shop_id').prop('required', true);
            $('#btn-shop-type').addClass('active');
            $('#btn-customer-type').removeClass('active');
            // Hide credit warning for shop transfers
            $('#credit_warning_row').hide();
        }
    });
    @endif

    // Product selection change is now handled in initializeSelect2 function

    // Handle unit price change
    $(document).on('input', '.unit-price', function() {
        const rowIndex = $(this).data('row');
        calculateRowTotal(rowIndex);
    });

    // Handle quantity change
    $(document).on('input', '.quantity', function() {
        const rowIndex = $(this).data('row');
        let quantity = parseFloat($(this).val()) || 0;
        const stock = parseFloat($(this).closest('tr').find('.stock-display').text()) || 0;
        
        // Check stock validation
        const $warning = $(this).closest('tr').find('.stock-warning-msg');
        if (quantity > stock) {
            $warning.text('Quantity exceeds available stock!').show();
            $(this).val(stock);
            quantity = stock;
        } else {
            $warning.hide();
        }
        
        calculateRowTotal(rowIndex);
    });

    // Handle Enter key on quantity input - add new row if on last row
    $(document).on('keydown', '.quantity', function(e) {
        // Check if Enter key is pressed
        if (e.key === 'Enter' || e.keyCode === 13) {
            e.preventDefault(); // Prevent form submission
            
            const $currentRow = $(this).closest('tr');
            const $allRows = $('#productTableBody tr.product-row');
            
            // Check if current row is the last row in the table
            const isLastRow = $currentRow.is($allRows.last());
            
            if (isLastRow) {
                // Add new row after current row (same as addRowAfter button)
                addRow();
                
                // Focus on the new row's product dropdown
                setTimeout(function() {
                    const $newRow = $('#productTableBody tr.product-row').last();
                    const $newProductSelect = $newRow.find('.product-select');
                    if ($newProductSelect.length > 0) {
                        // Open the Select2 dropdown and focus on search input
                        $newProductSelect.select2('open');
                    }
                }, 150); // Wait a bit longer to ensure Select2 is initialized
            }
        }
    });

    // Calculate row total and discount
    function calculateRowTotal(rowIndex) {
        const $row = $('tr[data-row-index="' + rowIndex + '"]');
        const unitPrice = parseFloat($row.find('.unit-price').val()) || 0;
        const originalPrice = parseFloat($row.find('.original-price').val()) || 0;
        const quantity = parseFloat($row.find('.quantity').val()) || 0;
        
        const total = unitPrice * quantity;
        const discount = (originalPrice - unitPrice) * quantity;
        const discountDisplay = discount > 0 ? discount.toFixed(2) : '0.00';
        
        $row.find('.total-display').text(total.toFixed(2));
        $row.find('.total-value').val(total.toFixed(2));
        $row.find('.discount-display').text(discountDisplay);
        $row.find('.item-discount-value').val(discount > 0 ? discount.toFixed(2) : '0.00');
        
        calculateInvoiceTotal();
    }

    // Handle VAT change
    $(document).on('input', '#vat', function() {
        calculateInvoiceTotal();
    });

    // Handle invoice discount change
    $(document).on('input', '#invoice_discount', function() {
        calculateInvoiceTotal();
    });

    // Handle payment amount changes – clear validation error when user edits
    $(document).on('input', '#pay_1', function() {
        clearPaymentError();
        calculateDue();
    });
    $(document).on('input', '#pay_2', function() {
        clearPaymentError();
        calculateDue();
    });
    // Calculate invoice total
    function calculateInvoiceTotal() {
        let subtotal = 0;
        
        $('.total-value').each(function() {
            subtotal += parseFloat($(this).val()) || 0;
        });
        
        const vat = parseFloat($('#vat').val()) || 0;
        const invoiceDiscount = parseFloat($('#invoice_discount').val()) || 0;
        const invoiceTotal = Math.max(0, subtotal + vat - invoiceDiscount);
        
        $('#subtotal').text(subtotal.toFixed(2));
        $('#invoice_total').text(invoiceTotal.toFixed(2));
        $('#invoice_total_hidden').val(invoiceTotal.toFixed(2));
        
        // If payment method 1 is cash/bank/cheque, auto-fill first amount with invoice total
        const method1 = $('#payment_method_1').val();
        if (method1 === 'cash' || method1 === 'bank' || method1 === 'cheque') {
            $('#pay_1').val(invoiceTotal.toFixed(2));
        }

        calculateDue();
    }

    function showPaymentError(msg) {
        $('#payment_validation_error_text').text(msg);
        $('#payment_validation_error').show();
    }
    function clearPaymentError() {
        $('#payment_validation_error').hide();
        $('#payment_validation_error_text').text('');
    }

    // Calculate due amount (pay = pay_1 + pay_2, due = total - pay)
    function calculateDue() {
        const invoiceTotal = parseFloat($('#invoice_total_hidden').val()) || 0;
        const pay1 = parseFloat($('#pay_1').val()) || 0;
        const pay2 = parseFloat($('#pay_2').val()) || 0;
        const pay = pay1 + pay2;
        const due = Math.max(0, invoiceTotal - pay);
        
        $('#pay_display').text(pay.toFixed(2));
        $('#pay_hidden').val(pay.toFixed(2));
        $('#due_display').text(due.toFixed(2));
        $('#due_hidden').val(due.toFixed(2));
        
        checkCreditLimit(due);
    }

    // Payment method 1 change: credit hides row 2; cash/bank/cheque show row 2 and bank (if bank/cheque), auto-fill amount
    $(document).on('change', '#payment_method_1', function() {
        clearPaymentError();
        const method = $(this).val();
        const invoiceTotal = parseFloat($('#invoice_total_hidden').val()) || 0;
        if (method === 'credit') {
            $('#payment_row_2_block').hide();
            $('#pay_2').val('0');
            $('#payment_method_2').val('');
            $('#shop_bank_id_2').val('');
            $('#bank_select_row_2').hide();
            $('#bank_select_row_1').hide();
            $('#shop_bank_id_1').val('').prop('required', false);
        } else if (method === 'bank' || method === 'cheque') {
            $('#bank_select_row_1').show();
            $('#shop_bank_id_1').prop('required', true);
            $('#pay_1').val(invoiceTotal.toFixed(2));
            $('#payment_row_2_block').show();
        } else if (method === 'cash') {
            $('#bank_select_row_1').hide();
            $('#shop_bank_id_1').val('').prop('required', false);
            $('#pay_1').val(invoiceTotal.toFixed(2));
            $('#payment_row_2_block').show();
        }
        calculateDue();
    });

    // Payment method 2 change: show/hide bank dropdown for row 2
    $(document).on('change', '#payment_method_2', function() {
        clearPaymentError();
        const method = $(this).val();
        if (method === 'bank' || method === 'cheque') {
            $('#bank_select_row_2').show();
            $('#shop_bank_id_2').prop('required', true);
        } else {
            $('#bank_select_row_2').hide();
            $('#shop_bank_id_2').val('').prop('required', false);
        }
        calculateDue();
    });
    
    // Check credit limit and show warning
    function checkCreditLimit(dueAmount) {
        const customerId = $('#customer_id').val();
        if (!customerId) {
            $('#credit_warning_row').hide();
            return;
        }
        
        const selectedOption = $('#customer_id option:selected');
        const creditLimit = parseFloat(selectedOption.data('credit-limit')) || 0;
        const creditAmount = parseFloat(selectedOption.data('credit-amount')) || 0;
        
        // Hide warning if no credit limit set
        if (creditLimit <= 0) {
            $('#credit_warning_row').hide();
            return;
        }
        
        // Check if current credit already exceeds limit
        if (creditAmount > creditLimit) {
            const exceededBy = creditAmount - creditLimit;
            const warningText = `Credit limit already exceeded! Current: ${creditAmount.toFixed(2)} (Limit: ${creditLimit.toFixed(2)}). Exceeded by: ${exceededBy.toFixed(2)}`;
            $('#credit_warning_text').text(warningText);
            $('#credit_warning_row').show();
            return;
        }
        
        // If no due amount yet, just check current status
        if (dueAmount <= 0) {
            $('#credit_warning_row').hide();
            return;
        }
        
        // Calculate new credit amount after this order
        const newCreditAmount = creditAmount + dueAmount;
        
        // Show warning if credit limit would be exceeded
        if (newCreditAmount > creditLimit) {
            const exceededBy = newCreditAmount - creditLimit;
            const warningText = `Credit limit will be exceeded! Current: ${creditAmount.toFixed(2)}, After this order: ${newCreditAmount.toFixed(2)} (Limit: ${creditLimit.toFixed(2)}). Exceeded by: ${exceededBy.toFixed(2)}`;
            $('#credit_warning_text').text(warningText);
            $('#credit_warning_row').show();
        } else {
            $('#credit_warning_row').hide();
        }
    }
    
    // Handle customer change - show warning immediately when customer is selected
    // Handle customer selection change - fetch customer details
    $(document).on('change', '#customer_id', function() {
        const customerId = $(this).val();
        const $customerInfoCard = $('#customerInfoCard');
        const $customerInfoLoading = $('#customerInfoLoading');
        const $customerInfoWarning = $('#customerInfoWarning');
        const $customerInfoContent = $('#customerInfoContent');
        
        if (!customerId) {
            // No customer selected - hide panel
            $customerInfoCard.hide();
            return;
        }
        
        // Show loading state
        $customerInfoCard.show();
        $customerInfoLoading.show();
        $customerInfoWarning.hide();
        $customerInfoContent.hide();
        
        // Fetch customer details via AJAX
        $.ajax({
            url: '{{ route("api.customers.details", ":id") }}'.replace(':id', customerId),
            method: 'GET',
            success: function(response) {
                if (response.success && response.customer) {
                    const customer = response.customer;
                    
                    // Check if it's a walk-in customer using is_walkin flag
                    const isWalkIn = customer.is_walkin === 1 || customer.is_walkin === true;
                    
                    if (isWalkIn) {
                        // Hide customer info panel for walk-in customers
                        $customerInfoCard.hide();
                        return;
                    }
                    
                    // Update customer information (left side)
                    $('#customerName').text(customer.name || '-');
                    $('#customerShopName').text(customer.shopname || '-');
                    $('#customerPhone').text(customer.phone || '-');
                    $('#customerAddress').text(customer.address || '-');
                    
                    // Update balance information (right side)
                    $('#customerCreditLimit').text(formatCurrency(customer.credit_limit || 0));
                    $('#customerCreditAmount').text(formatCurrency(customer.credit_amount || 0));
                    $('#customerAvailableCredit').text(formatCurrency(customer.available_credit || 0));
                    $('#customerCreditDays').text(customer.credit_days || 0);
                    $('#customerLastPayment').text(customer.last_payment_date ? formatDateTime(customer.last_payment_date) : 'No payment history');
                    
                    // Show content, hide loading
                    $customerInfoLoading.hide();
                    $customerInfoWarning.hide();
                    $customerInfoContent.show();
                } else {
                    // Error in response
                    $customerInfoLoading.hide();
                    $customerInfoWarning.html('<i class="ri-alert-line mr-2"></i>Failed to load customer information.').show();
                    $customerInfoContent.hide();
                }
            },
            error: function(xhr) {
                // Handle error
                $customerInfoLoading.hide();
                let errorMessage = 'Failed to load customer information.';
                if (xhr.responseJSON && xhr.responseJSON.error) {
                    errorMessage = xhr.responseJSON.error;
                }
                $customerInfoWarning.html('<i class="ri-alert-line mr-2"></i>' + errorMessage).show();
                $customerInfoContent.hide();
            }
        });
    });
    
    // Format currency helper function
    function formatCurrency(amount) {
        return parseFloat(amount).toLocaleString('en-US', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        });
    }
    
    // Format date time helper function
    function formatDateTime(dateString) {
        const date = new Date(dateString);
        return date.toLocaleString('en-US', {
            year: 'numeric',
            month: 'short',
            day: 'numeric',
            hour: '2-digit',
            minute: '2-digit'
        });
    }
    
    // Handle Walk-In customer button click
    $(document).on('click', '#selectWalkInBtn', function() {
        const $customerSelect = $('#customer_id');
        let walkInCustomerId = null;
        
        // Find walk-in customer in dropdown options
        $customerSelect.find('option').each(function() {
            const $option = $(this);
            const isWalkIn = $option.data('is-walkin') == 1 || $option.data('is-walkin') === true;
            if (isWalkIn && $option.val()) {
                walkInCustomerId = $option.val();
                return false; // Break loop
            }
        });
        
        if (walkInCustomerId) {
            // Select walk-in customer and trigger change event
            $customerSelect.val(walkInCustomerId).trigger('change');
        } else {
            // Show message if walk-in customer not found
            alert('Walk-In customer not found. Please create a walk-in customer first.');
        }
    });

    $(document).on('change', '#customer_id', function() {
        // Check credit limit immediately (with 0 due amount to check current status)
        checkCreditLimit(0);
        // Also recalculate due if there's already an invoice total
        calculateDue();
    });
    

    // Add row function
    function addRow() {
        rowCount++;
        
        const newRow = `
            <tr class="product-row" data-row-index="${rowCount}">
                <td>
                    <select class="form-control product-select" name="products[${rowCount}][product_id]" data-row="${rowCount}" style="width: 100%;">
                        <option value="">Select Product</option>
                    </select>
                    <input type="hidden" class="original-price" name="products[${rowCount}][original_price]" value="0">
                </td>
                <td>
                    <span class="product-code-display" data-row="${rowCount}">-</span>
                </td>
                <td>
                    <input type="number" step="0.01" class="form-control unit-price" name="products[${rowCount}][unit_price]" value="0" data-row="${rowCount}" min="0">
                </td>
                <td>
                    <span class="stock-label stock-display" data-row="${rowCount}">0</span>
                </td>
                <td>
                    <input type="number" class="form-control quantity" name="products[${rowCount}][quantity]" value="1" data-row="${rowCount}" min="1">
                    <span class="stock-warning stock-warning-msg" data-row="${rowCount}" style="display: none;"></span>
                </td>
                <td>
                    <span class="discount-display" data-row="${rowCount}">0.00</span>
                    <input type="hidden" class="item-discount-value" name="products[${rowCount}][item_discount]" value="0">
                </td>
                <td>
                    <span class="total-display" data-row="${rowCount}">0.00</span>
                    <input type="hidden" class="total-value" name="products[${rowCount}][total]" value="0">
                </td>
                <td>
                    <button type="button" class="delete-row-btn" data-row="${rowCount}">
                        <i class="ri-delete-bin-line"></i>
                    </button>
                </td>
            </tr>
        `;
        
        $('#productTableBody').append(newRow);
        
        // Initialize Select2 on the newly added select element
        const $newSelect = $('tr[data-row-index="' + rowCount + '"] .product-select');
        initializeSelect2($newSelect);
    }

    // Add row before
    $('#addRowBefore').on('click', function() {
        addRow();
    });

    // Add row after
    $('#addRowAfter').on('click', function() {
        addRow();
    });

    // Delete row
    $(document).on('click', '.delete-row-btn', function() {
        const rowIndex = $(this).data('row');
        const $row = $('tr[data-row-index="' + rowIndex + '"]');
        const $select = $row.find('.product-select');
        
        // Destroy Select2 instance before removing
        if ($select.data('select2')) {
            $select.select2('destroy');
        }
        
        $row.remove();
        calculateInvoiceTotal();
    });

    // Form submission - bind after DOM is ready
    console.log('Binding form submission handler...');
    const $invoiceForm = $('#invoiceForm');
    if ($invoiceForm.length > 0) {
        console.log('Form found, binding submit handler');
        $invoiceForm.on('submit', function(e) {
            // CRITICAL: Make sure this is the invoice form, not a modal form
            const $form = $(this);
            if ($form.attr('id') !== 'invoiceForm') {
                console.log('Form submission handler triggered for non-invoice form, ignoring');
                return true; // Allow other forms to submit normally
            }
            
            console.log('Form submission handler triggered for invoice form - event:', e);
        
        // Validation
        @if($childShops->isNotEmpty())
        // Parent shop: check if customer or shop is selected
        const selectedType = $('input[name="select_type"]:checked').val();
        console.log('Selected type:', selectedType);
        if (selectedType === 'customer') {
            const customerId = $('#customer_id').val();
            console.log('Customer ID:', customerId);
            if (!customerId) {
                e.preventDefault();
                alert('Please select a customer');
                return false;
            }
        } else if (selectedType === 'shop') {
            const shopId = $('#shop_id').val();
            console.log('Shop ID:', shopId);
            if (!shopId) {
                e.preventDefault();
                alert('Please select a child shop');
                return false;
            }
        }
        @else
        // Regular shop: must select customer
        const customerId = $('#customer_id').val();
        console.log('Customer ID:', customerId);
        if (!customerId) {
            e.preventDefault();
            alert('Please select a customer');
            return false;
        }
        @endif

        const orderDate = $('#order_date').val();
        console.log('Order date:', orderDate);
        if (!orderDate) {
            e.preventDefault();
            alert('Please select a date and time');
            return false;
        }

        let hasProducts = false;
        $('.product-select').each(function() {
            const productId = $(this).val();
            if (productId) {
                console.log('Product found:', productId);
                hasProducts = true;
                return false;
            }
        });

        if (!hasProducts) {
            e.preventDefault();
            alert('Please add at least one product');
            return false;
        }

        const paymentMethod1 = $('#payment_method_1').val();
        if (!paymentMethod1) {
            e.preventDefault();
            showPaymentError('Please select a payment method for Payment 1.');
            $('#payment_method_1').focus();
            return false;
        }

        const invoiceTotal = parseFloat($('#invoice_total_hidden').val()) || 0;
        const pay1 = parseFloat($('#pay_1').val()) || 0;
        const pay2 = parseFloat($('#pay_2').val()) || 0;
        const payTotal = pay1 + pay2;
        const paymentMethod2 = $('#payment_method_2').val();
        const method1NonCredit = ['cash','bank','cheque'].indexOf(paymentMethod1) !== -1;
        const method2NonCredit = paymentMethod2 && ['cash','bank','cheque'].indexOf(paymentMethod2) !== -1;
        const hasCredit = paymentMethod1 === 'credit' || paymentMethod2 === 'credit';

        // Payment 1: bank/cheque requires bank selected
        if (paymentMethod1 === 'bank' || paymentMethod1 === 'cheque') {
            if (!$('#shop_bank_id_1').val()) {
                e.preventDefault();
                showPaymentError('Please select a bank for Payment 1.');
                $('#shop_bank_id_1').focus();
                return false;
            }
        }

        // If second payment amount > 0, require method 2 and bank when bank/cheque
        if (pay2 > 0) {
            if (!paymentMethod2) {
                e.preventDefault();
                showPaymentError('Please select a payment method for Payment 2.');
                $('#payment_method_2').focus();
                return false;
            }
            if (paymentMethod2 === 'bank' || paymentMethod2 === 'cheque') {
                if (!$('#shop_bank_id_2').val()) {
                    e.preventDefault();
                    showPaymentError('Please select a bank for Payment 2.');
                    $('#shop_bank_id_2').focus();
                    return false;
                }
            }
        }

        // Pay total cannot exceed invoice total
        if (payTotal > invoiceTotal + 0.01) {
            e.preventDefault();
            showPaymentError('Pay amount total cannot exceed the invoice total.');
            $('#pay_1').focus();
            return false;
        }

        // When both payment methods are NOT Credit: pay total must equal invoice total
        if (method1NonCredit && (pay2 <= 0 || method2NonCredit)) {
            if (Math.abs(payTotal - invoiceTotal) > 0.01) {
                e.preventDefault();
                if (payTotal < invoiceTotal) {
                    showPaymentError('Pay amount total is less than the invoice total. When pay total is less than the invoice total, one payment method must be Credit.');
                } else {
                    showPaymentError('Pay amount total must equal the invoice total when both payment methods are Cash, Bank or Cheque.');
                }
                $('#pay_1').focus();
                return false;
            }
        }

        // When pay total < invoice total, at least one method must be Credit
        if (payTotal < invoiceTotal - 0.01 && !hasCredit) {
            e.preventDefault();
            showPaymentError('Pay amount total is less than the invoice total. One payment method must be Credit for the remaining due amount.');
            $('#pay_1').focus();
            return false;
        }

        clearPaymentError();
        console.log('Form validation passed, submitting...');
        
        // Log all form data before submission
        const formData = new FormData(this);
        console.log('Form data being submitted:');
        for (let [key, value] of formData.entries()) {
            console.log(key + ': ' + value);
        }
        
        // Show loading state
        const $btn = $('#createInvoiceBtn');
        $btn.prop('disabled', true).html('<i class="ri-loader-4-line ri-spin"></i> Creating...');
        
        // Allow form submission - don't prevent default
        return true;
        });
        
        // Handle "Save & Print" button click
        $('#createAndPrintInvoiceBtn').on('click', function(e) {
            const $invoiceForm = $('#invoiceForm');
            
            // Check if form validation passes before submitting
            if ($invoiceForm.length === 0) {
                console.error('Invoice form not found!');
                return false;
            }
            
            // Check if form is already submitting (prevent double submission)
            if ($invoiceForm.data('submitting')) {
                console.log('Form is already submitting, ignoring click');
                return false;
            }
            
            // Check HTML5 validation first
            if (!$invoiceForm[0].checkValidity()) {
                // Find and log all invalid fields
                const invalidFields = [];
                $invoiceForm[0].querySelectorAll(':invalid').forEach(function(field) {
                    const fieldInfo = {
                        name: field.name || field.id,
                        value: field.value,
                        validationMessage: field.validationMessage,
                        type: field.type,
                        tagName: field.tagName
                    };
                    invalidFields.push(fieldInfo);
                    console.error('Invalid field:', fieldInfo);
                    $(field).addClass('is-invalid').focus();
                });
                
                // Show native validation
                $invoiceForm[0].reportValidity();
                return false;
            }
            
            // Set print flag
            $('#print_after_create').val('1');
            
            // Prevent default button behavior
            e.preventDefault();
            e.stopPropagation();
            
            // Show loading state
            const $printBtn = $('#createAndPrintInvoiceBtn');
            $printBtn.prop('disabled', true).html('<i class="ri-loader-4-line ri-spin"></i> Creating...');
            
            // Submit form
            console.log('Submitting form with print flag...');
            $invoiceForm.data('submitting', true);
            $invoiceForm.submit();
            
            // Reset flag after a delay
            setTimeout(function() {
                $invoiceForm.data('submitting', false);
            }, 1000);
        });
    } else {
        console.error('Invoice form not found when trying to bind submit handler!');
    }
    
 
    // Handle Add Customer Modal Form Submission
    $('#addCustomerForm').on('submit', function(e) {
        e.preventDefault();
        e.stopPropagation();
        e.stopImmediatePropagation();
        
        // Ensure this is not the invoice form
        if ($(e.target).attr('id') !== 'addCustomerForm') {
            return false;
        }
        
        // Hide previous errors and success messages
        $('#customerFormErrors').hide().html('');
        $('#customerFormSuccess').hide();
        $('.invalid-feedback').hide();
        $('.form-control').removeClass('is-invalid');
        
        // Disable save button and show loading
        const $saveBtn = $('#saveCustomerBtn');
        const $saveBtnText = $('#saveCustomerBtnText');
        const $spinner = $('#saveCustomerSpinner');
        
        $saveBtn.prop('disabled', true);
        $saveBtnText.text('Saving...');
        $spinner.removeClass('d-none');
        
        // Get form data
        const formData = {
            shopname: $('#modal_shopname').val(),
            phone: $('#modal_phone').val(),
            credit_limit: $('#modal_credit_limit').val() || 0,
            credit_days: $('#modal_credit_days').val() || 0,
            credit_amount: $('#modal_credit_amount').val() || 0,
            address: $('#modal_address').val(),
        };
        
        // Submit via AJAX
        $.ajax({
            url: '{{ route("customers.store") }}',
            method: 'POST',
            data: formData,
            headers: {
                'X-CSRF-TOKEN': $('input[name="_token"]').val() || $('meta[name="csrf-token"]').attr('content')
            },
            success: function(response) {
                if (response.success) {
                    // Show success message
                    $('#customerFormSuccess').text(response.message).show();
                    
                    // Add new customer to dropdown
                    const $customerSelect = $('#customer_id');
                    const newOption = $('<option>', {
                        value: response.customer.id,
                        text: response.customer.shopname || response.customer.name,
                        'data-credit-limit': response.customer.credit_limit,
                        'data-credit-amount': response.customer.credit_amount,
                        selected: true
                    });
                    $customerSelect.append(newOption);
                    $customerSelect.val(response.customer.id).trigger('change');
                    
                    // Reset form
                    $('#addCustomerForm')[0].reset();
                    
                    // Auto-close modal after 5 seconds
                    let countdown = 5;
                    const countdownInterval = setInterval(function() {
                        countdown--;
                        if (countdown > 0) {
                            $('#customerFormSuccess').text(response.message + ' Closing in ' + countdown + ' seconds...');
                        } else {
                            clearInterval(countdownInterval);
                            $('#addCustomerModal').modal('hide');
                            // Reset form and messages after modal closes
                            setTimeout(function() {
                                $('#addCustomerForm')[0].reset();
                                $('#customerFormSuccess').hide();
                                $saveBtn.prop('disabled', false);
                                $saveBtnText.text('Save');
                                $spinner.addClass('d-none');
                            }, 300);
                        }
                    }, 1000);
                }
                
                return false;
            },
            error: function(xhr) {
                // Re-enable save button
                $saveBtn.prop('disabled', false);
                $saveBtnText.text('Save');
                $spinner.addClass('d-none');
                
                if (xhr.status === 422) {
                    // Validation errors
                    const errors = xhr.responseJSON.errors;
                    let errorHtml = '<ul class="mb-0">';
                    
                    $.each(errors, function(field, messages) {
                        const fieldId = 'modal_' + field;
                        const errorId = 'error_' + field;
                        
                        // Show field error
                        $('#' + fieldId).addClass('is-invalid');
                        $('#' + errorId).text(messages[0]).show();
                        
                        // Add to error list
                        $.each(messages, function(index, message) {
                            errorHtml += '<li>' + message + '</li>';
                        });
                    });
                    
                    errorHtml += '</ul>';
                    $('#customerFormErrors').html(errorHtml).show();
                } else {
                    // Other errors
                    $('#customerFormErrors').html('<p>An error occurred. Please try again.</p>').show();
                }
                
                return false;
            }
        });
        
        return false;
    });
    
    // Reset modal when closed
    $('#addCustomerModal').on('hidden.bs.modal', function() {
        $('#addCustomerForm')[0].reset();
        $('#customerFormErrors').hide().html('');
        $('#customerFormSuccess').hide();
        $('.invalid-feedback').hide();
        $('.form-control').removeClass('is-invalid');
        $('#saveCustomerBtn').prop('disabled', false);
        $('#saveCustomerBtnText').text('Save');
        $('#saveCustomerSpinner').addClass('d-none');
    });

    });
})(jQuery);
</script>
@endsection
