<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Customer Ledger - {{ $customer->shopname ?? $customer->name }}</title>
    <style>
        body { font-family: Arial, sans-serif; font-size: 12px; margin: 20px; }
        .header { text-align: center; margin-bottom: 20px; border-bottom: 2px solid #000; padding-bottom: 10px; }
        .header h2 { margin: 0; font-size: 18px; }
        .customer-info { margin-bottom: 15px; }
        .customer-info p { margin: 5px 0; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        th { background-color: #f2f2f2; font-weight: bold; }
        .text-right { text-align: right; }
        .footer { margin-top: 20px; padding-top: 10px; border-top: 1px solid #ddd; font-size: 10px; text-align: center; }
    </style>
</head>
<body>
    <div class="header">
        <h2>Customer Ledger</h2>
        @if(!empty($start_date) && !empty($end_date))
        <p>Period: {{ \Carbon\Carbon::parse($start_date)->format('M d, Y') }} – {{ \Carbon\Carbon::parse($end_date)->format('M d, Y') }}</p>
        @else
        <p>Period: All</p>
        @endif
    </div>

    <div class="customer-info">
        <p><strong>Customer:</strong> {{ $customer->shopname ?? $customer->name }}</p>
        <p><strong>Phone:</strong> {{ $customer->phone ?? '—' }}</p>
        @if($customer->email ?? null)<p><strong>Email:</strong> {{ $customer->email }}</p>@endif
        @if($customer->address ?? null)<p><strong>Address:</strong> {{ $customer->address }}</p>@endif
        <table class="summary-table" style="margin-top: 10px;">
            <tr><td><strong>Opening Balance</strong></td><td class="text-right">{{ (float)($opening_balance ?? 0) < 0 ? '(' . number_format(abs($opening_balance ?? 0), 2) . ') Advance' : number_format($opening_balance ?? 0, 2) }}</td></tr>
            <tr><td><strong>Total Debits</strong></td><td class="text-right">{{ number_format($total_debits ?? 0, 2) }}</td></tr>
            <tr><td><strong>Total Credits</strong></td><td class="text-right">{{ number_format($total_credits ?? 0, 2) }}</td></tr>
            <tr><td><strong>Closing Balance</strong></td><td class="text-right"><strong>{{ (float)($closing_balance ?? 0) < 0 ? '(' . number_format(abs($closing_balance ?? 0), 2) . ') Advance' : number_format($closing_balance ?? 0, 2) }}</strong></td></tr>
        </table>
    </div>

    <table>
        <thead>
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
                    <td>@if(!empty($txn['order_id']))Sale@elseif(!empty($txn['payment_transaction_id']))Payment@else—@endif</td>
                    <td>{{ $txn['reference'] }}</td>
                    <td>{{ $txn['description'] }}</td>
                    <td class="text-right">{{ $txn['debit'] > 0 ? number_format($txn['debit'], 2) : '—' }}</td>
                    <td class="text-right">{{ $txn['credit'] > 0 ? number_format($txn['credit'], 2) : '—' }}</td>
                    <td class="text-right">{{ $txn['balance'] < 0 ? '(' . number_format(abs($txn['balance']), 2) . ')' : number_format($txn['balance'], 2) }}</td>
                </tr>
            @empty
                <tr><td colspan="7" class="text-center">No transactions for the selected period.</td></tr>
            @endforelse
        </tbody>
        @if(!empty($transactions) && count($transactions) > 0)
        <tfoot>
            <tr style="background-color: #f2f2f2; font-weight: bold;">
                <td colspan="6" class="text-right">Closing Balance</td>
                <td class="text-right">{{ (float)($closing_balance ?? 0) < 0 ? '(' . number_format(abs($closing_balance ?? 0), 2) . ') Advance' : number_format($closing_balance ?? 0, 2) }}</td>
            </tr>
        </tfoot>
        @endif
    </table>

    <div class="footer">Generated on: {{ \Carbon\Carbon::now()->format('Y-m-d H:i:s') }}</div>
</body>
</html>
