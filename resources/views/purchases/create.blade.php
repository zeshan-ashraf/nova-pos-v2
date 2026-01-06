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
                    <h4>Create New Purchase</h4>
                </div>
                
                <!-- Credit Limit Warning -->
                <div id="credit_warning_row" style="display: none; margin-bottom: 20px;">
                    <div class="alert alert-warning mb-0" id="credit_warning" style="padding: 15px; margin: 0;">
                        <i class="ri-alert-line"></i> <strong>Warning:</strong> <span id="credit_warning_text"></span>
                    </div>
                </div>

                <form id="purchaseForm" method="POST" action="{{ route('purchases.store') }}">
                    @csrf

                    <!-- Supplier and Date Section -->
                    <div class="form-row-invoice">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="supplier_id">Supplier <span class="text-danger">*</span></label>
                                    <select class="form-control" id="supplier_id" name="supplier_id" required>
                                        <option value="">Select Supplier</option>
                                        @foreach($suppliers as $supplier)
                                            <option value="{{ $supplier->id }}" 
                                                data-credit-limit="{{ $supplier->credit_limit ?? 0 }}" 
                                                data-credit-amount="{{ $supplier->credit_amount ?? 0 }}">
                                                {{ $supplier->shopname ?: $supplier->name }}
                                            </option>
                                        @endforeach
                                    </select>
                                    @error('supplier_id')
                                        <div class="text-danger">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="purchase_date">Date <span class="text-danger">*</span></label>
                                    <input type="date" class="form-control" id="purchase_date" name="purchase_date" required>
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
                            @include('partials.add-product-modal')
                        </div>

                        <div class="table-responsive">
                            <table class="product-table" id="productTable">
                                <thead>
                                    <tr>
                                        <th class="product-name-col">Product</th>
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
                                            <input type="number" step="0.01" class="form-control unit-price" name="products[0][unit_price]" value="0" data-row="0" min="0">
                                        </td>
                                        <td>
                                            <span class="stock-label stock-display" data-row="0">0</span>
                                        </td>
                                        <td>
                                            <input type="number" class="form-control quantity" name="products[0][quantity]" value="1" data-row="0" min="1">
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

                        <button type="button" class="btn btn-primary btn-add-row" id="addRowAfter">
                            <i class="ri-add-line"></i> Add Row
                        </button>
                    </div>

                    <!-- Purchase Summary -->
                    <div class="invoice-summary">
                        <div class="row">
                            <div class="col-md-6 offset-md-6">
                                <div class="summary-row">
                                    <span class="summary-label">Subtotal:</span>
                                    <span class="summary-value" id="subtotal">0.00</span>
                                </div>
                                <div class="summary-row">
                                    <span class="summary-label">VAT:</span>
                                    <input type="number" step="0.01" class="form-control d-inline-block" id="vat" name="vat" value="0" min="0" style="width: 150px; display: inline-block;">
                                </div>
                                <div class="summary-row">
                                    <span class="summary-label">Discount on Purchase:</span>
                                    <input type="number" step="0.01" class="form-control d-inline-block" id="invoice_discount" name="invoice_discount" value="0" min="0" style="width: 150px; display: inline-block;">
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

                    <!-- Comment Section -->
                    <div class="comment-section">
                        <div class="form-group">
                            <label for="comment">Comment (Optional)</label>
                            <textarea class="form-control" id="comment" name="comment" rows="4" placeholder="Add any additional notes or comments here..."></textarea>
                        </div>
                    </div>

                    <!-- Submit Button -->
                    <div class="mt-4">
                        <button type="submit" class="btn btn-primary btn-lg" id="createPurchaseBtn">
                            <i class="ri-file-add-line"></i> Create Purchase
                        </button>
                        <a href="{{ route('purchases.index') }}" class="btn btn-secondary btn-lg">Cancel</a>
                    </div>
                </form>
            </div>
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

    // Initialize date with current date
    const now = new Date();
    const year = now.getFullYear();
    const month = String(now.getMonth() + 1).padStart(2, '0');
    const day = String(now.getDate()).padStart(2, '0');
    const formattedDate = `${year}-${month}-${day}`;
    $('#purchase_date').val(formattedDate);

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

    // Initialize Select2 on first product select
    initializeSelect2($('.product-select[data-row="0"]'));

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
        const discountDisplay = discount > 0 ? discount.toFixed(2) : '0.00';
        
        $row.find('.total-display').text(total.toFixed(2));
        $row.find('.total-value').val(total.toFixed(2));
        $row.find('.discount-display').text(discountDisplay);
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
        // Check credit limit immediately (with 0 due amount to check current status)
        checkCreditLimit(0);
        // Also recalculate due if there's already a purchase total
        calculateDue();
    });
    
    // Handle payment status change
    $(document).on('change', '#payment_status', function() {
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
                    <input type="number" step="0.01" class="form-control unit-price" name="products[${rowCount}][unit_price]" value="0" data-row="${rowCount}" min="0">
                </td>
                <td>
                    <span class="stock-label stock-display" data-row="${rowCount}">0</span>
                </td>
                <td>
                    <input type="number" class="form-control quantity" name="products[${rowCount}][quantity]" value="1" data-row="${rowCount}" min="1">
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
        calculatePurchaseTotal();
    });

    // Form submission
    $('#purchaseForm').on('submit', function(e) {
        // Validation
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

        // Allow form submission
        return true;
    });
    });
})(jQuery);
</script>
@endsection
