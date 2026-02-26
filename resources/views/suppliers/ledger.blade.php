@extends('dashboard.body.main')

@section('specificpagestyles')
<style>
    #paymentDetailModalBody .readonly-field { background-color: #f8f9fa; border: 1px solid #dee2e6; padding: 8px 12px; border-radius: 4px; color: #495057; }
    /* Page and table full width (like customer ledger) */
    .supplier-ledger-page.container-fluid { width: 100%; max-width: 100%; padding-left: 15px; padding-right: 15px; box-sizing: border-box; }
    .supplier-ledger-page .row { width: 100%; max-width: 100%; }
    /* Top 4 boxes + summary in one row; table full width */
    .supplier-ledger-page .ledger-summary-boxes { display: flex; flex-wrap: nowrap; }
    .supplier-ledger-page .ledger-summary-boxes .col-md-3 { flex: 0 0 25%; max-width: 25%; }
    .supplier-ledger-page .ledger-summary-row { display: flex; flex-wrap: nowrap; }
    .supplier-ledger-page .ledger-summary-row .col-md-3 { flex: 0 0 25%; max-width: 25%; }
    /* Full-width ledger table (same approach as customer ledger) */
    .supplier-ledger-page .ledger-table-wrapper { width: 100%; max-width: 100%; box-sizing: border-box; }
    .supplier-ledger-page .ledger-table-wrapper .table-responsive { width: 100%; max-width: 100%; overflow-x: auto; }
    .supplier-ledger-page #ledgerTable { width: 100%; min-width: 100%; table-layout: fixed; box-sizing: border-box; }
    .supplier-ledger-page #ledgerTable th,
    .supplier-ledger-page #ledgerTable td { word-wrap: break-word; }
    .supplier-ledger-page #ledgerTable .ledger-col-date { width: 9%; }
    .supplier-ledger-page #ledgerTable .ledger-col-type { width: 10%; }
    .supplier-ledger-page #ledgerTable .ledger-col-purchase { width: 12%; }
    .supplier-ledger-page #ledgerTable .ledger-col-desc { width: 22%; }
    .supplier-ledger-page #ledgerTable .ledger-col-total { width: 9%; }
    .supplier-ledger-page #ledgerTable .ledger-col-paid { width: 9%; }
    .supplier-ledger-page #ledgerTable .ledger-col-due { width: 9%; }
    .supplier-ledger-page #ledgerTable .ledger-col-debit { width: 8%; }
    .supplier-ledger-page #ledgerTable .ledger-col-credit { width: 8%; }
    .supplier-ledger-page #ledgerTable .ledger-col-balance { width: 8%; }
    /* Print: same as HTML view – hide layout chrome, keep ledger content and styling */
    @media print {
        body, .wrapper { padding: 0 !important; margin: 0 !important; }
        .iq-sidebar, .iq-top-navbar, .content-page > .navbar, .content-page > .iq-sidebar,
        .iq-footer, .modal, .modal-backdrop, [data-dismiss="modal"], .close,
        #dateFilterCard, .supplier-ledger-page .btn,
        .supplier-ledger-page .row.mb-3 .col-lg-4 { display: none !important; }
        .wrapper { display: block !important; }
        .content-page { padding: 0 !important; margin: 0 !important; width: 100% !important; max-width: none !important; }
        .supplier-ledger-page { padding-top: 0 !important; width: 100% !important; max-width: none !important; }
        .supplier-ledger-page .row.mb-3 .col-lg-8 { flex: 0 0 100%; max-width: 100%; }
        .supplier-ledger-page .card { border: 1px solid #dee2e6 !important; box-shadow: none !important; break-inside: avoid; }
        .supplier-ledger-page .card-header { background: #f8f9fa !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        .supplier-ledger-page .ledger-table-wrapper { width: 100vw !important; max-width: 100vw !important; position: relative !important; left: 50% !important; margin-left: -50vw !important; padding-left: 10px !important; padding-right: 10px !important; box-sizing: border-box !important; }
        .supplier-ledger-page .ledger-table-wrapper .table-responsive { width: 100% !important; max-width: none !important; overflow: visible !important; }
        .supplier-ledger-page #ledgerTable { width: 100% !important; min-width: 100% !important; table-layout: fixed !important; box-sizing: border-box !important; }
        .supplier-ledger-page #ledgerTable .ledger-col-date { width: 9% !important; }
        .supplier-ledger-page #ledgerTable .ledger-col-type { width: 10% !important; }
        .supplier-ledger-page #ledgerTable .ledger-col-purchase { width: 12% !important; }
        .supplier-ledger-page #ledgerTable .ledger-col-desc { width: 22% !important; }
        .supplier-ledger-page #ledgerTable .ledger-col-total { width: 9% !important; }
        .supplier-ledger-page #ledgerTable .ledger-col-paid { width: 9% !important; }
        .supplier-ledger-page #ledgerTable .ledger-col-due { width: 9% !important; }
        .supplier-ledger-page #ledgerTable .ledger-col-debit { width: 8% !important; }
        .supplier-ledger-page #ledgerTable .ledger-col-credit { width: 8% !important; }
        .supplier-ledger-page #ledgerTable .ledger-col-balance { width: 8% !important; }
        .supplier-ledger-page .table th, .supplier-ledger-page .table td { border: 1px solid #dee2e6 !important; }
        .supplier-ledger-page .thead-light th { background: #f8f9fa !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        .supplier-ledger-page .bg-light { background: #f8f9fa !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        .supplier-ledger-page .text-danger { color: #dc3545 !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        .supplier-ledger-page .text-success { color: #28a745 !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        .supplier-ledger-page .badge { border: 1px solid transparent; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        .supplier-ledger-page a.ledger-purchase-link, .supplier-ledger-page a.ledger-payment-link { color: inherit !important; text-decoration: none !important; }
        .supplier-ledger-page .ledger-summary-boxes { display: flex !important; flex-wrap: nowrap !important; }
        .supplier-ledger-page .ledger-summary-boxes .col-md-3 { flex: 0 0 25% !important; max-width: 25% !important; }
        .supplier-ledger-page .ledger-summary-row { display: flex !important; flex-wrap: nowrap !important; }
        .supplier-ledger-page .ledger-summary-row .col-md-3 { flex: 0 0 25% !important; max-width: 25% !important; }
        /* Supplier name and phone visible in print (avoid light gray not showing) */
        .supplier-ledger-page .row.mb-3:first-of-type .col-lg-8 .text-muted { color: #212529 !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
    }
</style>
@endsection

@section('container')
<div class="container-fluid mb-3 supplier-ledger-page" style="padding-top: 90px;">
    <div class="row mb-3">
        <div class="col-lg-8">
            <h4 class="mb-1">Supplier Ledger</h4>
            <p class="mb-0 text-muted">{{ $supplier->shopname }} ({{ $supplier->phone }})</p>
        </div>
        <div class="col-lg-4 text-right">
            <a href="{{ route('supplier-payments.create', ['supplier_id' => $supplier->id]) }}" class="btn btn-success mr-2">Record Payment</a>
            <a href="{{ route('suppliers.show', $supplier->id) }}" class="btn btn-secondary">Back to Profile</a>
        </div>
    </div>

    <!-- Date Filter Section (hidden when printing to avoid empty box) -->
    <div class="card mb-3 d-print-none" id="dateFilterCard">
        <div class="card-body">
            <form action="{{ route('suppliers.ledger', $supplier->id) }}" method="GET" id="dateFilterForm">
                <div class="row align-items-end">
                    <div class="col-md-3">
                        <label for="date_filter" class="form-label">Date Filter</label>
                        <select class="form-control" name="date_filter" id="date_filter" onchange="toggleCustomDates()">
                            <option value="current_month" {{ $date_filter == 'current_month' ? 'selected' : '' }}>Current Month</option>
                            <option value="last_30_days" {{ $date_filter == 'last_30_days' ? 'selected' : '' }}>Last 30 Days</option>
                            <option value="custom" {{ $date_filter == 'custom' ? 'selected' : '' }}>Custom Range</option>
                        </select>
                    </div>
                    <div class="col-md-3" id="start_date_group" style="display: {{ $date_filter == 'custom' ? 'block' : 'none' }};">
                        <label for="start_date" class="form-label">Start Date</label>
                        <input type="date" class="form-control" name="start_date" id="start_date" value="{{ $start_date }}">
                    </div>
                    <div class="col-md-3" id="end_date_group" style="display: {{ $date_filter == 'custom' ? 'block' : 'none' }};">
                        <label for="end_date" class="form-label">End Date</label>
                        <input type="date" class="form-control" name="end_date" id="end_date" value="{{ $end_date }}">
                    </div>
                    <div class="col-md-3">
                        <button type="submit" class="btn btn-primary">Filter</button>
                        <button type="button" class="btn btn-info ml-2" onclick="window.print()">
                            <i class="ri-printer-line mr-1"></i> Print
                        </button>
                        <a href="{{ route('suppliers.ledgerPdf', ['supplier' => $supplier->id, 'date_filter' => $date_filter, 'start_date' => $start_date, 'end_date' => $end_date]) }}" class="btn btn-danger ml-2" target="_blank">
                            <i class="ri-file-pdf-line mr-1"></i> Export PDF
                        </a>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Summary Section: 4 boxes in one row -->
    <div class="row mb-3 ledger-summary-boxes">
        <div class="col-md-3">
            <div class="card">
                <div class="card-body">
                    <p class="text-muted mb-1">Opening Balance</p>
                    <h4 class="mb-0">{{ number_format($opening_balance, 2) }}</h4>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card">
                <div class="card-body">
                    <p class="text-muted mb-1">Closing Balance</p>
                    <h4 class="mb-0">{{ number_format($closing_balance, 2) }}</h4>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card">
                <div class="card-body">
                    <p class="text-muted mb-1">Total Purchases</p>
                    <h4 class="mb-0">{{ $summary['total_purchases'] }}</h4>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card">
                <div class="card-body">
                    <p class="text-muted mb-1">Total Payments</p>
                    <h4 class="mb-0">{{ $summary['total_payments'] }}</h4>
                </div>
            </div>
        </div>
    </div>

    <!-- Detailed Summary: labels in one row, values in second row -->
    <div class="row mb-3">
        <div class="col-12">
            <div class="card">
                <div class="card-body">
                    <h6 class="card-title mb-3">Summary</h6>
                    <div class="row ledger-summary-row mb-2">
                        <div class="col-md-3"><strong>Total Purchase Amount</strong></div>
                        <div class="col-md-3"><strong>Total Paid</strong></div>
                        <div class="col-md-3"><strong>Total Due</strong></div>
                        <div class="col-md-3"><strong>Total Payment Amount</strong></div>
                    </div>
                    <div class="row ledger-summary-row">
                        <div class="col-md-3">{{ number_format($summary['total_purchase_amount'], 2) }}</div>
                        <div class="col-md-3">{{ number_format($summary['total_paid'], 2) }}</div>
                        <div class="col-md-3">{{ number_format($summary['total_due'], 2) }}</div>
                        <div class="col-md-3">{{ number_format($summary['total_payment_amount'], 2) }}</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Ledger Table (full width like customer ledger) -->
    <div class="card ledger-table-wrapper">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5 class="mb-0">Transaction Ledger</h5>
            <div>
                <span class="text-muted">Period: {{ \Carbon\Carbon::parse($start_date)->format('M d, Y') }} - {{ \Carbon\Carbon::parse($end_date)->format('M d, Y') }}</span>
            </div>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table mb-0" id="ledgerTable">
                    <colgroup>
                        <col class="ledger-col-date">
                        <col class="ledger-col-type">
                        <col class="ledger-col-purchase">
                        <col class="ledger-col-desc">
                        <col class="ledger-col-total">
                        <col class="ledger-col-paid">
                        <col class="ledger-col-due">
                        <col class="ledger-col-debit">
                        <col class="ledger-col-credit">
                        <col class="ledger-col-balance">
                    </colgroup>
                    <thead class="bg-white text-uppercase">
                        <tr class="ligth ligth-data">
                            <th>Date</th>
                            <th>Type</th>
                            <th>Purchase No</th>
                            <th>Description</th>
                            <th class="text-right">Total</th>
                            <th class="text-right">Paid</th>
                            <th class="text-right">Due</th>
                            <th class="text-right">Debit</th>
                            <th class="text-right">Credit</th>
                            <th class="text-right">Balance</th>
                        </tr>
                    </thead>
                    <tbody class="ligth-body">
                        @forelse ($transactions as $transaction)
                            <tr>
                                <td>{{ \Carbon\Carbon::parse($transaction['date'])->format('Y-m-d') }}</td>
                                <td>
                                    <span class="badge {{ $transaction['type_badge'] }}">{{ $transaction['type'] }}</span>
                                </td>
                                <td>
                                    @if(!empty($transaction['is_supplier_payment']) && !empty($transaction['payment_transaction_id']))
                                        <a href="javascript:void(0)" class="ledger-payment-link text-primary" data-transaction-id="{{ $transaction['payment_transaction_id'] }}" title="View payment details">View payment</a>
                                    @elseif($transaction['purchase_id'])
                                        <a href="javascript:void(0)" class="ledger-purchase-link text-primary" data-purchase-id="{{ $transaction['purchase_id'] }}" title="View purchase invoice">{{ $transaction['purchase_no'] ?? ('Purchase #' . $transaction['purchase_id']) }}</a>
                                    @else
                                        —
                                    @endif
                                </td>
                                <td>{{ $transaction['description'] }}</td>
                                <td class="text-right">
                                    @if($transaction['is_purchase'])
                                        {{ number_format($transaction['total'], 2) }}
                                    @else
                                        —
                                    @endif
                                </td>
                                <td class="text-right">
                                    @if($transaction['is_purchase'])
                                        {{ number_format($transaction['paid'], 2) }}
                                    @else
                                        {{ number_format($transaction['paid'], 2) }}
                                    @endif
                                </td>
                                <td class="text-right">
                                    @if($transaction['is_purchase'])
                                        {{ number_format($transaction['due'], 2) }}
                                    @else
                                        —
                                    @endif
                                </td>
                                <td class="text-right">
                                    @if($transaction['debit'] > 0)
                                        <span class="text-danger">{{ number_format($transaction['debit'], 2) }}</span>
                                    @else
                                        —
                                    @endif
                                </td>
                                <td class="text-right">
                                    @if($transaction['credit'] > 0)
                                        <span class="text-success">{{ number_format($transaction['credit'], 2) }}</span>
                                    @else
                                        —
                                    @endif
                                </td>
                                <td class="text-right">
                                    <strong>{{ number_format($transaction['balance'], 2) }}</strong>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="10" class="text-center text-muted py-4">No transactions found for the selected period.</td>
                            </tr>
                        @endforelse
                    </tbody>
                    <tfoot class="bg-light">
                        <tr>
                            <td colspan="4" class="text-right"><strong>Totals:</strong></td>
                            <td class="text-right"><strong>{{ number_format($summary['total_purchase_amount'], 2) }}</strong></td>
                            <td class="text-right"><strong>{{ number_format($summary['total_paid'], 2) }}</strong></td>
                            <td class="text-right"><strong>{{ number_format($summary['total_due'], 2) }}</strong></td>
                            <td class="text-right"><strong>{{ number_format($transactions->sum('debit'), 2) }}</strong></td>
                            <td class="text-right"><strong>{{ number_format($transactions->sum('credit'), 2) }}</strong></td>
                            <td class="text-right"><strong>{{ number_format($closing_balance, 2) }}</strong></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Purchase Detail Modal (same content as purchases.show) -->
<div class="modal fade" id="purchaseDetailModal" tabindex="-1" role="dialog" aria-labelledby="purchaseDetailModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="purchaseDetailModalLabel">Purchase Details</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body" id="purchaseDetailModalBody">
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
function toggleCustomDates() {
    const dateFilter = document.getElementById('date_filter').value;
    const startDateGroup = document.getElementById('start_date_group');
    const endDateGroup = document.getElementById('end_date_group');
    
    if (dateFilter === 'custom') {
        startDateGroup.style.display = 'block';
        endDateGroup.style.display = 'block';
    } else {
        startDateGroup.style.display = 'none';
        endDateGroup.style.display = 'none';
    }
}

// Purchase detail modal (purchase link in ledger – same view as purchases.show)
document.addEventListener('DOMContentLoaded', function() {
    var purchaseModalEl = document.getElementById('purchaseDetailModal');
    document.addEventListener('click', function(e) {
        var link = e.target.closest('.ledger-purchase-link');
        if (link) {
            e.preventDefault();
            var purchaseId = link.getAttribute('data-purchase-id');
            if (!purchaseId || !purchaseModalEl) return;
            var bodyEl = document.getElementById('purchaseDetailModalBody');
            if (!bodyEl) return;
            bodyEl.innerHTML = '<div class="text-center py-5 text-muted"><span class="spinner-border spinner-border-sm mr-2" role="status"></span> Loading...</div>';
            if (typeof bootstrap !== 'undefined' && bootstrap.Modal) {
                var m = new bootstrap.Modal(purchaseModalEl);
                m.show();
            } else {
                purchaseModalEl.classList.add('show');
                purchaseModalEl.style.display = 'block';
                document.body.classList.add('modal-open');
            }
            fetch("{{ url('purchases') }}/" + purchaseId + "/content", { headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' } })
                .then(function(res) { return res.json(); })
                .then(function(data) {
                    if (data && data.html) {
                        bodyEl.innerHTML = data.html;
                    } else {
                        bodyEl.innerHTML = '<div class="alert alert-danger">Failed to load purchase details.</div>';
                    }
                })
                .catch(function() {
                    bodyEl.innerHTML = '<div class="alert alert-danger">Failed to load purchase details.</div>';
                });
            return;
        }
    });
    if (purchaseModalEl) {
        var purchaseCloseBtn = purchaseModalEl.querySelector('.modal-header .close, .modal-header [data-dismiss="modal"]');
        if (purchaseCloseBtn) purchaseCloseBtn.addEventListener('click', function() {
            if (typeof bootstrap !== 'undefined' && bootstrap.Modal) {
                var m = bootstrap.Modal.getInstance(purchaseModalEl);
                if (m) m.hide();
            } else {
                purchaseModalEl.classList.remove('show');
                purchaseModalEl.style.display = 'none';
                document.body.classList.remove('modal-open');
            }
        });
    }

    // Payment detail modal (supplier payment link in ledger)
    var paymentModalEl = document.getElementById('paymentDetailModal');
    document.addEventListener('click', function(e) {
        var link = e.target.closest('.ledger-payment-link');
        if (!link) return;
        e.preventDefault();
        var transactionId = link.getAttribute('data-transaction-id');
        if (!transactionId || !paymentModalEl) return;
        var bodyEl = document.getElementById('paymentDetailModalBody');
        if (!bodyEl) return;

        bodyEl.innerHTML = '<div class="text-center py-5 text-muted"><span class="spinner-border spinner-border-sm mr-2" role="status"></span> Loading...</div>';
        if (typeof bootstrap !== 'undefined' && bootstrap.Modal) {
            var m = new bootstrap.Modal(paymentModalEl);
            m.show();
        } else {
            paymentModalEl.classList.add('show');
            paymentModalEl.style.display = 'block';
            document.body.classList.add('modal-open');
        }

        fetch("{{ url('supplier-payments') }}/" + transactionId + "/content", { headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' } })
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

    if (paymentModalEl) {
        var closeBtn = paymentModalEl.querySelector('.modal-header .close, .modal-header [data-dismiss="modal"]');
        if (closeBtn) closeBtn.addEventListener('click', function() {
            if (typeof bootstrap !== 'undefined' && bootstrap.Modal) {
                var m = bootstrap.Modal.getInstance(paymentModalEl);
                if (m) m.hide();
            } else {
                paymentModalEl.classList.remove('show');
                paymentModalEl.style.display = 'none';
                document.body.classList.remove('modal-open');
            }
        });
    }
});
</script>

@endsection
