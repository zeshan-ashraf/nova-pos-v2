@extends('dashboard.body.main')

@section('specificpagestyles')
<style>
    /* Invoice detail modal content (same as orders/details page) */
    #invoiceDetailModalBody .invoice-header { border-bottom: 2px solid #e9ecef; padding-bottom: 20px; margin-bottom: 20px; }
    #invoiceDetailModalBody .invoice-header h4 { margin: 0; color: #333; }
    #invoiceDetailModalBody .form-row-invoice { margin-bottom: 20px; }
    #invoiceDetailModalBody .product-table-wrapper { margin: 20px 0; }
    #invoiceDetailModalBody .product-table { width: 100%; border-collapse: collapse; table-layout: fixed; }
    #invoiceDetailModalBody .product-table thead { background-color: #f8f9fa; }
    #invoiceDetailModalBody .product-table th,
    #invoiceDetailModalBody .product-table td { padding: 10px; border: 1px solid #dee2e6; text-align: left; }
    #invoiceDetailModalBody .product-table th { font-weight: 600; color: #495057; }
    #invoiceDetailModalBody .product-table .product-name-col { width: 25%; }
    #invoiceDetailModalBody .product-table .product-code-col { width: 10%; }
    #invoiceDetailModalBody .product-table .quantity-col { width: 10%; }
    #invoiceDetailModalBody .product-table .unit-price-col { width: 10%; }
    #invoiceDetailModalBody .product-table .total-col { width: 12%; }
    #invoiceDetailModalBody .invoice-summary { margin-top: 20px; padding-top: 15px; border-top: 2px solid #e9ecef; }
    #invoiceDetailModalBody .summary-row { display: flex; justify-content: space-between; padding: 8px 0; font-size: 15px; }
    #invoiceDetailModalBody .summary-row.total { font-size: 18px; font-weight: bold; color: #28a745; border-top: 2px solid #28a745; padding-top: 12px; margin-top: 8px; }
    #invoiceDetailModalBody .summary-label { font-weight: 600; color: #495057; }
    #invoiceDetailModalBody .readonly-field { background-color: #f8f9fa; border: 1px solid #dee2e6; padding: 8px 12px; border-radius: 4px; color: #495057; }
    #invoiceDetailModalBody .customer-info-item { margin-bottom: 6px; }
    #invoiceDetailModalBody .balance-info-item { margin-bottom: 6px; }
    /* Payment detail modal */
    #paymentDetailModalBody .readonly-field { background-color: #f8f9fa; border: 1px solid #dee2e6; padding: 8px 12px; border-radius: 4px; color: #495057; }
</style>
@endsection

@section('container')
@php
    $fmt = function ($n) {
        $n = (float) $n;
        return number_format($n, 2);
    };
    $fmtBalance = function ($n) {
        $n = (float) $n;
        if ($n < 0) return '(' . number_format(abs($n), 2) . ')';
        return number_format($n, 2);
    };
