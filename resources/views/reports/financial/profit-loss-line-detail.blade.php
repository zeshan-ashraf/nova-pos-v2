@extends('dashboard.body.main')

@section('specificpagestyles')
<style>
    /* P&L line detail: table full width */
    .reports-pl-line-detail .container-fluid { max-width: 100%; padding-left: 15px; padding-right: 15px; }
    .reports-pl-line-detail .row { max-width: 100%; }
    .reports-pl-line-detail .col-lg-12 { max-width: 100%; }
    .reports-pl-line-detail .table-responsive { width: 100%; max-width: 100%; overflow-x: auto; }
    .reports-pl-line-detail .table { width: 100%; max-width: 100%; table-layout: auto; }
    #invoiceDetailModalBody .invoice-header { border-bottom: 2px solid #e9ecef; padding-bottom: 20px; margin-bottom: 20px; }
    #invoiceDetailModalBody .invoice-header h4 { margin: 0; color: #333; }
    #invoiceDetailModalBody .product-table { width: 100%; border-collapse: collapse; table-layout: fixed; }
    #invoiceDetailModalBody .product-table thead { background-color: #f8f9fa; }
    #invoiceDetailModalBody .product-table th,
    #invoiceDetailModalBody .product-table td { padding: 10px; border: 1px solid #dee2e6; text-align: left; }
    #invoiceDetailModalBody .summary-row { display: flex; justify-content: space-between; padding: 8px 0; font-size: 15px; }
    #invoiceDetailModalBody .readonly-field { background-color: #f8f9fa; border: 1px solid #dee2e6; padding: 8px 12px; border-radius: 4px; color: #495057; }
    .pl-invoice-link { cursor: pointer; }
</style>
@endsection

@section('container')
@php
    $fmt = function ($n) {
        $n = (float) ($n ?? 0);
        return number_format($n, 2);
    };
@endphp
<div class="container-fluid reports-pl-line-detail">
    <div class="row">
        <div class="col-lg-12">
            <div class="d-flex flex-wrap align-items-center justify-content-between mb-4">
                <div>
                    <h4 class="mb-1">P&amp;L Line Detail (Sales &amp; COGS)</h4>
                    <p class="mb-0 text-muted small">{{ $dateRange['start_date'] ?? '' }} to {{ $dateRange['end_date'] ?? '' }}</p>
                </div>
                <div>
                    <a href="{{ route('reports.financial.profit-loss', request()->only(['date_filter', 'start_date', 'end_date', 'shop_id'])) }}" class="btn btn-secondary btn-sm">Back to P&amp;L Report</a>
                </div>
            </div>
        </div>

        <div class="col-lg-12">
            <div class="table-responsive bg-white border rounded">
                <table class="table table-bordered table-hover mb-0">
                    <thead class="thead-light">
                        <tr>
                            <th>#</th>
                            <th>Invoice No</th>
                            <th>Order Date</th>
                            <th>Product</th>
                            <th>Product Code</th>
                            <th class="text-right">Qty</th>
                            <th class="text-right">Unit Sell Price</th>
                            <th class="text-right">Cost/Unit</th>
                            <th class="text-right">Line Revenue</th>
                            <th class="text-right">Line COGS</th>
                            <th class="text-right">Difference</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($rows as $index => $row)
                        @php
                            $lineDiff = (float) ($row->line_revenue ?? 0) - (float) ($row->line_cogs ?? 0);
                        @endphp
                        <tr>
                            <td>{{ $index + 1 }}</td>
                            <td>
                                <a href="javascript:void(0)" class="pl-invoice-link text-primary" data-order-id="{{ $row->order_id }}" title="View invoice details">{{ $row->invoice_no ?? $row->order_id }}</a>
                            </td>
                            <td>{{ \Carbon\Carbon::parse($row->order_date)->format('Y-m-d') }}</td>
                            <td>{{ $row->product_name ?? '—' }}</td>
                            <td>{{ $row->product_code ?? '—' }}</td>
                            <td class="text-right">{{ (int) $row->quantity }}</td>
                            <td class="text-right">{{ $fmt($row->unit_sell_price) }}</td>
                            <td class="text-right">{{ $fmt($row->cost_per_unit_used) }}</td>
                            <td class="text-right">{{ $fmt($row->line_revenue) }}</td>
                            <td class="text-right">{{ $fmt($row->line_cogs) }}</td>
                            <td class="text-right {{ $lineDiff >= 0 ? 'text-success' : 'text-danger' }}">{{ $fmt($lineDiff) }}</td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="11" class="text-center text-muted py-4">No order lines in this period.</td>
                        </tr>
                        @endforelse
                    </tbody>
                    @if($rows->isNotEmpty())
                    @php
                        $totalDiff = $rows->sum('line_revenue') - $rows->sum('line_cogs');
                    @endphp
                    <tfoot class="font-weight-bold">
                        <tr>
                            <td colspan="8" class="text-right">Totals</td>
                            <td class="text-right">{{ $fmt($rows->sum('line_revenue')) }}</td>
                            <td class="text-right">{{ $fmt($rows->sum('line_cogs')) }}</td>
                            <td class="text-right {{ $totalDiff >= 0 ? 'text-success' : 'text-danger' }}">{{ $fmt($totalDiff) }}</td>
                        </tr>
                    </tfoot>
                    @endif
                </table>
            </div>
        </div>
    </div>
</div>

{{-- Invoice detail modal (same as customer ledger) --}}
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
                <div class="text-center py-5 text-muted">Click an invoice to load details.</div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    var invoiceModalEl = document.getElementById('invoiceDetailModal');
    var bodyEl = document.getElementById('invoiceDetailModalBody');

    document.addEventListener('click', function(e) {
        var link = e.target.closest('.pl-invoice-link');
        if (!link) return;
        e.preventDefault();
        var orderId = link.getAttribute('data-order-id');
        if (!orderId || !invoiceModalEl || !bodyEl) return;

        bodyEl.innerHTML = '<div class="text-center py-5 text-muted"><span class="spinner-border spinner-border-sm mr-2" role="status"></span> Loading...</div>';
        if (typeof $ !== 'undefined' && $.fn.modal) {
            $('#invoiceDetailModal').modal('show');
        } else if (typeof bootstrap !== 'undefined' && bootstrap.Modal) {
            var m = new bootstrap.Modal(invoiceModalEl);
            m.show();
        } else {
            invoiceModalEl.classList.add('show');
            invoiceModalEl.style.display = 'block';
            document.body.classList.add('modal-open');
        }

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

    if (invoiceModalEl) {
        var closeBtn = invoiceModalEl.querySelector('.modal-header .close, .modal-header [data-dismiss="modal"]');
        if (closeBtn) closeBtn.addEventListener('click', function() {
            if (typeof $ !== 'undefined' && $.fn.modal) $('#invoiceDetailModal').modal('hide');
            else if (typeof bootstrap !== 'undefined' && bootstrap.Modal) { var m = bootstrap.Modal.getInstance(invoiceModalEl); if (m) m.hide(); }
            else { invoiceModalEl.classList.remove('show'); invoiceModalEl.style.display = 'none'; document.body.classList.remove('modal-open'); }
        });
    }
});
</script>
@endsection
