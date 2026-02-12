@extends('dashboard.body.main')

@section('specificpagestyles')
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <style>
        .return-form-container {
            background: #fff;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .return-header {
            border-bottom: 2px solid #e9ecef;
            padding-bottom: 20px;
            margin-bottom: 30px;
        }
        .return-header h4 {
            margin: 0;
            color: #333;
        }
        .form-row-return {
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
        .product-table select,
        .product-table input[type="checkbox"] {
            width: 100%;
            padding: 8px;
            border: 1px solid #ced4da;
            border-radius: 4px;
        }
        .return-summary {
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
        .order-info {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 4px;
            margin-bottom: 20px;
        }
        .order-info-item {
            margin: 5px 0;
        }
        #orderDetailsTable {
            display: none;
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

            <div class="return-form-container">
                <div class="return-header">
                    <h4>Create Sale Return</h4>
                </div>

                <form id="returnForm" method="POST" action="{{ route('sale-returns.store') }}">
                    @csrf
                    <input type="hidden" name="order_id" id="order_id" value="">

                    <!-- Customer and Order Selection -->
                    <div class="form-row-return">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="customer_id">Customer <span class="text-danger">*</span></label>
                                    <select class="form-control" id="customer_id" name="customer_id" required>
                                        <option value="">Select Customer</option>
                                        @foreach($customers as $customer)
                                            <option value="{{ $customer->id }}">
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
                                    <label for="order_select">Invoice/Order <span class="text-danger">*</span></label>
                                    <select class="form-control" id="order_select" name="order_select" required disabled>
                                        <option value="">Select Customer First</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Order Information Display -->
                    <div id="orderInfo" class="order-info" style="display: none;">
                        <h6>Order Information:</h6>
                        <div class="row">
                            <div class="col-md-3">
                                <div class="order-info-item">
                                    <strong>Invoice No:</strong> <span id="info_invoice_no">-</span>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="order-info-item">
                                    <strong>Order Date:</strong> <span id="info_order_date">-</span>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="order-info-item">
                                    <strong>Total:</strong> <span id="info_total">-</span>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="order-info-item">
                                    <strong>Paid:</strong> <span id="info_paid">-</span> | <strong>Due:</strong> <span id="info_due">-</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Return Date and Reason -->
                    <div class="form-row-return">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="return_date">Return Date & Time <span class="text-danger">*</span></label>
                                    <input type="datetime-local" class="form-control" id="return_date" name="return_date" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="reason">Return Reason (Optional)</label>
                                    <input type="text" class="form-control" id="reason" name="reason" placeholder="Enter return reason">
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Product Return Table -->
                    <div class="product-table-wrapper" id="orderDetailsTable">
                        <h6>Select Items to Return:</h6>
                        <div class="table-responsive">
                            <table class="product-table" id="returnTable">
                                <thead>
                                    <tr>
                                        <th>Select</th>
                                        <th>Product Name</th>
                                        <th>Product Code</th>
                                        <th>Ordered Qty</th>
                                        <th>Already Returned</th>
                                        <th>Available to Return</th>
                                        <th>Return Qty</th>
                                        <th>Unit Price</th>
                                        <th>Item Discount</th>
                                        <th>Total</th>
                                    </tr>
                                </thead>
                                <tbody id="returnTableBody">
                                    <!-- Will be populated via AJAX -->
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Return Summary -->
                    <div class="return-summary" id="returnSummary" style="display: none;">
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
                                    <span class="summary-label">Discount on Return:</span>
                                    <input type="number" step="0.01" class="form-control d-inline-block" id="invoice_discount" name="invoice_discount" value="0" min="0" style="width: 150px; display: inline-block;">
                                </div>
                                <div class="summary-row total">
                                    <span class="summary-label">Return Total:</span>
                                    <span class="summary-value" id="return_total">0.00</span>
                                    <input type="hidden" name="return_total" id="return_total_hidden" value="0">
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Submit Button -->
                    <div class="mt-4">
                        <button type="submit" class="btn btn-primary btn-lg" id="createReturnBtn" disabled>
                            <i class="ri-arrow-go-back-line"></i> Create Return
                        </button>
                        <a href="{{ route('sale-returns.index') }}" class="btn btn-secondary btn-lg">Cancel</a>
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
        // Initialize return date with current date/time
        const now = new Date();
        const year = now.getFullYear();
        const month = String(now.getMonth() + 1).padStart(2, '0');
        const day = String(now.getDate()).padStart(2, '0');
        const hours = String(now.getHours()).padStart(2, '0');
        const minutes = String(now.getMinutes()).padStart(2, '0');
        const formattedDateTime = `${year}-${month}-${day}T${hours}:${minutes}`;
        $('#return_date').val(formattedDateTime);

        // Load orders when customer is selected
        $('#customer_id').on('change', function() {
            const customerId = $(this).val();
            $('#order_select').html('<option value="">Loading...</option>').prop('disabled', true);
            $('#orderInfo').hide();
            $('#orderDetailsTable').hide();
            $('#returnSummary').hide();
            $('#createReturnBtn').prop('disabled', true);
            
            if (!customerId) {
                $('#order_select').html('<option value="">Select Customer First</option>').prop('disabled', true);
                return;
            }

            $.ajax({
                url: `/sale-returns/customer/${customerId}/orders`,
                method: 'GET',
                success: function(response) {
                    let options = '<option value="">Select Invoice/Order</option>';
                    if (response.orders && response.orders.length > 0) {
                        response.orders.forEach(function(order) {
                            options += `<option value="${order.id}" 
                                data-invoice="${order.invoice_no}" 
                                data-date="${order.order_date}" 
                                data-total="${order.total}" 
                                data-pay="${order.pay}" 
                                data-due="${order.due}">
                                ${order.invoice_no} - ${order.order_date} (Total: ${order.total})
                            </option>`;
                        });
                    } else {
                        options = '<option value="">No orders found for this customer</option>';
                    }
                    $('#order_select').html(options).prop('disabled', false);
                },
                error: function() {
                    $('#order_select').html('<option value="">Error loading orders</option>').prop('disabled', true);
                }
            });
        });

        // Load order details when order is selected
        $('#order_select').on('change', function() {
            const orderId = $(this).val();
            const selectedOption = $(this).find('option:selected');
            
            if (!orderId) {
                $('#orderInfo').hide();
                $('#orderDetailsTable').hide();
                $('#returnSummary').hide();
                $('#createReturnBtn').prop('disabled', true);
                return;
            }

            // Update order info
            $('#order_id').val(orderId);
            $('#info_invoice_no').text(selectedOption.data('invoice') || '-');
            $('#info_order_date').text(selectedOption.data('date') || '-');
            $('#info_total').text(parseFloat(selectedOption.data('total') || 0).toFixed(2));
            $('#info_paid').text(parseFloat(selectedOption.data('pay') || 0).toFixed(2));
            $('#info_due').text(parseFloat(selectedOption.data('due') || 0).toFixed(2));
            $('#orderInfo').show();

            // Load order details
            $.ajax({
                url: `/sale-returns/order/${orderId}/details`,
                method: 'GET',
                success: function(response) {
                    let tbody = '';
                    if (response.order_details && response.order_details.length > 0) {
                        response.order_details.forEach(function(detail, index) {
                            const availableQty = detail.available_to_return;
                            const isDisabled = availableQty <= 0;
                            
                            tbody += `<tr data-order-detail-id="${detail.id}">
                                <td>
                                    <input type="checkbox" class="return-checkbox" data-index="${index}" ${isDisabled ? 'disabled' : ''}>
                                    <input type="hidden" name="products[${index}][order_detail_id]" value="${detail.id}">
                                    <input type="hidden" name="products[${index}][product_id]" value="${detail.product_id}">
                                </td>
                                <td>${detail.product_name}</td>
                                <td>${detail.product_code}</td>
                                <td>${detail.quantity}</td>
                                <td>${detail.returned_quantity}</td>
                                <td><strong>${availableQty}</strong></td>
                                <td>
                                    <input type="number" class="form-control return-quantity" 
                                        name="products[${index}][quantity]" 
                                        data-index="${index}" 
                                        data-max="${availableQty}"
                                        value="0" 
                                        min="1" 
                                        max="${availableQty}"
                                        ${isDisabled ? 'disabled' : ''}
                                        style="width: 80px;">
                                </td>
                                <td>
                                    <input type="number" step="0.01" class="form-control unit-price" 
                                        name="products[${index}][unit_price]" 
                                        data-index="${index}"
                                        value="${detail.unitcost}" 
                                        min="0" 
                                        readonly
                                        style="width: 100px;">
                                </td>
                                <td>
                                    <input type="number" step="0.01" class="form-control item-discount" 
                                        name="products[${index}][item_discount]" 
                                        data-index="${index}"
                                        value="${detail.item_discount || 0}" 
                                        min="0"
                                        style="width: 100px;">
                                </td>
                                <td>
                                    <span class="total-display" data-index="${index}">0.00</span>
                                    <input type="hidden" class="total-value" name="products[${index}][total]" value="0">
                                </td>
                            </tr>`;
                        });
                    } else {
                        tbody = '<tr><td colspan="10" class="text-center">No items found in this order</td></tr>';
                    }
                    $('#returnTableBody').html(tbody);
                    $('#orderDetailsTable').show();
                },
                error: function() {
                    alert('Error loading order details');
                }
            });
        });

        // Handle checkbox change
        $(document).on('change', '.return-checkbox', function() {
            const index = $(this).data('index');
            const $row = $(this).closest('tr');
            const $qtyInput = $row.find('.return-quantity');
            
            if ($(this).is(':checked')) {
                $qtyInput.prop('disabled', false);
                $qtyInput.val(1);
                calculateRowTotal(index);
            } else {
                $qtyInput.prop('disabled', true).val(0);
                $row.find('.total-display').text('0.00');
                $row.find('.total-value').val(0);
                calculateReturnTotal();
            }
        });

        // Handle quantity change
        $(document).on('input', '.return-quantity', function() {
            const index = $(this).data('index');
            const maxQty = parseInt($(this).data('max')) || 0;
            let qty = parseInt($(this).val()) || 0;
            
            if (qty > maxQty) {
                $(this).val(maxQty);
                qty = maxQty;
                alert(`Cannot return more than ${maxQty} items`);
            }
            
            if (qty > 0) {
                $(this).closest('tr').find('.return-checkbox').prop('checked', true);
            }
            
            calculateRowTotal(index);
        });

        // Handle unit price and discount change
        $(document).on('input', '.unit-price, .item-discount', function() {
            const index = $(this).data('index');
            calculateRowTotal(index);
        });

        // Handle VAT and discount change
        $(document).on('input', '#vat, #invoice_discount', function() {
            calculateReturnTotal();
        });

        // Calculate row total
        function calculateRowTotal(index) {
            const $row = $(`tr[data-order-detail-id]`).eq(index);
            const unitPrice = parseFloat($row.find('.unit-price').val()) || 0;
            const quantity = parseFloat($row.find('.return-quantity').val()) || 0;
            const itemDiscount = parseFloat($row.find('.item-discount').val()) || 0;
            
            const total = Math.max(0, (unitPrice * quantity) - itemDiscount);
            
            $row.find('.total-display').text(total.toFixed(2));
            $row.find('.total-value').val(total.toFixed(2));
            
            calculateReturnTotal();
        }

        // Calculate return total
        function calculateReturnTotal() {
            let subtotal = 0;
            
            $('.total-value').each(function() {
                subtotal += parseFloat($(this).val()) || 0;
            });
            
            const vat = parseFloat($('#vat').val()) || 0;
            const invoiceDiscount = parseFloat($('#invoice_discount').val()) || 0;
            const returnTotal = Math.max(0, subtotal + vat - invoiceDiscount);
            
            $('#subtotal').text(subtotal.toFixed(2));
            $('#return_total').text(returnTotal.toFixed(2));
            $('#return_total_hidden').val(returnTotal.toFixed(2));
            
            // Enable submit button if there are items selected
            const hasSelectedItems = $('.return-checkbox:checked').length > 0;
            $('#createReturnBtn').prop('disabled', !hasSelectedItems);
            $('#returnSummary').toggle(hasSelectedItems);
        }

        // Form validation and cleanup before submit
        $('#returnForm').on('submit', function(e) {
            const hasSelectedItems = $('.return-checkbox:checked').length > 0;
            if (!hasSelectedItems) {
                e.preventDefault();
                alert('Please select at least one item to return');
                return false;
            }
            
            // Validate quantities and reindex selected items
            let hasError = false;
            let productIndex = 0;
            const selectedRows = [];
            
            $('tr[data-order-detail-id]').each(function() {
                const $row = $(this);
                const isChecked = $row.find('.return-checkbox').is(':checked');
                
                if (isChecked) {
                    // Validate checked items
                    const qty = parseInt($row.find('.return-quantity').val()) || 0;
                    const maxQty = parseInt($row.find('.return-quantity').data('max')) || 0;
                    
                    if (qty <= 0 || qty > maxQty) {
                        hasError = true;
                        return false;
                    }
                    
                    selectedRows.push($row);
                } else {
                    // Remove unchecked rows from form submission
                    $row.find('input[name*="products["], select[name*="products["]').each(function() {
                        $(this).removeAttr('name');
                    });
                }
            });
            
            if (hasError) {
                e.preventDefault();
                alert('Please check return quantities. They must be greater than 0 and not exceed available quantity.');
                return false;
            }
            
            // Reindex selected items to sequential array
            selectedRows.forEach(function($row, index) {
                $row.find('input[name*="products["], select[name*="products["]').each(function() {
                    const name = $(this).attr('name');
                    if (name) {
                        $(this).attr('name', name.replace(/products\[\d+\]/, `products[${index}]`));
                    }
                });
            });
        });
    });
})(jQuery);
</script>
@endsection
