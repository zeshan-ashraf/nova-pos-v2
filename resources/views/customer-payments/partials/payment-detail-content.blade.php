{{-- Payment detail content: used by ledger modal. --}}
@php
    $in_modal = $in_modal ?? false;
    $transaction = $transaction ?? null;
    $customer = $customer ?? null;
    $payment_method = $payment_method ?? '—';
    $bank_name = $bank_name ?? null;
@endphp
@if($transaction)
<div class="payment-detail-content">
    <div class="border rounded p-3 mb-3" style="background-color: #f8f9fa;">
        <h6 class="text-primary mb-3">Payment Details</h6>
        <div class="row">
            <div class="col-md-6">
                <div class="mb-2"><strong>Date</strong></div>
                <div class="readonly-field">{{ $transaction->transaction_date->format('Y-m-d') }}</div>
            </div>
            <div class="col-md-6">
                <div class="mb-2"><strong>Amount</strong></div>
                <div class="readonly-field text-success font-weight-bold">{{ number_format((float) $transaction->amount, 2) }}</div>
            </div>
        </div>
        <div class="row mt-2">
            <div class="col-md-6">
                <div class="mb-2"><strong>Customer</strong></div>
                <div class="readonly-field">{{ $customer ? ($customer->shopname ?? $customer->name ?? '—') : '—' }}</div>
            </div>
            <div class="col-md-6">
                <div class="mb-2"><strong>Payment Method</strong></div>
                <div class="readonly-field">{{ $payment_method }}{{ $bank_name ? ' (' . $bank_name . ')' : '' }}</div>
            </div>
        </div>
        @if(!empty(trim($transaction->description ?? '')))
        <div class="row mt-2">
            <div class="col-12">
                <div class="mb-2"><strong>Description</strong></div>
                <div class="readonly-field">{{ $transaction->description }}</div>
            </div>
        </div>
        @endif
    </div>
    @if($in_modal)
    <div class="d-flex flex-wrap gap-2">
        <a href="{{ route('customer-payments.create') }}" class="btn btn-outline-primary btn-sm">View all payments</a>
        <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">Close</button>
    </div>
    @endif
</div>
@else
<div class="alert alert-warning">Payment not found.</div>
@endif
