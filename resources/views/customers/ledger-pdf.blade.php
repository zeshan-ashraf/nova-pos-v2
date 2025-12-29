<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Customer Ledger - {{ $customer->shopname }}</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            font-size: 12px;
            margin: 20px;
        }
        .header {
            text-align: center;
            margin-bottom: 20px;
            border-bottom: 2px solid #000;
            padding-bottom: 10px;
        }
        .header h2 {
            margin: 0;
            font-size: 18px;
        }
        .customer-info {
            margin-bottom: 15px;
        }
        .customer-info p {
            margin: 5px 0;
        }
        .summary {
            margin-bottom: 20px;
            padding: 10px;
            background-color: #f5f5f5;
        }
        .summary-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 5px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        th, td {
            border: 1px solid #ddd;
            padding: 8px;
            text-align: left;
        }
        th {
            background-color: #f2f2f2;
            font-weight: bold;
            text-align: center;
        }
        .text-right {
            text-align: right;
        }
        .text-center {
            text-align: center;
        }
        .footer {
            margin-top: 20px;
            padding-top: 10px;
            border-top: 1px solid #ddd;
            font-size: 10px;
            text-align: center;
        }
    </style>
</head>
<body>
    <div class="header">
        <h2>Customer Ledger</h2>
        <p>Period: {{ \Carbon\Carbon::parse($start_date)->format('M d, Y') }} - {{ \Carbon\Carbon::parse($end_date)->format('M d, Y') }}</p>
    </div>

    <div class="customer-info">
        <p><strong>Customer:</strong> {{ $customer->shopname }}</p>
        <p><strong>Phone:</strong> {{ $customer->phone }}</p>
        @if($customer->email)
        <p><strong>Email:</strong> {{ $customer->email }}</p>
        @endif
        @if($customer->address)
        <p><strong>Address:</strong> {{ $customer->address }}</p>
        @endif
    </div>

    <div class="summary">
        <div class="summary-row">
            <span><strong>Opening Balance:</strong></span>
            <span><strong>{{ number_format($opening_balance, 2) }}</strong></span>
        </div>
        <div class="summary-row">
            <span><strong>Closing Balance:</strong></span>
            <span><strong>{{ number_format($closing_balance, 2) }}</strong></span>
        </div>
        <div class="summary-row">
            <span>Total Orders:</span>
            <span>{{ $summary['total_orders'] }}</span>
        </div>
        <div class="summary-row">
            <span>Total Order Amount:</span>
            <span>{{ number_format($summary['total_order_amount'], 2) }}</span>
        </div>
        <div class="summary-row">
            <span>Total Paid:</span>
            <span>{{ number_format($summary['total_paid'], 2) }}</span>
        </div>
        <div class="summary-row">
            <span>Total Due:</span>
            <span>{{ number_format($summary['total_due'], 2) }}</span>
        </div>
        <div class="summary-row">
            <span>Total Payments:</span>
            <span>{{ $summary['total_payments'] }}</span>
        </div>
        <div class="summary-row">
            <span>Total Payment Amount:</span>
            <span>{{ number_format($summary['total_payment_amount'], 2) }}</span>
        </div>
    </div>

    <table>
        <thead>
            <tr>
                <th>Date</th>
                <th>Type</th>
                <th>Invoice/Order</th>
                <th>Description</th>
                <th class="text-center">Order Status</th>
                <th class="text-center">Payment Status</th>
                <th class="text-right">Total</th>
                <th class="text-right">Paid</th>
                <th class="text-right">Due</th>
                <th class="text-right">Debit</th>
                <th class="text-right">Credit</th>
                <th class="text-right">Balance</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($transactions as $transaction)
                <tr>
                    <td>{{ \Carbon\Carbon::parse($transaction['date'])->format('Y-m-d') }}</td>
                    <td>{{ $transaction['type'] }}</td>
                    <td>{{ $transaction['invoice_no'] ?? ('Order #' . $transaction['order_id']) }}</td>
                    <td>{{ $transaction['description'] }}</td>
                    <td class="text-center">
                        @if($transaction['is_order'])
                            {{ $transaction['order_status'] }}
                        @else
                            —
                        @endif
                    </td>
                    <td class="text-center">
                        @if($transaction['is_order'])
                            {{ $transaction['payment_status'] }}
                        @else
                            —
                        @endif
                    </td>
                    <td class="text-right">
                        @if($transaction['is_order'])
                            {{ number_format($transaction['total'], 2) }}
                        @else
                            —
                        @endif
                    </td>
                    <td class="text-right">{{ number_format($transaction['paid'], 2) }}</td>
                    <td class="text-right">
                        @if($transaction['is_order'])
                            {{ number_format($transaction['due'], 2) }}
                        @else
                            —
                        @endif
                    </td>
                    <td class="text-right">
                        @if($transaction['debit'] > 0)
                            {{ number_format($transaction['debit'], 2) }}
                        @else
                            —
                        @endif
                    </td>
                    <td class="text-right">
                        @if($transaction['credit'] > 0)
                            {{ number_format($transaction['credit'], 2) }}
                        @else
                            —
                        @endif
                    </td>
                    <td class="text-right"><strong>{{ number_format($transaction['balance'], 2) }}</strong></td>
                </tr>
            @empty
                <tr>
                    <td colspan="12" class="text-center">No transactions found for the selected period.</td>
                </tr>
            @endforelse
        </tbody>
        <tfoot>
            <tr style="background-color: #f2f2f2; font-weight: bold;">
                <td colspan="6" class="text-right">Totals:</td>
                <td class="text-right">{{ number_format($summary['total_order_amount'], 2) }}</td>
                <td class="text-right">{{ number_format($summary['total_paid'], 2) }}</td>
                <td class="text-right">{{ number_format($summary['total_due'], 2) }}</td>
                <td class="text-right">{{ number_format($transactions->sum('debit'), 2) }}</td>
                <td class="text-right">{{ number_format($transactions->sum('credit'), 2) }}</td>
                <td class="text-right">{{ number_format($closing_balance, 2) }}</td>
            </tr>
        </tfoot>
    </table>

    <div class="footer">
        <p>Generated on: {{ \Carbon\Carbon::now()->format('Y-m-d H:i:s') }}</p>
    </div>
</body>
</html>
