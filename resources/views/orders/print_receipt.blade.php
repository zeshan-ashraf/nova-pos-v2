<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Receipt - {{ $order->invoice_no }}</title>
    <style>
        body {
            margin: 0 auto;
            width: 2.5in;
            font-family: "Courier New", Courier, monospace;
            font-size: 12px;
            color: #000;
            padding: 6px;
            line-height: 1.25;
        }
        .center {
            text-align: center;
        }
        .mb-4 {
            margin-bottom: 4px;
        }
        .sep {
            border-top: 1px dashed #000;
            margin: 6px 0;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
        }
        th, td {
            padding: 2px 0;
            vertical-align: top;
        }
        th {
            font-weight: 700;
            border-bottom: 1px dashed #000;
        }
        .qty, .price, .total {
            text-align: right;
            white-space: nowrap;
        }
        .item {
            word-break: break-word;
            padding-right: 4px;
        }
        .line-total {
            display: flex;
            justify-content: space-between;
            margin: 2px 0;
        }
        .bold {
            font-weight: 700;
        }
        @media print {
            @page {
                size: 2.5in auto;
                margin: 0.08in;
            }
            body {
                width: 2.5in;
                font-size: 12px;
            }
        }
    </style>
</head>
<body>
    @php
        $shop = $order->shop ?? auth()->user()->shop ?? null;
        $subtotal = (float) ($order->sub_total ?? $orderDetails->sum('total'));
        $laborCharges = (float) ($order->vat ?? 0);
        $invoiceDiscount = (float) ($order->invoice_discount ?? 0);
        $invoiceTotal = (float) ($order->total ?? ($subtotal + $laborCharges - $invoiceDiscount));
    @endphp

    <div class="center mb-4">
        <div class="bold">{{ $shop->name ?? 'POS' }}</div>
        <div>{{ $shop->phone ?? '' }}</div>
        <div>Invoice: {{ $order->invoice_no }}</div>
        <div>{{ \Carbon\Carbon::parse($order->order_date)->format('Y-m-d H:i') }}</div>
    </div>

    <div class="sep"></div>
    <div class="mb-4">Customer: {{ $order->customer->name ?? '-' }}</div>
    <div class="sep"></div>

    <table>
        <colgroup>
            <col style="width: 44%;">
            <col style="width: 14%;">
            <col style="width: 21%;">
            <col style="width: 21%;">
        </colgroup>
        <thead>
            <tr>
                <th class="item">Item</th>
                <th class="qty">Qty</th>
                <th class="price">Price</th>
                <th class="total">Total</th>
            </tr>
        </thead>
        <tbody>
            @foreach($orderDetails as $item)
            @php
                $qty = (float) ($item->quantity ?? 0);
                $unitPrice = (float) ($item->unitcost ?? 0);
                $storedTotal = (float) ($item->total ?? 0);
                $lineTotal = $storedTotal > 0 ? $storedTotal : ($qty * $unitPrice);
            @endphp
            <tr>
                <td class="item">{{ $item->product->product_name ?? 'N/A' }}</td>
                <td class="qty">{{ number_format($qty, 0, '.', '') }}</td>
                <td class="price">{{ number_format($unitPrice, 0, '.', '') }}</td>
                <td class="total">{{ number_format($lineTotal, 0, '.', '') }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>

    <div class="sep"></div>
    <div class="line-total"><span>Subtotal</span><span>{{ number_format($subtotal, 0, '.', '') }}</span></div>
    <div class="line-total"><span>Labor</span><span>{{ number_format($laborCharges, 0, '.', '') }}</span></div>
    <div class="line-total"><span>Discount</span><span>{{ number_format($invoiceDiscount, 0, '.', '') }}</span></div>
    <div class="line-total bold"><span>Total</span><span>{{ number_format($invoiceTotal, 0, '.', '') }}</span></div>
    <div class="line-total"><span>Paid</span><span>{{ number_format((float) ($order->pay ?? 0), 0, '.', '') }}</span></div>
    <div class="line-total"><span>Due</span><span>{{ number_format((float) ($order->due ?? 0), 0, '.', '') }}</span></div>
    <div class="sep"></div>
    <div class="center">Thank you</div>

    <script>
        window.onload = function() {
            window.print();
        }
    </script>
</body>
</html>
