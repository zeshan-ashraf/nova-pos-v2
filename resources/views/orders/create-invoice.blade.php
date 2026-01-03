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
                                        <label for="customer_id">Customer <span class="text-danger">*</span></label>
                                        <div class="d-flex align-items-center">
                                            <select class="form-control" id="customer_id" name="customer_id" style="max-width: 70%;">
                                                <option value="">Select Customer</option>
                                                @foreach($customers as $customer)
                                                    <option value="{{ $customer->id }}" 
                                                        data-credit-limit="{{ $customer->credit_limit ?? 0 }}" 
                                                        data-credit-amount="{{ $customer->credit_amount ?? 0 }}">
                                                        {{ $customer->shopname ?: $customer->name }}
                                                    </option>
                                                @endforeach
                                            </select>
                                            <button type="button" class="btn btn-success btn-sm ml-2" id="addCustomerBtn" data-toggle="modal" data-target="#addCustomerModal">
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
                                        <label for="customer_id">Customer <span class="text-danger">*</span></label>
                                        <div class="d-flex align-items-center">
                                            <select class="form-control" id="customer_id" name="customer_id" required style="max-width: 70%;">
                                                <option value="">Select Customer</option>
                                                @foreach($customers as $customer)
                                                    <option value="{{ $customer->id }}" 
                                                        data-credit-limit="{{ $customer->credit_limit ?? 0 }}" 
                                                        data-credit-amount="{{ $customer->credit_amount ?? 0 }}">
                                                        {{ $customer->shopname ?: $customer->name }}
                                                    </option>
                                                @endforeach
                                            </select>
                                            <button type="button" class="btn btn-success btn-sm ml-2" id="addCustomerBtn" data-toggle="modal" data-target="#addCustomerModal">
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
                    </div>

                    <!-- Product Grid Section -->
                    <div class="product-table-wrapper">
                        <button type="button" class="btn btn-success btn-add-row" id="addRowBefore">
                            <i class="ri-add-line"></i> Add Row
                        </button>

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
                                        <label for="comment">Comment (Optional)</label>
                                        <textarea class="form-control" id="comment" name="comment" rows="8" placeholder="Add any additional notes or comments here..."></textarea>
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
                                <div class="summary-row" style="margin-top: 20px; padding-top: 15px; border-top: 1px solid #dee2e6;">
                                    <span class="summary-label">Payment Method <span class="text-danger">*</span>:</span>
                                    <select class="form-control d-inline-block" id="payment_status" name="payment_status" required style="width: 150px; display: inline-block;">
                                        <option value="">Select Method</option>
                                        <option value="cash">Cash</option>
                                        <option value="bank">Bank</option>
                                        <option value="cheque">Cheque</option>
                                        <option value="credit">Credit</option>
                                    </select>
                                </div>
                                <div class="summary-row">
                                    <span class="summary-label">Payment Amount:</span>
                                    <input type="number" step="0.01" class="form-control d-inline-block" id="pay" name="pay" value="0" min="0" style="width: 150px; display: inline-block;">
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
                        <button type="submit" class="btn btn-primary btn-lg" id="createInvoiceBtn">
                            <i class="ri-file-add-line"></i> Create Invoice
                        </button>
                        <a href="{{ route('order.index') }}" class="btn btn-secondary btn-lg">Cancel</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

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
            <form id="addCustomerForm">
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
        let rowCount = 0;

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
            const data = e.params.data;
            const rowIdx = $(this).data('row');
            const $row = $('tr[data-row-index="' + rowIdx + '"]');
            
            $row.find('.original-price').val(data.price);
            $row.find('.unit-price').val(data.price);
            $row.find('.stock-display').text(data.stock);
            $row.find('.product-code-display').text(data.code || '-');
            
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

    // Initialize date/time with current date/time
    const now = new Date();
    const year = now.getFullYear();
    const month = String(now.getMonth() + 1).padStart(2, '0');
    const day = String(now.getDate()).padStart(2, '0');
    const hours = String(now.getHours()).padStart(2, '0');
    const minutes = String(now.getMinutes()).padStart(2, '0');
    const formattedDateTime = `${year}-${month}-${day}T${hours}:${minutes}`;
    $('#order_date').val(formattedDateTime);

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

    // Handle payment amount change
    $(document).on('input', '#pay', function() {
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
        
        // If payment method is cash, auto-update payment amount
        const paymentMethod = $('#payment_status').val();
        if (paymentMethod === 'cash') {
            $('#pay').val(invoiceTotal.toFixed(2));
        }
        
        calculateDue();
    }

    // Calculate due amount
    function calculateDue() {
        const invoiceTotal = parseFloat($('#invoice_total_hidden').val()) || 0;
        const pay = parseFloat($('#pay').val()) || 0;
        const due = Math.max(0, invoiceTotal - pay);
        
        $('#due_display').text(due.toFixed(2));
        $('#due_hidden').val(due.toFixed(2));
        
        // Check credit limit
        checkCreditLimit(due);
    }
    
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
    $(document).on('change', '#customer_id', function() {
        // Check credit limit immediately (with 0 due amount to check current status)
        checkCreditLimit(0);
        // Also recalculate due if there's already an invoice total
        calculateDue();
    });
    
    // Handle payment status change
    $(document).on('change', '#payment_status', function() {
        const paymentMethod = $(this).val();
        
        // If payment method is cash, auto-populate payment amount with invoice total
        if (paymentMethod === 'cash') {
            const invoiceTotal = parseFloat($('#invoice_total_hidden').val()) || 0;
            $('#pay').val(invoiceTotal.toFixed(2));
        }
        
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

    // Form submission
    $('#invoiceForm').on('submit', function(e) {
        // Validation
        @if($childShops->isNotEmpty())
        // Parent shop: check if customer or shop is selected
        const selectedType = $('input[name="select_type"]:checked').val();
        if (selectedType === 'customer') {
            const customerId = $('#customer_id').val();
            if (!customerId) {
                e.preventDefault();
                alert('Please select a customer');
                return false;
            }
        } else if (selectedType === 'shop') {
            const shopId = $('#shop_id').val();
            if (!shopId) {
                e.preventDefault();
                alert('Please select a child shop');
                return false;
            }
        }
        @else
        // Regular shop: must select customer
        const customerId = $('#customer_id').val();
        if (!customerId) {
            e.preventDefault();
            alert('Please select a customer');
            return false;
        }
        @endif

        const orderDate = $('#order_date').val();
        if (!orderDate) {
            e.preventDefault();
            alert('Please select a date and time');
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

        // Validate: If payment method is cash, payment amount must equal invoice total
        if (paymentStatus === 'cash') {
            const invoiceTotal = parseFloat($('#invoice_total_hidden').val()) || 0;
            const payAmount = parseFloat($('#pay').val()) || 0;
            
            if (Math.abs(payAmount - invoiceTotal) > 0.01) {
                e.preventDefault();
                alert('Payment amount must equal invoice total when payment method is Cash.');
                $('#pay').focus();
                return false;
            }
        }

        // Allow form submission
        return true;
    });

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
