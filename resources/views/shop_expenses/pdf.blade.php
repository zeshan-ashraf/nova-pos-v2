<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Shop Expenses</title>
    <style>
        body { font-family: DejaVu Sans, Arial, sans-serif; font-size: 11px; margin: 14px; color: #222; }
        h2 { margin: 0 0 6px 0; font-size: 18px; }
        .meta { color: #555; margin-bottom: 14px; font-size: 10px; }
        table { width: 100%; border-collapse: collapse; table-layout: fixed; }
        th, td { border: 1px solid #ccc; padding: 6px 8px; text-align: left; vertical-align: top; word-wrap: break-word; }
        th { background: #f0f0f0; font-weight: bold; }
        .text-right { text-align: right; }
        tfoot td { font-weight: bold; background: #fafafa; }
        .col-date { width: 10%; }
        .col-shop { width: 14%; }
        .col-exp { width: 14%; }
        .col-pay { width: 8%; }
        .col-bank { width: 12%; }
        .col-desc { width: 28%; }
        .col-amt { width: 14%; }
    </style>
</head>
<body>
    <h2>Shop Expenses</h2>
    <div class="meta">
        @if(($dateRange['date_filter'] ?? '') === 'all')
            <div>Period: All time</div>
        @elseif(!empty($dateRange['start_date']) && !empty($dateRange['end_date']))
            <div>Period: {{ $dateRange['start_date'] }} to {{ $dateRange['end_date'] }}</div>
        @endif
        @if(($shopFilter['selected_shop_id'] ?? 'all') !== 'all')
            @php
                $selShop = isset($shopFilter['shops']) ? $shopFilter['shops']->firstWhere('id', (int) $shopFilter['selected_shop_id']) : null;
            @endphp
            <div>Shop filter: {{ $selShop->name ?? ('#' . $shopFilter['selected_shop_id']) }}</div>
        @else
            <div>Shop filter: All visible shops</div>
        @endif
        <div>Generated: {{ now()->format('Y-m-d H:i') }}</div>
    </div>
    <table>
        <thead>
            <tr>
                <th class="col-date">Date</th>
                <th class="col-shop">Shop</th>
                <th class="col-exp">Expense</th>
                <th class="col-pay">Payment</th>
                <th class="col-bank">Bank</th>
                <th class="col-desc">Description</th>
                <th class="col-amt text-right">Amount</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($items as $row)
                <tr>
                    <td>{{ $row->expense_date?->format('Y-m-d') }}</td>
                    <td>{{ $row->shop?->name ?? '—' }}</td>
                    <td>{{ $row->expense?->expense_title ?? '—' }}</td>
                    <td>{{ ucfirst($row->payment_type) }}</td>
                    <td>{{ $row->payment_type === 'bank' ? ($row->bank?->name ?? '—') : '—' }}</td>
                    <td>{{ $row->description ?? '' }}</td>
                    <td class="text-right">{{ number_format((float) $row->amount, 2) }}</td>
                </tr>
            @endforeach
        </tbody>
        <tfoot>
            <tr>
                <td colspan="6" class="text-right">Total</td>
                <td class="text-right">{{ number_format((float) $total, 2) }}</td>
            </tr>
        </tfoot>
    </table>
</body>
</html>
