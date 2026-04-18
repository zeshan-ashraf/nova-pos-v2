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
            width: 30%;
        }
        .product-table .unit-price-col {
            width: 12%;
        }
        .product-table .stock-col {
            width: 10%;
        }
        .product-table .quantity-col {
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
        .btn-add-row {
            margin: 15px 0;
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
            @if ($errors->has('error'))
                <div class="alert text-white bg-danger" role="alert">
                    <div class="iq-alert-text">{{ $errors->first('error') }}</div>
                    <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                    <i class="ri-close-line"></i>
                    </button>
                </div>
            @endif

            <div class="invoice-form-container">
                <div class="invoice-header">
                    <h4>Edit Purchase {{ $purchase->purchase_no }}</h4>
                </div>
                
                <!-- Credit Limit Warning -->
                <div id="credit_warning_row" style="display: none; margin-bottom: 20px;">
                    <div class="alert alert-warning mb-0" id="credit_warning" style="padding: 15px; margin: 0;">
                        <i class="ri-alert-line"></i> <strong>Warning:</strong> <span id="credit_warning_text"></span>
                    </div>
                </div>

                <form id="purchaseForm" method="POST" action="{{ route('purchases.update', $purchase->id) }}">
                    @csrf
                    @method('PUT')

                    <!-- Supplier and Date Section -->
                    <div class="form-row-invoice">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="supplier_id">Supplier <span class="text-danger">*</span></label>
                                    <div class="d-flex align-items-center">
                                        <select class="form-control" id="supplier_id" name="supplier_id" required style="max-width: 70%;">
                                            <option value="">Select Supplier</option>
                                            @foreach($suppliers as $supplier)
                                                <option value="{{ $supplier->id }}"
                                                    {{ (string) old('supplier_id', $purchase->supplier_id) === (string) $supplier->id ? 'selected' : '' }}
                                                    data-credit-limit="{{ $supplier->credit_limit ?? 0 }}"
                                                    data-credit-amount="{{ $supplier->credit_amount ?? 0 }}">
                                                    {{ $supplier->shopname ?: $supplier->name }}
                                                </option>
                                            @endforeach
                                        </select>
                                        <button type="button" class="btn btn-success btn-sm ml-2" id="addSupplierBtn" data-toggle="modal" data-target="#addSupplierModal">
                                            <i class="ri-add-line"></i> Add Supplier
                                        </button>
                                    </div>
                                    @error('supplier_id')
                                        <div class="text-danger">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="purchase_date">Date <span class="text-danger">*</span></label>
                                    <input type="date" class="form-control" id="purchase_date" name="purchase_date" required value="{{ old('purchase_date', \Illuminate\Support\Carbon::parse($purchase->purchase_date)->format('Y-m-d')) }}">
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Product Grid Section -->
                    <div class="product-table-wrapper">
                        <div class="d-flex align-items-center mb-3">
                            <button type="button" class="btn btn-primary btn-add-row" id="addRowBefore">
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
                                        <th class="unit-price-col">Unit Price</th>
                                        <th class="stock-col">Stock</th>
                                        <th class="quantity-col">Quantity</th>
                                        <th class="total-col">Total</th>
                                        <th class="action-col">Action</th>
                                    </tr>
                                </thead>
                                <tbody id="productTableBody">
                                    @forelse($purchaseDetails as $idx => $detail)
                                    <tr class="product-row" data-row-index="{{ $idx }}">
                                        <td>
                                            <select class="form-control product-select" name="products[{{ $idx }}][product_id]" data-row="{{ $idx }}" style="width: 100%;">
                                                <option value="{{ $detail->product_id }}" selected>{{ ($detail->product->product_code ?? '') }} - {{ ($detail->product->product_name ?? 'Product') }}</option>
                                            </select>
                                            <input type="hidden" class="original-price" name="products[{{ $idx }}][original_price]" value="{{ $detail->unitcost }}">
                                        </td>
                                        <td>
                                            <input type="number" step="0.01" class="form-control unit-price" name="products[{{ $idx }}][unit_price]" value="{{ old('products.'.$idx.'.unit_price', $detail->unitcost) }}" data-row="{{ $idx }}" min="0">
                                        </td>
                                        <td>
                                            <span class="stock-label stock-display" data-row="{{ $idx }}">{{ $detail->product->product_store ?? 0 }}</span>
                                        </td>
                                        <td>
                                            <input type="number" class="form-control quantity" name="products[{{ $idx }}][quantity]" value="{{ old('products.'.$idx.'.quantity', $detail->quantity) }}" data-row="{{ $idx }}" min="1">
                                        </td>
                                        <td>
                                            <span class="total-display" data-row="{{ $idx }}">{{ number_format((float) $detail->total, 2, '.', '') }}</span>
                                            <input type="hidden" class="item-discount-value" name="products[{{ $idx }}][item_discount]" value="{{ old('products.'.$idx.'.item_discount', $detail->item_discount ?? 0) }}">
                                            <input type="hidden" class="total-value" name="products[{{ $idx }}][total]" value="{{ old('products.'.$idx.'.total', $detail->total) }}">
                                        </td>
                                        <td>
                                            @if($purchaseDetails->count() > 1)
                                            <button type="button" class="delete-row-btn" data-row="{{ $idx }}">
                                                <i class="ri-delete-bin-line"></i>
                                            </button>
                                            @endif
                                        </td>
                                    </tr>
                                    @empty
                                    <tr class="product-row" data-row-index="0">
                                        <td>
                                            <select class="form-control product-select" name="products[0][product_id]" data-row="0" style="width: 100%;">
                                                <option value="">Select Product</option>
                                            </select>
                                            <input type="hidden" class="original-price" name="products[0][original_price]" value="0">
                                        </td>
                                        <td>
                                            <input type="number" step="0.01" class="form-control unit-price" name="products[0][unit_price]" value="0" data-row="0" min="0">
                                        </td>
                                        <td>
                                            <span class="stock-label stock-display" data-row="0">0</span>
                                        </td>
                                        <td>
                                            <input type="number" class="form-control quantity" name="products[0][quantity]" value="1" data-row="0" min="1">
                                        </td>
                                        <td>
                                            <span class="total-display" data-row="0">0.00</span>
                                            <input type="hidden" class="item-discount-value" name="products[0][item_discount]" value="0">
                                            <input type="hidden" class="total-value" name="products[0][total]" value="0">
                                        </td>
                                        <td></td>
                                    </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>

                        <button type="button" class="btn btn-primary btn-add-row" id="addRowAfter">
                            <i class="ri-add-line"></i> Add Row
                        </button>
                    </div>

                    <!-- Purchase Summary -->
                    <!-- Invoice Summary and Comment Section -->
                    <div class="invoice-summary">
                        <div class="row">
                            <!-- Comment Section - Left Side -->
                            <div class="col-md-6">
                                <div class="comment-section">
                                    <div class="form-group">
                                        <label for="comment">Note (Optional)</label>
                                        <textarea class="form-control" id="comment" name="comment" rows="8" placeholder="Add any additional notes here...">{{ old('comment', $purchase->comment) }}</textarea>
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
                                    <input type="number" step="0.01" class="form-control d-inline-block" id="vat" name="vat" value="{{ old('vat', $purchase->vat) }}" min="0" style="width: 150px; display: inline-block;">
                                </div>
                                <div class="summary-row">
                                    <span class="summary-label">Discount on Purchase:</span>
                                    <input type="number" step="0.01" class="form-control d-inline-block" id="invoice_discount" name="invoice_discount" value="{{ old('invoice_discount', $purchase->invoice_discount) }}" min="0" style="width: 150px; display: inline-block;">
                                </div>
                                <div class="summary-row total">
                                    <span class="summary-label">Purchase Total:</span>
                                    <span class="summary-value" id="purchase_total">0.00</span>
                                    <input type="hidden" name="purchase_total" id="purchase_total_hidden" value="0">
                                </div>
                                <div class="summary-row" style="margin-top: 20px; padding-top: 15px; border-top: 1px solid #dee2e6;">
                                    <span class="summary-label">Payment Method <span class="text-danger">*</span>:</span>
                                    <select class="form-control d-inline-block" id="payment_status" name="payment_status" required style="width: 150px; display: inline-block;">
                                        <option value="">Select Method</option>
                                        <option value="cash" {{ old('payment_status', $purchase->payment_status) == 'cash' ? 'selected' : '' }}>Cash</option>
                                        <option value="bank" {{ old('payment_status', $purchase->payment_status) == 'bank' ? 'selected' : '' }}>Bank</option>
                                        <option value="cheque" {{ old('payment_status', $purchase->payment_status) == 'cheque' ? 'selected' : '' }}>Cheque</option>
                                        <option value="credit" {{ old('payment_status', $purchase->payment_status) == 'credit' ? 'selected' : '' }}>Credit</option>
                                    </select>
                                </div>
                                <div class="summary-row" id="shop_bank_group" style="display: none;">
                                    <span class="summary-label">Bank Account <span class="text-danger">*</span>:</span>
                                    <select class="form-control d-inline-block" id="shop_bank_id" name="shop_bank_id" style="width: 200px; display: inline-block;">
                                        <option value="">Select Bank</option>
                                        @foreach($shopBanks ?? [] as $bank)
                                        <option value="{{ $bank->id }}" {{ (string)old('shop_bank_id', optional($purchase->paymentLogs->first())->shop_bank_id) == (string)$bank->id ? 'selected' : '' }}>{{ $bank->name }}</option>
                                        @endforeach
                                    </select>
                                    @error('shop_bank_id')
                                    <div class="text-danger">{{ $message }}</div>
                                    @enderror
                                </div>
                                <div class="summary-row">
                                    <span class="summary-label">Payment Amount:</span>
                                    <input type="number" step="0.01" class="form-control d-inline-block" id="pay" name="pay" value="{{ old('pay', $purchase->pay) }}" min="0" style="width: 150px; display: inline-block;">
                                </div>
                                <div class="summary-row">
                                    <span class="summary-label">Due Amount:</span>
                                    <span class="summary-value" id="due_display">0.00</span>
                                    <input type="hidden" name="due" id="due_hidden" value="0">
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Submit Button -->
                    <div class="mt-4">
                        <button type="button" class="btn btn-primary btn-lg d-inline-flex align-items-center" id="updatePurchaseBtn">
                            <i class="ri-save-line js-update-purchase-icon"></i>
                            <span class="js-update-purchase-label ml-1">Update Purchase</span>
                            <span class="js-update-purchase-spinner spinner-border spinner-border-sm ml-2 d-none" role="status" aria-hidden="true"></span>
                        </button>
                        <a href="{{ route('purchases.show', $purchase->id) }}" class="btn btn-secondary btn-lg">Cancel</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

{{-- Add Product Modal - MUST be outside the purchase form to prevent conflicts --}}
@include('partials.add-product-modal')

<!-- Add Supplier Modal -->
<div class="modal fade" id="addSupplierModal" tabindex="-1" role="dialog" aria-labelledby="addSupplierModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-md" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="addSupplierModalLabel">Add New Supplier</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <form id="addSupplierForm">
                <div class="modal-body">
                    <div id="supplierFormErrors" class="alert alert-danger" style="display: none;"></div>
                    <div id="supplierFormSuccess" class="alert alert-success" style="display: none;"></div>
                    
                    <div class="form-group">
                        <label for="modal_shopname">Shop Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="modal_shopname" name="shopname" required>
                        <div class="invalid-feedback" id="error_shopname"></div>
                    </div>
                    
                    <div class="form-group">
                        <label for="modal_phone">Supplier Phone <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="modal_phone" name="phone" required>
                        <div class="invalid-feedback" id="error_phone"></div>
                    </div>
                    
                    <div class="form-group">
                        <label for="modal_email">Supplier Email</label>
                        <input type="email" class="form-control" id="modal_email" name="email">
                        <div class="invalid-feedback" id="error_email"></div>
                    </div>
                    
                    <div class="form-group">
                        <label for="modal_type">Type of Supplier <span class="text-danger">*</span></label>
                        <select class="form-control" id="modal_type" name="type" required>
                            <option value="">Select Type..</option>
                            <option value="Distributor">Distributor</option>
                            <option value="Whole Seller">Whole Seller</option>
                        </select>
                        <div class="invalid-feedback" id="error_type"></div>
                    </div>
                    
                    <div class="form-group">
                        <label for="modal_account_holder">Account Holder</label>
                        <input type="text" class="form-control" id="modal_account_holder" name="account_holder">
                        <div class="invalid-feedback" id="error_account_holder"></div>
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
                        <label for="modal_address">Supplier Address <span class="text-danger">*</span></label>
                        <textarea class="form-control" id="modal_address" name="address" rows="3" required></textarea>
                        <div class="invalid-feedback" id="error_address"></div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" id="saveSupplierBtn">
                        <span class="spinner-border spinner-border-sm d-none" id="saveSupplierSpinner" role="status" aria-hidden="true"></span>
                        <span id="saveSupplierBtnText">Save</span>
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
        let rowCount = {{ max(0, $purchaseDetails->count() - 1) }};

    // Initialize Select2 on existing product selects
    function initializeSelect2($select) {
        const rowIndex = $select.data('row');
        $select.select2({
            theme: 'bootstrap-5',
            placeholder: 'Select Product',
            allowClear: true,
            minimumInputLength: 2,
            ajax: {
                url: '{{ route("api.purchases.products.search") }}',
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
            const focusAttempts = [50, 100, 150, 200];
            focusAttempts.forEach(function(delay) {
                setTimeout(function() {
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
            
            // If data doesn't have required properties (manually added option), try to get from stored data
            if (!data.price && !data.stock && typeof window.newProductData !== 'undefined') {
                const productId = data.id;
                if (window.newProductData[productId]) {
                    data = window.newProductData[productId];
                }
            }
            
            // For purchase, prefer buying_price if available, otherwise use price
            const productPrice = (data.buying_price !== undefined) ? data.buying_price : (data.price || 0);
            
            $row.find('.original-price').val(productPrice);
            $row.find('.unit-price').val(productPrice);
            $row.find('.stock-display').text(data.stock || 0);
            
            calculateRowTotal(rowIdx);
        });

        // Handle clear selection
        $select.on('select2:clear', function (e) {
            const rowIdx = $(this).data('row');
            const $row = $('tr[data-row-index="' + rowIdx + '"]');
            
            $row.find('.original-price').val(0);
            $row.find('.unit-price').val(0);
            $row.find('.stock-display').text(0);
            
            calculateRowTotal(rowIdx);
        });
    }

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

    // Initialize Select2 on each product row (edit: pre-filled options)
    $('.product-select').each(function() {
        initializeSelect2($(this));
    });
    $('.product-select').each(function() {
        const r = $(this).data('row');
        calculateRowTotal(r);
    });
    calculatePurchaseTotal();

    // Handle unit price change
    $(document).on('input', '.unit-price', function() {
        const rowIndex = $(this).data('row');
        calculateRowTotal(rowIndex);
    });

    // Handle quantity change
    $(document).on('input', '.quantity', function() {
        const rowIndex = $(this).data('row');
        calculateRowTotal(rowIndex);
    });

    // Calculate row total and discount
    function calculateRowTotal(rowIndex) {
        const $row = $('tr[data-row-index="' + rowIndex + '"]');
        const unitPrice = parseFloat($row.find('.unit-price').val()) || 0;
        const originalPrice = parseFloat($row.find('.original-price').val()) || 0;
        const quantity = parseFloat($row.find('.quantity').val()) || 0;
        
        const total = unitPrice * quantity;
        const discount = (originalPrice - unitPrice) * quantity;
        
        $row.find('.total-display').text(total.toFixed(2));
        $row.find('.total-value').val(total.toFixed(2));
        $row.find('.item-discount-value').val(discount > 0 ? discount.toFixed(2) : '0.00');
        
        calculatePurchaseTotal();
    }

    // Handle VAT change
    $(document).on('input', '#vat', function() {
        calculatePurchaseTotal();
    });

    // Handle purchase discount change
    $(document).on('input', '#invoice_discount', function() {
        calculatePurchaseTotal();
    });

    // Handle payment amount change
    $(document).on('input', '#pay', function() {
        calculateDue();
    });

    // Calculate purchase total
    function calculatePurchaseTotal() {
        let subtotal = 0;
        
        $('.total-value').each(function() {
            subtotal += parseFloat($(this).val()) || 0;
        });
        
        const vat = parseFloat($('#vat').val()) || 0;
        const invoiceDiscount = parseFloat($('#invoice_discount').val()) || 0;
        const purchaseTotal = Math.max(0, subtotal + vat - invoiceDiscount);
        
        $('#subtotal').text(subtotal.toFixed(2));
        $('#purchase_total').text(purchaseTotal.toFixed(2));
        $('#purchase_total_hidden').val(purchaseTotal.toFixed(2));
        
        calculateDue();
    }

    // Calculate due amount
    function calculateDue() {
        const purchaseTotal = parseFloat($('#purchase_total_hidden').val()) || 0;
        const pay = parseFloat($('#pay').val()) || 0;
        const due = Math.max(0, purchaseTotal - pay);
        
        $('#due_display').text(due.toFixed(2));
        $('#due_hidden').val(due.toFixed(2));
        
        // Check credit limit
        checkCreditLimit(due);
    }
    
    // Check credit limit and show warning
    function checkCreditLimit(dueAmount) {
        const supplierId = $('#supplier_id').val();
        if (!supplierId) {
            $('#credit_warning_row').hide();
            return;
        }
        const selectedOption = $('#supplier_id option:selected');
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
        
        // Calculate new credit amount after this purchase
        const newCreditAmount = creditAmount + dueAmount;
        
        // Show warning if credit limit would be exceeded
        if (newCreditAmount > creditLimit) {
            const exceededBy = newCreditAmount - creditLimit;
            const warningText = `Credit limit will be exceeded! Current: ${creditAmount.toFixed(2)}, After this purchase: ${newCreditAmount.toFixed(2)} (Limit: ${creditLimit.toFixed(2)}). Exceeded by: ${exceededBy.toFixed(2)}`;
            $('#credit_warning_text').text(warningText);
            $('#credit_warning_row').show();
        } else {
            $('#credit_warning_row').hide();
        }
    }
    
    // Handle supplier change - show warning immediately when supplier is selected
    $(document).on('change', '#supplier_id', function() {
        checkCreditLimit(0);
        calculateDue();
    });
    
    // Handle payment status change (show bank dropdown for bank/cheque)
    function toggleBankGroup() {
        const status = ($('#payment_status').val() || '').toLowerCase();
        const isBankOrCheque = (status === 'bank' || status === 'cheque');
        $('#shop_bank_group').toggle(isBankOrCheque);
        if (!isBankOrCheque) $('#shop_bank_id').val('');
    }
    $(document).on('change', '#payment_status', function() {
        toggleBankGroup();
        calculateDue();
    });
    toggleBankGroup(); // initial state (e.g. after validation error with bank/cheque selected)

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
                    <input type="number" step="0.01" class="form-control unit-price" name="products[${rowCount}][unit_price]" value="0" data-row="${rowCount}" min="0">
                </td>
                <td>
                    <span class="stock-label stock-display" data-row="${rowCount}">0</span>
                </td>
                <td>
                    <input type="number" class="form-control quantity" name="products[${rowCount}][quantity]" value="1" data-row="${rowCount}" min="1">
                </td>
                <td>
                    <span class="total-display" data-row="${rowCount}">0.00</span>
                    <input type="hidden" class="item-discount-value" name="products[${rowCount}][item_discount]" value="0">
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
        calculatePurchaseTotal();
    });

    function setUpdatePurchaseSubmitting($purchaseForm, active) {
        const $btn = $('#updatePurchaseBtn');
        if (active) {
            $purchaseForm.data('submitting', true);
            $btn.prop('disabled', true);
            $btn.find('.js-update-purchase-label').text('Saving…');
            $btn.find('.js-update-purchase-spinner').removeClass('d-none');
            $btn.find('.js-update-purchase-icon').addClass('d-none');
        } else {
            $purchaseForm.data('submitting', false);
            $btn.prop('disabled', false);
            $btn.find('.js-update-purchase-label').text('Update Purchase');
            $btn.find('.js-update-purchase-spinner').addClass('d-none');
            $btn.find('.js-update-purchase-icon').removeClass('d-none');
        }
    }

    // Form submission (runs for Update button and Enter key)
    $('#purchaseForm').on('submit', function(e) {
        const $purchaseForm = $(this);

        if ($purchaseForm.data('submitting')) {
            e.preventDefault();
            return false;
        }

        const supplierId = $('#supplier_id').val();
        if (!supplierId) {
            e.preventDefault();
            alert('Please select a supplier');
            return false;
        }

        const purchaseDate = $('#purchase_date').val();
        if (!purchaseDate) {
            e.preventDefault();
            alert('Please select a date');
            return false;
        }

        let hasProducts = false;
        $('.product-select').each(function() {
            if ($(this).val()) {
                hasProducts = true;
                return false;
            }
        });

        if (!hasProducts) {
            e.preventDefault();
            alert('Please add at least one product');
            return false;
        }

        const paymentStatus = $('#payment_status').val();
        if (!paymentStatus) {
            e.preventDefault();
            alert('Please select a payment method');
            return false;
        }

        setUpdatePurchaseSubmitting($purchaseForm, true);
        return true;
    });

    $('#updatePurchaseBtn').on('click', function(e) {
        e.preventDefault();
        e.stopPropagation();

        const $purchaseForm = $('#purchaseForm');
        if ($purchaseForm.length === 0 || $purchaseForm.data('submitting')) {
            return false;
        }

        if (!$purchaseForm[0].checkValidity()) {
            $purchaseForm[0].reportValidity();
            return false;
        }

        $purchaseForm.submit();
    });

    // Handle Add Supplier Modal Form Submission - prevent form submit
    $('#addSupplierForm').on('submit', function(e) {
        e.preventDefault();
        e.stopPropagation();
        e.stopImmediatePropagation();
        return false;
    });
    
    // Handle Save Supplier button click
    $(document).on('click', '#saveSupplierBtn', function(e) {
        e.preventDefault();
        e.stopPropagation();
        e.stopImmediatePropagation();
        
        // Hide previous errors and success messages
        $('#supplierFormErrors').hide().html('');
        $('#supplierFormSuccess').hide();
        $('.invalid-feedback').hide();
        $('.form-control').removeClass('is-invalid');
        
        // Disable save button and show loading
        const $saveBtn = $('#saveSupplierBtn');
        const $saveBtnText = $('#saveSupplierBtnText');
        const $spinner = $('#saveSupplierSpinner');
        
        $saveBtn.prop('disabled', true);
        $saveBtnText.text('Saving...');
        $spinner.removeClass('d-none');
        
        // Get form data
        const formData = {
            shopname: $('#modal_shopname').val(),
            phone: $('#modal_phone').val(),
            email: $('#modal_email').val() || '',
            type: $('#modal_type').val(),
            account_holder: $('#modal_account_holder').val() || '',
            credit_limit: $('#modal_credit_limit').val() || 0,
            credit_days: $('#modal_credit_days').val() || 0,
            credit_amount: $('#modal_credit_amount').val() || 0,
            address: $('#modal_address').val(),
        };
        
        // Submit via AJAX
        $.ajax({
            url: '{{ route("suppliers.store") }}',
            method: 'POST',
            data: formData,
            headers: {
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') || $('input[name="_token"]').val(),
                'X-Requested-With': 'XMLHttpRequest'
            },
            success: function(response) {
                if (response.success) {
                    // Show success message
                    $('#supplierFormSuccess').text(response.message).show();
                    
                    // Add new supplier to dropdown and select it
                    const $supplierSelect = $('#supplier_id');
                    const newOption = $('<option>', {
                        value: response.supplier.id,
                        text: response.supplier.shopname || response.supplier.name,
                        'data-credit-limit': response.supplier.credit_limit,
                        'data-credit-amount': response.supplier.credit_amount,
                        selected: true
                    });
                    $supplierSelect.append(newOption);
                    $supplierSelect.val(response.supplier.id).trigger('change');
                    
                    // Reset form fields
                    $('#modal_shopname').val('');
                    $('#modal_phone').val('');
                    $('#modal_email').val('');
                    $('#modal_type').val('');
                    $('#modal_account_holder').val('');
                    $('#modal_credit_limit').val(0);
                    $('#modal_credit_days').val(0);
                    $('#modal_credit_amount').val(0);
                    $('#modal_address').val('');
                    
                    // Auto-close modal after 3 seconds
                    let countdown = 3;
                    const countdownInterval = setInterval(function() {
                        countdown--;
                        if (countdown > 0) {
                            $('#supplierFormSuccess').text(response.message + ' Closing in ' + countdown + ' seconds...');
                        } else {
                            clearInterval(countdownInterval);
                            // Close modal manually
                            var $modal = $('#addSupplierModal');
                            $modal.removeClass('show');
                            $modal.css('display', 'none');
                            $modal.attr('aria-hidden', 'true');
                            $('.modal-backdrop').remove();
                            $('body').removeClass('modal-open');
                            $('body').css({'overflow': '', 'padding-right': ''});
                            // Reset form and messages after modal closes
                            setTimeout(function() {
                                $('#modal_shopname').val('');
                                $('#modal_phone').val('');
                                $('#modal_email').val('');
                                $('#modal_type').val('');
                                $('#modal_account_holder').val('');
                                $('#modal_credit_limit').val(0);
                                $('#modal_credit_days').val(0);
                                $('#modal_credit_amount').val(0);
                                $('#modal_address').val('');
                                $('#supplierFormSuccess').hide();
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
                    $('#supplierFormErrors').html(errorHtml).show();
                } else {
                    // Other errors
                    $('#supplierFormErrors').html('<p>An error occurred. Please try again.</p>').show();
                }
                
                return false;
            }
        });
    });
    
    // Reset modal when closed
    $(document).on('hidden.bs.modal', '#addSupplierModal', function() {
        $('#modal_shopname').val('');
        $('#modal_phone').val('');
        $('#modal_email').val('');
        $('#modal_type').val('');
        $('#modal_account_holder').val('');
        $('#modal_credit_limit').val(0);
        $('#modal_credit_days').val(0);
        $('#modal_credit_amount').val(0);
        $('#modal_address').val('');
        $('#supplierFormErrors').hide().html('');
        $('#supplierFormSuccess').hide();
        $('.invalid-feedback').hide();
        $('.form-control').removeClass('is-invalid');
        $('#saveSupplierBtn').prop('disabled', false);
        $('#saveSupplierBtnText').text('Save');
        $('#saveSupplierSpinner').addClass('d-none');
    });
    });
})(jQuery);
</script>
@endsection
