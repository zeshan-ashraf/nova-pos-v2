<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment Receipt {{ $receipt_no }}</title>
    <link rel="stylesheet" href="{{ asset('assets/css/customer-payment-print.css') }}">
</head>
<body class="customer-payment-print-a4">
    <div class="print-doc print-a4">
        <div class="print-header">
            <div class="d-flex">
                <img class="print-logo" src="{{ $shop?->logo_url ?? asset('assets/images/user/1.png') }}" alt="Shop logo">
                <div class="print-shop">
                    <h2>{{ $shop?->name ?? 'Shop' }}</h2>
                    <div class="print-muted">{{ $shop?->phone ?? '' }}</div>
                </div>
            </div>
            <div class="print-title">
                <h1>Payment Receipt / Slip</h1>
                <div class="print-muted">Receipt No: {{ $receipt_no }}</div>
                <div class="print-muted">{{ \Carbon\Carbon::parse($transaction->transaction_date)->format('Y-m-d') }}</div>
            </div>
        </div>

        <div class="print-grid">
            <div class="print-card">
                <h4>Customer</h4>
                <div class="kv"><span class="k">Name</span><span class="v">{{ $customer?->shopname ?? $customer?->name ?? '—' }}</span></div>
                <div class="kv"><span class="k">Phone</span><span class="v">{{ $customer?->phone ?? '—' }}</span></div>
                <div class="kv"><span class="k">Address</span><span class="v">{{ $customer?->address ?? '—' }}</span></div>
            </div>
            <div class="print-card">
                <h4>Payment</h4>
                <div class="kv"><span class="k">Amount</span><span class="v">{{ number_format((float) $transaction->amount, 2) }}</span></div>
                <div class="kv"><span class="k">Method</span><span class="v">{{ $payment_method }}</span></div>
                <div class="kv"><span class="k">Bank</span><span class="v">{{ $bank_name ?? '—' }}</span></div>
                <div class="kv"><span class="k">Recorded By</span><span class="v">{{ $received_by?->name ?? '—' }}</span></div>
            </div>
        </div>

        <div class="print-grid" style="margin-top: 10px;">
            <div class="print-card">
                <h4>Ledger Snapshot</h4>
                <div class="kv"><span class="k">Previous Balance</span><span class="v">{{ number_format((float) $before_balance, 2) }}</span></div>
                <div class="kv"><span class="k">After This Payment</span><span class="v">{{ number_format((float) $after_balance, 2) }}</span></div>
                <div class="kv"><span class="k">Current Balance</span><span class="v">{{ number_format((float) $latest_balance, 2) }}</span></div>
            </div>
            <div class="print-card">
                <h4>Reference</h4>
                <div class="kv"><span class="k">Payment ID</span><span class="v">#{{ $transaction->id }}</span></div>
                <div class="kv"><span class="k">Date</span><span class="v">{{ \Carbon\Carbon::parse($transaction->transaction_date)->format('d M Y') }}</span></div>
                <div class="kv"><span class="k">Description</span><span class="v">{{ $transaction->description ?: 'Customer payment' }}</span></div>
            </div>
        </div>

        @if($shop && filled(trim((string) ($shop->print_note ?? ''))))
            <div class="print-note">{{ $shop->print_note }}</div>
        @endif

        <div class="sign-row">
            <div class="sign-box">Customer Signature</div>
            <div class="sign-box">Authorized Signature</div>
        </div>

        <div class="print-footer">This is a computer generated receipt.</div>
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
