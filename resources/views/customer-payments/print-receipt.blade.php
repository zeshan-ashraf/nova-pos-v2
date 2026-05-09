<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment Receipt {{ $receipt_no }}</title>
    <link rel="stylesheet" href="{{ asset('assets/css/customer-payment-print.css') }}">
    <style>
        @media print {
            @page {
                size: 80mm auto;
                margin: 2mm;
            }
        }
    </style>
</head>
<body>
    @php
        $payDate = \Carbon\Carbon::parse($transaction->transaction_date);
        $custName = $customer?->shopname ?? $customer?->name ?? '—';
        $desc = $transaction->description ?: 'Customer payment';
    @endphp

    <div class="print-doc print-thermal">
        <div class="cp-slip">
            <div class="cp-slip-head">
                <h1 class="cp-slip-title">Payment Receipt</h1>
                <div class="cp-slip-shop">{{ $shop?->name ?? 'Shop' }}</div>
            </div>

            <div class="cp-sep" aria-hidden="true"></div>

            <div class="cp-meta-block" role="list">
                <div class="cp-meta-line" role="listitem">
                    <span class="cp-meta-label">Address</span>
                    <span class="cp-meta-val">{{ filled(trim((string) ($shop?->address ?? ''))) ? $shop->address : '—' }}</span>
                </div>
                <div class="cp-meta-line" role="listitem">
                    <span class="cp-meta-label">Tel</span>
                    <span class="cp-meta-val">{{ $shop?->phone ?: '—' }}</span>
                </div>
                <div class="cp-meta-line" role="listitem">
                    <span class="cp-meta-label">Date</span>
                    <span class="cp-meta-val">{{ $payDate->format('d M Y') }}</span>
                </div>
                <div class="cp-meta-line" role="listitem">
                    <span class="cp-meta-label">Manager</span>
                    <span class="cp-meta-val">{{ $received_by?->name ?? '—' }}</span>
                </div>
            </div>

            <div class="cp-sep" aria-hidden="true"></div>

            <div class="cp-line"><span>Customer</span><span>{{ $custName }}</span></div>
            <div class="cp-line"><span>Phone</span><span>{{ $customer?->phone ?? '—' }}</span></div>
            <div class="cp-line"><span>Amount</span><span>{{ number_format((float) $transaction->amount, 2) }}</span></div>
            <div class="cp-line"><span>Method</span><span>{{ $payment_method }}</span></div>
            <div class="cp-line"><span>Bank</span><span>{{ $bank_name ?? '—' }}</span></div>
            <div class="cp-line"><span>Receipt No</span><span>{{ $receipt_no }}</span></div>
            <div class="cp-meta-line" style="margin-top: 8px;">
                <span class="cp-meta-label">Note</span>
                <span class="cp-meta-val" style="font-weight: 600;">{{ $desc }}</span>
            </div>

            <div class="cp-dbl-sep" aria-hidden="true"><span></span><span></span></div>

            <div class="cp-balance-title">Balance</div>
            <div class="cp-line cp-line--strong"><span>Previous</span><span>{{ number_format((float) $before_balance, 2) }}</span></div>
            <div class="cp-line cp-line--strong"><span>After Payment</span><span>{{ number_format((float) $after_balance, 2) }}</span></div>
            <div class="cp-line cp-line--strong"><span>Current</span><span>{{ number_format((float) $latest_balance, 2) }}</span></div>

            <div class="cp-dbl-sep" aria-hidden="true"><span></span><span></span></div>

            <div class="cp-total">
                <span>Total</span>
                <span>{{ number_format((float) $transaction->amount, 2) }}</span>
            </div>

            @if($shop && filled(trim((string) ($shop->print_note ?? ''))))
                <div class="cp-note-block">{{ $shop->print_note }}</div>
            @endif

            <div class="cp-sign-row" style="grid-template-columns: 1fr;">
                <div class="cp-sign-box">Authorized Signature</div>
            </div>

            <div class="cp-thanks">Thank you!</div>
            <div class="cp-legal">Payment ID #{{ $transaction->id }}</div>
        </div>
    </div>

    <script>
        (function () {
            function closePrintPage() { window.close(); }
            window.addEventListener('afterprint', closePrintPage);
            var mediaQueryList = window.matchMedia ? window.matchMedia('print') : null;
            if (mediaQueryList) {
                mediaQueryList.addEventListener('change', function (e) {
                    if (!e.matches) closePrintPage();
                });
            }
            window.onload = function () { window.print(); };
        })();
    </script>
</body>
</html>
