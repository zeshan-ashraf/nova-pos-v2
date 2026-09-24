@extends('dashboard.body.main')

@section('specificpagestyles')
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<style>
    .return-form-container { background: #fff; padding: 30px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,.1); }
    .return-header { border-bottom: 2px solid #e9ecef; padding-bottom: 20px; margin-bottom: 30px; }
    .return-header h4 { margin: 0; color: #333; }
    .return-summary { margin-top: 30px; padding-top: 20px; border-top: 2px solid #e9ecef; }
    #purchaseDetailsTable { display: none; }
</style>
@endsection

@section('container')
<div class="container-fluid">
    <div class="row">
        <div class="col-lg-12">
            @if ($errors->any())
                <div class="alert text-white bg-danger" role="alert">
                    <div class="iq-alert-text">
                        <ul class="mb-0">
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                </div>
            @endif

            <div class="return-form-container">
                <div class="return-header">
                    <h4>Create Purchase Return Request</h4>
                </div>

                <form id="purchaseReturnForm" method="POST" action="{{ route('purchase-returns.store') }}">
                    @csrf
                    <input type="hidden" name="purchase_id" id="purchase_id">

                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="purchase_select">Purchase Invoice <span class="text-danger">*</span></label>
                            <select class="form-control" id="purchase_select" required>
                                <option value="">Select Purchase</option>
                                @foreach($purchases as $purchase)
                                    <option value="{{ $purchase->id }}"
                                        data-purchase-no="{{ $purchase->purchase_no }}"
                                        data-purchase-date="{{ $purchase->purchase_date }}"
                                        data-total="{{ $purchase->total }}">
                                        {{ $purchase->purchase_no }} - {{ $purchase->purchase_date }} (Total: {{ number_format($purchase->total, 2) }})
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="return_date">Return Date & Time <span class="text-danger">*</span></label>
                            <input type="datetime-local" class="form-control" id="return_date" name="return_date" required>
                        </div>
                    </div>

                    <div id="purchaseInfo" class="alert alert-light border" style="display:none;">
                        <div class="row">
                            <div class="col-md-3"><strong>Purchase No:</strong> <span id="info_purchase_no">-</span></div>
                            <div class="col-md-3"><strong>Purchase Date:</strong> <span id="info_purchase_date">-</span></div>
                            <div class="col-md-3"><strong>Total:</strong> <span id="info_total">-</span></div>
                            <div class="col-md-3"><strong>Mother Sale:</strong> <span id="info_source_sale">-</span></div>
                        </div>
                    </div>

                    <div id="purchaseDetailsTable" class="table-responsive">
                        <h6>Select Items to Return:</h6>
                        <table class="table">
                            <thead class="bg-white text-uppercase">
                                <tr>
                                    <th>Select</th>
                                    <th>Product</th>
                                    <th>Code</th>
                                    <th>Purchased Qty</th>
                                    <th>Return Qty</th>
                                    <th>Price</th>
                                    <th>Total</th>
                                </tr>
                            </thead>
                            <tbody id="detailsBody"></tbody>
                        </table>
                    </div>

                    <div class="return-summary" id="summaryBox" style="display:none;">
                        <div class="row">
                            <div class="col-md-6 offset-md-6">
                                <div class="d-flex justify-content-between py-2">
                                    <strong>Subtotal:</strong> <span id="subtotal">0.00</span>
                                </div>
                                <div class="d-flex justify-content-between py-2">
                                    <strong>Total:</strong> <span id="total">0.00</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="mt-4">
                        <button type="submit" id="submitBtn" class="btn btn-primary btn-lg" disabled>
                            <i class="ri-arrow-go-back-line"></i> Submit Return Request
                        </button>
                        <a href="{{ route('purchase-returns.index') }}" class="btn btn-secondary btn-lg">Cancel</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection

@section('specificpagescripts')
<script>
(function($){
    'use strict';

    $(function(){
        const now = new Date();
        const formatted = `${now.getFullYear()}-${String(now.getMonth()+1).padStart(2,'0')}-${String(now.getDate()).padStart(2,'0')}T${String(now.getHours()).padStart(2,'0')}:${String(now.getMinutes()).padStart(2,'0')}`;
        $('#return_date').val(formatted);

        $('#purchase_select').on('change', function() {
            const purchaseId = $(this).val();
            const opt = $(this).find('option:selected');

            $('#purchase_id').val(purchaseId || '');
            $('#detailsBody').html('');
            $('#purchaseDetailsTable, #summaryBox').hide();
            $('#submitBtn').prop('disabled', true);
            if (!purchaseId) {
                $('#purchaseInfo').hide();
                return;
            }

            $('#info_purchase_no').text(opt.data('purchase-no') || '-');
            $('#info_purchase_date').text(opt.data('purchase-date') || '-');
            $('#info_total').text(parseFloat(opt.data('total') || 0).toFixed(2));
            $('#info_source_sale').text('-');
            $('#purchaseInfo').show();

            $.ajax({
                url: `/purchase-returns/purchase/${purchaseId}/details`,
                method: 'GET',
                success: function(res) {
                    if (res.purchase && res.purchase.source_sale_invoice) {
                        $('#info_source_sale').text(res.purchase.source_sale_invoice);
                    }

                    let html = '';
                    if (res.details && res.details.length) {
                        res.details.forEach((d, i) => {
                            const unit = d.unit || 'piece';
                            const step = d.quantity_step || (unit === 'kg' ? '0.001' : '1');
                            const minWhenChecked = d.quantity_min || (unit === 'kg' ? '0.001' : '1');
                            const available = d.available_to_return ?? d.quantity;
                            const purchasedDisplay = d.quantity_with_unit || (d.quantity + ' ' + (unit === 'kg' ? 'kg' : 'pieces'));
                            html += `<tr data-qty-min="${minWhenChecked}">
                                <td>
                                    <input type="checkbox" class="ret-check" data-index="${i}">
                                    <input type="hidden" name="items[${i}][product_id]" value="${d.product_id}">
                                </td>
                                <td>${d.product_name}</td>
                                <td>${d.product_code}</td>
                                <td>${purchasedDisplay}</td>
                                <td>
                                    <input type="number" class="form-control ret-qty" data-index="${i}" data-max="${available}" data-unit="${unit}" min="0" max="${available}" step="${step}" value="0" disabled>
                                </td>
                                <td>
                                    <input type="number" step="0.01" class="form-control ret-price" name="items[${i}][price]" value="${parseFloat(d.unit_price).toFixed(2)}" readonly>
                                </td>
                                <td>
                                    <span class="ret-total-text">0.00</span>
                                    <input type="hidden" class="ret-total" name="items[${i}][total]" value="0">
                                    <input type="hidden" class="ret-qty-hidden" name="items[${i}][quantity]" value="0">
                                </td>
                            </tr>`;
                        });
                    } else {
                        html = '<tr><td colspan="7" class="text-center text-muted">No items found in this purchase.</td></tr>';
                    }

                    $('#detailsBody').html(html);
                    $('#purchaseDetailsTable').show();
                },
                error: function() {
                    alert('Failed to load purchase details.');
                }
            });
        });

        function parseReturnQty(value) {
            const n = parseFloat(value);
            return Number.isFinite(n) ? n : 0;
        }

        $(document).on('change', '.ret-check', function(){
            const $row = $(this).closest('tr');
            const $qty = $row.find('.ret-qty');
            if ($(this).is(':checked')) {
                const minQty = parseReturnQty($row.data('qty-min')) || 1;
                const maxQty = parseReturnQty($qty.data('max'));
                const defaultQty = maxQty < minQty ? maxQty : minQty;
                $qty.prop('disabled', false).attr('min', minQty).val(defaultQty).trigger('input');
            } else {
                $qty.prop('disabled', true).attr('min', 0).val(0).trigger('input');
            }
        });

        $(document).on('input', '.ret-qty', function(){
            const $row = $(this).closest('tr');
            const max = parseReturnQty($(this).data('max'));
            let qty = parseReturnQty($(this).val());
            if (qty > max) { qty = max; $(this).val(max); }
            if (qty < 0) { qty = 0; $(this).val(0); }

            const price = parseFloat($row.find('.ret-price').val() || 0);
            const lineTotal = Math.max(0, qty * price);
            $row.find('.ret-total-text').text(lineTotal.toFixed(2));
            $row.find('.ret-total').val(lineTotal.toFixed(2));
            $row.find('.ret-qty-hidden').val(qty);

            recalcSummary();
        });

        function recalcSummary() {
            let subtotal = 0;
            $('.ret-total').each(function(){
                subtotal += parseFloat($(this).val() || 0);
            });
            $('#subtotal').text(subtotal.toFixed(2));
            $('#total').text(subtotal.toFixed(2));

            const hasChecked = $('.ret-check:checked').length > 0;
            $('#summaryBox').toggle(hasChecked);
            $('#submitBtn').prop('disabled', !hasChecked);
        }

        // Remove unchecked rows from submission so browser/backend validate only selected items.
        $('#purchaseReturnForm').on('submit', function(e) {
            const hasChecked = $('.ret-check:checked').length > 0;
            if (!hasChecked) {
                e.preventDefault();
                alert('Please select at least one item to return.');
                return false;
            }

            const selectedRows = [];
            let hasError = false;

            $('#detailsBody tr').each(function() {
                const $row = $(this);
                const isChecked = $row.find('.ret-check').is(':checked');
                const qty = parseReturnQty($row.find('.ret-qty-hidden').val());
                const max = parseReturnQty($row.find('.ret-qty').data('max'));

                if (isChecked) {
                    if (qty <= 0 || qty > max) {
                        hasError = true;
                        return false;
                    }
                    selectedRows.push($row);
                } else {
                    $row.find('input[name*="items["], select[name*="items["]').each(function() {
                        $(this).removeAttr('name');
                    });
                }
            });

            if (hasError) {
                e.preventDefault();
                alert('Please check return quantities. They must be greater than 0 and not exceed purchased quantity.');
                return false;
            }

            // Reindex selected rows to keep items array compact.
            selectedRows.forEach(function($row, index) {
                $row.find('input[name*="items["], select[name*="items["]').each(function() {
                    const name = $(this).attr('name');
                    if (name) {
                        $(this).attr('name', name.replace(/items\[\d+\]/, `items[${index}]`));
                    }
                });
            });
        });
    });
})(jQuery);
</script>
@endsection