@endphp
<div class="container-fluid mb-3 customer-ledger-page" style="padding-top: 0;">
    <!-- Centered header for print only -->
    <div class="ledger-print-header text-center" style="display: none;">
        <h4 class="mb-1">Customer Ledger</h4>
        <h2 class="mb-1">{{ $customer->shopname ?? $customer->name }}</h2>
        <p class="mb-0 text-muted">{{ $customer->phone ?? '—' }}</p>
    </div>
    <div class="row mb-3 ledger-screen-header">
        <div class="col-lg-8">
            <h4 class="mb-1">Customer Ledger</h4>
            <p class="mb-0 text-muted">{{ $customer->shopname ?? $customer->name }} ({{ $customer->phone ?? '—' }})</p>
        </div>
        <div class="col-lg-4 text-right">
            <a href="{{ route('customer-payments.create', ['customer_id' => $customer->id]) }}" class="btn btn-success btn-sm mr-2">Record Payment</a>
            <a href="{{ route('customers.show', $customer->id) }}" class="btn btn-secondary btn-sm">Back to Profile</a>
        </div>
    </div>

    <!-- Date Filter -->
    <div class="border rounded p-3 bg-white mb-3">
        <form action="{{ route('customers.ledger', $customer->id) }}" method="GET" id="dateFilterForm">
            <div class="row align-items-end">
                <div class="col-md-3">
                    <label for="date_filter" class="form-label">Date Filter</label>
                    <select class="form-control form-control-sm" name="date_filter" id="date_filter" onchange="toggleCustomDates()">
                        <option value="all" {{ ($date_filter ?? '') == 'all' ? 'selected' : '' }}>All</option>
                        <option value="today" {{ ($date_filter ?? 'today') == 'today' ? 'selected' : '' }}>Today</option>
                        <option value="yesterday" {{ ($date_filter ?? '') == 'yesterday' ? 'selected' : '' }}>Yesterday</option>
                        <option value="last_7_days" {{ ($date_filter ?? '') == 'last_7_days' ? 'selected' : '' }}>Last 7 Days</option>
                        <option value="current_month" {{ ($date_filter ?? '') == 'current_month' ? 'selected' : '' }}>Current Month</option>
                        <option value="last_30_days" {{ ($date_filter ?? '') == 'last_30_days' ? 'selected' : '' }}>Last 30 Days</option>
                        <option value="custom" {{ ($date_filter ?? '') == 'custom' ? 'selected' : '' }}>Custom Range</option>
                    </select>
                </div>
                <div class="col-md-3" id="start_date_group" style="display: {{ ($date_filter ?? '') == 'custom' ? 'block' : 'none' }};">
                    <label for="start_date" class="form-label">From Date</label>
                    <input type="date" class="form-control form-control-sm" name="start_date" id="start_date" value="{{ $start_date ?? '' }}">
                </div>
                <div class="col-md-3" id="end_date_group" style="display: {{ ($date_filter ?? '') == 'custom' ? 'block' : 'none' }};">
                    <label for="end_date" class="form-label">To Date</label>
                    <input type="date" class="form-control form-control-sm" name="end_date" id="end_date" value="{{ $end_date ?? '' }}">
                </div>
                <div class="col-md-3">
                    <button type="submit" class="btn btn-primary btn-sm">Filter</button>
                    <button type="button" class="btn btn-outline-secondary btn-sm ml-2" onclick="printLedgerFullWidth()">Print</button>
                    <a href="{{ route('customers.ledgerPdf', ['customer' => $customer->id, 'date_filter' => $date_filter ?? '', 'start_date' => $start_date ?? '', 'end_date' => $end_date ?? '']) }}" class="btn btn-outline-danger btn-sm ml-2" target="_blank">PDF</a>
                </div>
            </div>
        </form>
    </div>

    <!-- Printable content (summary + table) for popup -->
    @php
        $fmtAmt = function ($n) {
            $n = (float) $n;
            if ($n < 0) {
                return '(' . number_format(abs($n), 2) . ')';
            }
            return number_format($n, 2);
        };
        $showAdvance = function ($n) {
            return (float) $n < 0;
        };
    @endphp
    <div id="ledgerPrintContent">
    <div class="row mb-3 ledger-summary-row">
        <div class="col-md-3">
            <div class="card border shadow-none">
                <div class="card-body py-3">
                    <div class="text-muted small">Opening Balance</div>
                    <div class="text-right font-weight-bold mt-1 {{ $showAdvance($opening_balance ?? 0) ? 'text-danger' : '' }}" style="font-size: 1.1rem;">
                        {{ $fmtAmt($opening_balance ?? 0) }}
                        @if($showAdvance($opening_balance ?? 0)) <span class="small">(Advance)</span> @endif
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border shadow-none">
                <div class="card-body py-3">
                    <div class="text-muted small">Total Debits</div>
                    <div class="text-right">{{ $fmtAmt($total_debits ?? 0) }}</div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border shadow-none">
                <div class="card-body py-3">
                    <div class="text-muted small">Total Credits</div>
                    <div class="text-right">{{ $fmtAmt($total_credits ?? 0) }}</div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border shadow-none">
                <div class="card-body py-3">
                    <div class="text-muted small">Closing Balance</div>
                    <div class="text-right font-weight-bold {{ $showAdvance($closing_balance ?? 0) ? 'text-danger' : '' }}">
                        {{ $fmtAmt($closing_balance ?? 0) }}
                        @if($showAdvance($closing_balance ?? 0)) <span class="small">(Advance)</span> @endif
                    </div>
                </div>
            </div>
        </div>
    </div>
    @if(isset($start_date) && isset($end_date) && $start_date && $end_date)
    <div class="row mb-2">
        <div class="col-12 text-right text-muted small">
            Period: {{ \Carbon\Carbon::parse($start_date)->format('M d, Y') }} – {{ \Carbon\Carbon::parse($end_date)->format('M d, Y') }}
        </div>
    </div>
    @endif

    <!-- Ledger Table -->
    <div class="border rounded bg-white ledger-table-wrapper">
        <div class="table-responsive">
            <table class="table table-sm mb-0" id="ledgerTable">
                <colgroup>
                    <col class="ledger-col-date">
                    <col class="ledger-col-type">
                    <col class="ledger-col-ref">
                    <col class="ledger-col-desc">
                    <col class="ledger-col-debit">
                    <col class="ledger-col-credit">
                    <col class="ledger-col-balance">
                </colgroup>
                <thead class="thead-light">
                    <tr>
                        <th>Date</th>
                        <th>Type</th>
                        <th>Reference</th>
                        <th>Description</th>
                        <th class="text-right">Debit</th>
                        <th class="text-right">Credit</th>
                        <th class="text-right">Balance</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($transactions ?? [] as $txn)
                        <tr>
                            <td>{{ $txn['date'] }}</td>
                            <td>
                                @if(!empty($txn['is_opening']))
                                    Opening Balance
                                @elseif(!empty($txn['order_id']))
                                    Sale
                                @elseif(!empty($txn['payment_transaction_id']))
                                    Payment
                                @else
                                    —
                                @endif
                            </td>
                            <td>
                                @if(!empty($txn['order_id']))
                                    <a href="javascript:void(0)" class="ledger-invoice-link text-primary" data-order-id="{{ $txn['order_id'] }}" title="View invoice details">{{ $txn['reference'] }}</a>
                                @elseif(!empty($txn['payment_transaction_id']))
                                    <a href="javascript:void(0)" class="ledger-payment-link text-primary" data-transaction-id="{{ $txn['payment_transaction_id'] }}" title="View payment details">{{ $txn['reference'] }}</a>
                                @else
                                    {{ $txn['reference'] }}
                                @endif
                            </td>
                            <td>{{ $txn['description'] }}</td>
                            <td class="text-right">{{ $txn['debit'] > 0 ? $fmt($txn['debit']) : '—' }}</td>
                            <td class="text-right">{{ $txn['credit'] > 0 ? $fmt($txn['credit']) : '—' }}</td>
                            <td class="text-right">{{ $fmtBalance($txn['balance']) }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="text-center text-muted py-4">No transactions for the selected period.</td>
                        </tr>
                    @endforelse
                </tbody>
                @if(!empty($transactions) && count($transactions) > 0)
                <tfoot class="bg-light">
                    <tr>
                        <td colspan="6" class="text-right"><strong>Closing Balance</strong></td>
                        <td class="text-right"><strong>{{ $fmtBalance($closing_balance ?? 0) }}</strong> @if((float)($closing_balance ?? 0) < 0)<span class="small text-danger">(Advance)</span>@endif</td>
                    </tr>
                </tfoot>
                @endif
            </table>
        </div>
    </div>
    </div><!-- /#ledgerPrintContent -->
</div>

<!-- Invoice Detail Modal (uses same content as orders/details page) -->
<div class="modal fade" id="invoiceDetailModal" tabindex="-1" role="dialog" aria-labelledby="invoiceDetailModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="invoiceDetailModalLabel">Invoice Details</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body" id="invoiceDetailModalBody">
                <div class="text-center py-5 text-muted">
                    <span class="spinner-border spinner-border-sm mr-2" role="status"></span> Loading...
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Payment Detail Modal -->
<div class="modal fade" id="paymentDetailModal" tabindex="-1" role="dialog" aria-labelledby="paymentDetailModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="paymentDetailModalLabel">Payment Details</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body" id="paymentDetailModalBody">
                <div class="text-center py-5 text-muted">
                    <span class="spinner-border spinner-border-sm mr-2" role="status"></span> Loading...
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function printLedgerFullWidth() {
    var printHeader = document.querySelector('.ledger-print-header');
    var title = 'Customer Ledger';
    var customerName = '';
    var phone = '';
    if (printHeader) {
        var h4 = printHeader.querySelector('h4');
        var h2 = printHeader.querySelector('h2');
        var paras = printHeader.querySelectorAll('p');
        if (h4) title = h4.textContent.trim();
        if (h2) customerName = h2.textContent.trim();
        if (paras.length) phone = paras[0].textContent.trim();
    }
    var contentEl = document.getElementById('ledgerPrintContent');
    var content = contentEl ? contentEl.innerHTML : '';
    var styles = '*,*:before,*:after{box-sizing:border-box;}body{margin:0;padding:12px;font-family:Arial,sans-serif;font-size:14px;width:100%;max-width:100%;}' +
        '.print-header{text-align:center;margin-bottom:16px;}.print-header h4{margin:0 0 6px 0;font-size:1.25rem;}.print-header h2{margin:0 0 4px 0;font-size:1.5rem;}.print-header p{margin:0;color:#6c757d;}' +
        '.row{display:flex;flex-wrap:nowrap;margin-bottom:12px;}.row .col-md-3{flex:0 0 25%;max-width:25%;padding:0 6px;}' +
        '.card{border:1px solid #dee2e6;border-radius:4px;}.card-body{padding:12px;}.text-right{text-align:right;}.font-weight-bold{font-weight:700;}.text-muted{color:#6c757d;}.text-danger{color:#dc3545;}.small{font-size:0.875em;}' +
        '.table-wrap{width:100%;overflow:visible;margin-top:12px;}.ledger-table-wrapper,.table-responsive{width:100%!important;max-width:100%!important;}' +
        'table{width:100%!important;min-width:100%!important;border-collapse:collapse;table-layout:fixed;}th,td{border:1px solid #dee2e6;padding:8px;text-align:left;}th{background:#f8f9fa;font-weight:600;}tfoot td{font-weight:700;}' +
        '@media print{body{padding:6px;width:100%;}.table-wrap,.ledger-table-wrapper,.table-responsive,table{width:100%!important;max-width:100%!important;}@page{margin:0;size:auto;}body{margin:0!important;}}';
    var headerHtml = '<div class="print-header"><h4>' + (title || 'Customer Ledger') + '</h4><h2>' + customerName + '</h2><p>' + phone + '</p></div>';
    var html = '<!DOCTYPE html><html><head><meta charset="utf-8"><title></title><style>' + styles + '</style></head><body>' + headerHtml + '<div class="table-wrap">' + content + '</div></body></html>';
    var w = window.open('', '_blank', 'width=900,height=700,scrollbars=yes');
    if (!w) { alert('Please allow popups to print.'); return; }
    w.document.write(html);
    w.document.close();
    w.focus();
    setTimeout(function() { w.print(); }, 300);
}
function openLedgerPrintPreview() {
    var titleEl = document.querySelector('.customer-ledger-page h4.mb-1');
    var customerEl = document.querySelector('.customer-ledger-page .row.mb-3 p.mb-0.text-muted');
    var title = titleEl ? titleEl.textContent.trim() : 'Customer Ledger';
    var customer = customerEl ? customerEl.textContent.trim() : '';
    var contentEl = document.getElementById('ledgerPrintContent');
    var content = contentEl ? contentEl.innerHTML : '';
    var html = '<!DOCTYPE html><html><head><meta charset="utf-8"><title>' + title + '</title><style>' +
        'body{font-family:Arial,sans-serif;font-size:14px;margin:20px;padding:0;}' +
        '.row{display:flex;flex-wrap:wrap;margin-bottom:1rem;}' +
        '.row .col-md-3{flex:0 0 25%;max-width:25%;padding:0 8px;box-sizing:border-box;}' +
        '.card{border:1px solid #dee2e6;border-radius:4px;margin-bottom:0;}' +
        '.card-body{padding:12px 15px;}' +
        '.text-muted{color:#6c757d;}.text-right{text-align:right;}.font-weight-bold{font-weight:700;}' +
        'table{width:100%;border-collapse:collapse;margin-top:1rem;}' +
        'th,td{border:1px solid #dee2e6;padding:8px;text-align:left;}' +
        'th{background:#f8f9fa;font-weight:600;}.text-danger{color:#dc3545;}' +
        'tfoot td{font-weight:700;}' +
        '</style></head><body>' +
        '<h4 style="margin:0 0 4px 0;">' + title + '</h4><p style="margin:0 0 16px 0;color:#6c757d;">' + customer + '</p>' +
        content +
        '<p style="margin-top:16px;"><button onclick="window.print()">Print</button> <button onclick="window.close()">Close</button></p>' +
        '</body></html>';
    var w = window.open('', '_blank', 'width=1000,height=700,scrollbars=yes,resizable=yes');
    if (w) {
        w.document.write(html);
        w.document.close();
    } else {
        alert('Please allow popups to view the print preview.');
    }
}
function toggleCustomDates() {
    var f = document.getElementById('date_filter').value;
    document.getElementById('start_date_group').style.display = f === 'custom' ? 'block' : 'none';
    document.getElementById('end_date_group').style.display = f === 'custom' ? 'block' : 'none';
}

// Ledger: click invoice/payment reference to show detail in modal (vanilla JS - no jQuery)
document.addEventListener('DOMContentLoaded', function() {
    var invoiceModalEl = document.getElementById('invoiceDetailModal');
    var paymentModalEl = document.getElementById('paymentDetailModal');

    // Click on invoice reference: load and show invoice modal
    document.addEventListener('click', function(e) {
        var link = e.target.closest('.ledger-invoice-link');
        if (!link) return;
        e.preventDefault();
        var orderId = link.getAttribute('data-order-id');
        if (!orderId || !invoiceModalEl) return;
        var bodyEl = document.getElementById('invoiceDetailModalBody');
        if (!bodyEl) return;

        bodyEl.innerHTML = '<div class="text-center py-5 text-muted"><span class="spinner-border spinner-border-sm mr-2" role="status"></span> Loading...</div>';
        showModal(invoiceModalEl);

        fetch("{{ url('orders/details') }}/" + orderId + "/content", { headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' } })
            .then(function(res) { return res.json(); })
            .then(function(data) {
                if (data && data.html) {
                    bodyEl.innerHTML = data.html;
                } else {
                    bodyEl.innerHTML = '<div class="alert alert-danger">Failed to load invoice details.</div>';
                }
            })
            .catch(function() {
                bodyEl.innerHTML = '<div class="alert alert-danger">Failed to load invoice details.</div>';
            });
    });

    // Click on payment reference: load and show payment modal
    document.addEventListener('click', function(e) {
        var link = e.target.closest('.ledger-payment-link');
        if (!link) return;
        e.preventDefault();
        var transactionId = link.getAttribute('data-transaction-id');
        if (!transactionId || !paymentModalEl) return;
        var bodyEl = document.getElementById('paymentDetailModalBody');
        if (!bodyEl) return;

        bodyEl.innerHTML = '<div class="text-center py-5 text-muted"><span class="spinner-border spinner-border-sm mr-2" role="status"></span> Loading...</div>';
        showModal(paymentModalEl);

        fetch("{{ url('customer-payments') }}/" + transactionId + "/content", { headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' } })
            .then(function(res) { return res.json(); })
            .then(function(data) {
                if (data && data.html) {
                    bodyEl.innerHTML = data.html;
                } else {
                    bodyEl.innerHTML = '<div class="alert alert-danger">Failed to load payment details.</div>';
                }
            })
            .catch(function() {
                bodyEl.innerHTML = '<div class="alert alert-danger">Failed to load payment details.</div>';
            });
    });

    // Invoice modal: close button (X) and [data-dismiss="modal"]
    if (invoiceModalEl) {
        var invoiceCloseBtn = invoiceModalEl.querySelector('.modal-header .close, .modal-header [data-dismiss="modal"]');
        if (invoiceCloseBtn) {
            invoiceCloseBtn.addEventListener('click', function() { closeModal(invoiceModalEl); });
        }
        invoiceModalEl.addEventListener('click', function(e) {
            if (e.target.closest('[data-dismiss="modal"]')) {
                e.preventDefault();
                closeModal(invoiceModalEl);
            }
        });
    }
    // Payment modal: close button (X) and [data-dismiss="modal"]
    if (paymentModalEl) {
        var paymentCloseBtn = paymentModalEl.querySelector('.modal-header .close, .modal-header [data-dismiss="modal"]');
        if (paymentCloseBtn) {
            paymentCloseBtn.addEventListener('click', function() { closeModal(paymentModalEl); });
        }
        paymentModalEl.addEventListener('click', function(e) {
            if (e.target.closest('[data-dismiss="modal"]')) {
                e.preventDefault();
                closeModal(paymentModalEl);
            }
        });
    }
});

function showModal(modalEl) {
    if (typeof bootstrap !== 'undefined' && bootstrap.Modal) {
        var m = new bootstrap.Modal(modalEl);
        m.show();
    } else {
        modalEl.classList.add('show');
        modalEl.style.display = 'block';
        document.body.classList.add('modal-open');
        var backdrop = document.createElement('div');
        backdrop.className = 'modal-backdrop fade show';
        backdrop.setAttribute('data-dismiss', 'modal');
        document.body.appendChild(backdrop);
        backdrop.addEventListener('click', function() { closeModal(modalEl); });
    }
}

function closeModal(modalEl) {
    if (typeof bootstrap !== 'undefined' && bootstrap.Modal) {
        var m = bootstrap.Modal.getInstance(modalEl);
        if (m) m.hide();
    } else {
        modalEl.classList.remove('show');
        modalEl.style.display = 'none';
        document.body.classList.remove('modal-open');
        var backdrops = document.querySelectorAll('.modal-backdrop');
        backdrops.forEach(function(b) { b.remove(); });
    }
}
</script>
<style>
/* Ledger table full width */
#ledgerTable { width: 100%; }
@media print {
    .btn, #dateFilterForm, .border.rounded.p-3 { display: none !important; }
    /* Centered heading and customer info (show only in print) */
    .ledger-print-header { display: block !important; margin-bottom: 1rem !important; }
    .ledger-screen-header { display: none !important; }
    /* Keep 4 summary boxes in one row */
    .ledger-summary-row { display: flex !important; flex-wrap: nowrap !important; }
    .ledger-summary-row > [class*="col-"] { flex: 0 0 25% !important; max-width: 25% !important; }
    /* Full width for print - entire page content full-bleed */
    html, body { margin: 0 !important; padding: 0 !important; width: 100% !important; }
    body .wrapper { width: 100vw !important; max-width: none !important; padding: 0 !important; margin: 0 !important; }
    body .content-page { width: 100vw !important; max-width: none !important; padding: 0 12px !important; margin: 0 !important; box-sizing: border-box !important; }
    .customer-ledger-page.container-fluid { width: 100% !important; max-width: none !important; padding-left: 0 !important; padding-right: 0 !important; }
    .customer-ledger-page .row { width: 100% !important; max-width: none !important; margin-left: 0 !important; margin-right: 0 !important; }
    .customer-ledger-page .row > [class*="col-"] { width: 100% !important; max-width: none !important; padding-left: 0 !important; padding-right: 0 !important; }
    /* Full-bleed table: force table area to full page width */
    .ledger-table-wrapper { width: 100vw !important; max-width: 100vw !important; position: relative !important; left: 50% !important; margin-left: -50vw !important; padding-left: 10px !important; padding-right: 10px !important; box-sizing: border-box !important; }
    .ledger-table-wrapper .table-responsive { width: 100% !important; max-width: none !important; overflow: visible !important; }
    #ledgerTable { width: 100% !important; min-width: 100% !important; table-layout: fixed !important; box-sizing: border-box !important; }
    /* Column widths so table fills full width in print (percentages of table) */
    #ledgerTable .ledger-col-date { width: 12%; }
    #ledgerTable .ledger-col-type { width: 10%; }
    #ledgerTable .ledger-col-ref { width: 16%; }
    #ledgerTable .ledger-col-desc { width: 28%; }
    #ledgerTable .ledger-col-debit { width: 12%; }
    #ledgerTable .ledger-col-credit { width: 12%; }
    #ledgerTable .ledger-col-balance { width: 10%; }
}
</style>
@endsection
