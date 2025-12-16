@extends('dashboard.body.main')

@section('specificpagestyles')
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
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

                    <!-- Customer and Date Section -->
                    <div class="form-row-invoice">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="customer_id">Customer <span class="text-danger">*</span></label>
                                    <select class="form-control" id="customer_id" name="customer_id" required>
                                        <option value="">Select Customer</option>
                                        @foreach($customers as $customer)
                                            <option value="{{ $customer->id }}" 
                                                data-credit-limit="{{ $customer->credit_limit ?? 0 }}" 
                                                data-credit-amount="{{ $customer->credit_amount ?? 0 }}">
                                                {{ $customer->shopname ?: $customer->name }}
                                            </option>
                                        @endforeach
                                    </select>
                                    @error('customer_id')
                                        <div class="text-danger">{{ $message }}</div>
                                    @enderror
                                </div>
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
                        <button type="button" class="btn btn-primary btn-add-row" id="addRowBefore">
                            <i class="ri-add-line"></i> Add Row
                        </button>

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
                                            <select class="form-control product-select" name="products[0][product_id]" data-row="0">
                                                <option value="">Select Product</option>
                                                @foreach($products as $product)
                                                    <option value="{{ $product->id }}" 
                                                        data-price="{{ $product->selling_price ?? 0 }}" 
                                                        data-stock="{{ $product->product_store ?? 0 }}">
                                                        {{ $product->product_name }} (Stock: {{ $product->product_store ?? 0 }})
                                                    </option>
                                                @endforeach
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

                        <button type="button" class="btn btn-primary btn-add-row" id="addRowAfter">
                            <i class="ri-add-line"></i> Add Row
                        </button>
                    </div>

                    <!-- Invoice Summary -->
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

                    <!-- Comment Section -->
                    <div class="comment-section">
                        <div class="form-group">
                            <label for="comment">Comment (Optional)</label>
                            <textarea class="form-control" id="comment" name="comment" rows="4" placeholder="Add any additional notes or comments here..."></textarea>
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
@endsection

@section('specificpagescripts')
<script>
(function($) {
    'use strict';
    
    $(document).ready(function() {
        let rowCount = 0;

    // Initialize date/time with current date/time
    const now = new Date();
    const year = now.getFullYear();
    const month = String(now.getMonth() + 1).padStart(2, '0');
    const day = String(now.getDate()).padStart(2, '0');
    const hours = String(now.getHours()).padStart(2, '0');
    const minutes = String(now.getMinutes()).padStart(2, '0');
    const formattedDateTime = `${year}-${month}-${day}T${hours}:${minutes}`;
    $('#order_date').val(formattedDateTime);

    // Handle product selection change
    $(document).on('change', '.product-select', function() {
        const rowIndex = $(this).data('row');
        const selectedOption = $(this).find('option:selected');
        const price = parseFloat(selectedOption.data('price')) || 0;
        const stock = parseFloat(selectedOption.data('stock')) || 0;
        
        const $row = $(this).closest('tr');
        $row.find('.original-price').val(price);
        $row.find('.unit-price').val(price);
        $row.find('.stock-display').text(stock);
        
        // Update row calculations
        calculateRowTotal(rowIndex);
    });

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
        calculateDue();
    });

    // Add row function
    function addRow() {
        rowCount++;
        
        // Build product options HTML
        let productOptions = '<option value="">Select Product</option>';
        @foreach($products as $product)
            productOptions += '<option value="{{ $product->id }}" data-price="{{ $product->selling_price ?? 0 }}" data-stock="{{ $product->product_store ?? 0 }}">{{ $product->product_name }} (Stock: {{ $product->product_store ?? 0 }})</option>';
        @endforeach
        
        const newRow = `
            <tr class="product-row" data-row-index="${rowCount}">
                <td>
                    <select class="form-control product-select" name="products[${rowCount}][product_id]" data-row="${rowCount}">
                        ${productOptions}
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
        $('tr[data-row-index="' + rowIndex + '"]').remove();
        calculateInvoiceTotal();
    });

    // Form submission
    $('#invoiceForm').on('submit', function(e) {
        // Validation
        const customerId = $('#customer_id').val();
        if (!customerId) {
            e.preventDefault();
            alert('Please select a customer');
            return false;
        }

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

        // Allow form submission
        return true;
    });
    });
})(jQuery);
</script>
@endsection
