<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>A4 Invoice - {{ $order->invoice_no }}</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            color: #222;
            margin: 24px;
            font-size: 13px;
        }
        .invoice-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 18px;
        }
        .logo {
            max-width: 140px;
            max-height: 80px;
            object-fit: contain;
        }
        .title {
            text-align: right;
        }
        .title h1 {
            margin: 0;
            font-size: 24px;
        }
        .meta {
            margin-top: 4px;
            color: #555;
        }
        .info-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 16px;
            margin-bottom: 16px;
        }
        .info-card h4 {
            margin: 0 0 6px;
            font-size: 13px;
        }
        .info-card p {
            margin: 2px 0;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 8px;
        }
        th, td {
            border: 1px solid #d4d4d4;
            padding: 8px 6px;
            text-align: left;
        }
        th {
            background: #f5f5f5;
            font-weight: 600;
        }
        .text-right {
            text-align: right;
        }
        .totals {
            margin-top: 16px;
            margin-left: auto;
            width: 320px;
        }
        .totals-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 6px;
        }
        .totals-row strong {
            font-size: 14px;
        }
        .note {
            margin-top: 16px;
            white-space: pre-wrap;
        }
        @media print {
            body {
                margin: 12mm;
            }
        }
    </style>
</head>
<body>
    @php
        $shop = $order->shop ?? auth()->user()->shop ?? null;
        $logoUrl = auth()->user()->shop?->logo_url ?? asset('assets/images/login/company-logo.png');
        $subtotal = (float) ($order->sub_total ?? $orderDetails->sum('total'));
        $laborCharges = (float) ($order->vat ?? 0);
        $invoiceDiscount = (float) ($order->invoice_discount ?? 0);
        $invoiceTotal = (float) ($order->total ?? ($subtotal + $laborCharges - $invoiceDiscount));
    @endphp

    <div class="invoice-header">
        <div>
            <img class="logo" src="{{ $logoUrl }}" alt="logo">
            <div class="meta">{{ $shop->name ?? 'POS' }}</div>
            <div class="meta">{{ $shop->phone ?? '' }}</div>
        </div>
        <div class="title">
            <h1>INVOICE</h1>
            <div class="meta">Invoice #: {{ $order->invoice_no }}</div>
            <div class="meta">Date: {{ \Carbon\Carbon::parse($order->order_date)->format('Y-m-d H:i') }}</div>
        </div>
    </div>

    <div class="info-grid">
        <div class="info-card">
            <h4>Bill To</h4>
            <p>{{ $order->customer->name ?? '-' }}</p>
            <p>{{ $order->customer->phone ?? '-' }}</p>
            <p>{{ $order->customer->address ?? '-' }}</p>
        </div>
        <div class="info-card">
            <h4>Payment</h4>
            <p>Status: {{ ucfirst($order->payment_status ?? 'n/a') }}</p>
            <p>Paid: {{ number_format((float) ($order->pay ?? 0), 2) }}</p>
            <p>Due: {{ number_format((float) ($order->due ?? 0), 2) }}</p>
        </div>
    </div>

    <table>
        <thead>
            <tr>
                <th>Product Name</th>
                <th>Product Code</th>
                <th class="text-right">Quantity</th>
                <th class="text-right">Price</th>
                <th class="text-right">Total</th>
            </tr>
        </thead>
        <tbody>
            @foreach($orderDetails as $item)
            <tr>
                <td>{{ $item->product->product_name ?? 'N/A' }}</td>
                <td>{{ $item->product->product_code ?? '-' }}</td>
                <td class="text-right">{{ number_format((float) $item->quantity, 2) }}</td>
                <td class="text-right">{{ number_format((float) $item->unitcost, 2) }}</td>
                <td class="text-right">{{ number_format((float) $item->total, 2) }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>

    <div class="totals">
        <div class="totals-row"><span>Subtotal</span><span>{{ number_format($subtotal, 2) }}</span></div>
        <div class="totals-row"><span>Labor Charges</span><span>{{ number_format($laborCharges, 2) }}</span></div>
        <div class="totals-row"><span>Discount</span><span>{{ number_format($invoiceDiscount, 2) }}</span></div>
        <div class="totals-row"><strong>Invoice Total</strong><strong>{{ number_format($invoiceTotal, 2) }}</strong></div>
    </div>

    @if(!empty($order->comment))
        <div class="note">
            <strong>Note:</strong> {{ $order->comment }}
        </div>
    @endif

    <script>
        window.onload = function() {
            window.print();
        }
    </script>
</body>
</html>
