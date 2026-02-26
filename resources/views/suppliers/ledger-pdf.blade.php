<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Supplier Ledger - {{ $supplier->shopname }}</title>
    <style>
        body { font-family: Arial, sans-serif; font-size: 12px; margin: 12px; padding: 0; }
        .header { margin-bottom: 16px; }
        .header h2 { margin: 0 0 4px 0; font-size: 18px; }
        .header .period { color: #6c757d; margin: 0; }
        .supplier-info { margin-bottom: 16px; }
        .supplier-info p { margin: 4px 0; }
        /* 4 boxes in one row (same as print view) */
        .summary-boxes { display: table; width: 100%; table-layout: fixed; margin-bottom: 16px; }
        .summary-boxes .box { display: table-cell; width: 25%; padding: 0 6px; vertical-align: top; }
        .summary-boxes .card { border: 1px solid #dee2e6; border-radius: 4px; padding: 10px 12px; background: #fff; }
        .summary-boxes .label { color: #6c757d; font-size: 11px; margin-bottom: 4px; }
        .summary-boxes .value { font-size: 14px; font-weight: bold; margin: 0; }
        /* Summary: labels row + values row */
        .summary-card { border: 1px solid #dee2e6; border-radius: 4px; padding: 12px; margin-bottom: 16px; background: #fff; }
        .summary-card h6 { margin: 0 0 10px 0; font-size: 13px; }
        .summary-rows { display: table; width: 100%; table-layout: fixed; }
        .summary-row { display: table-row; }
        .summary-row .col { display: table-cell; width: 25%; padding: 4px 8px 4px 0; vertical-align: top; }
        .summary-row .col strong { font-weight: 600; }
        /* Table full width */
        .ledger-section { margin-bottom: 16px; }
        .ledger-section h5 { margin: 0 0 8px 0; font-size: 14px; }
        .ledger-section .period { color: #6c757d; font-size: 11px; margin: 0 0 8px 0; }
        table.ledger-table { width: 100%; border-collapse: collapse; table-layout: fixed; }
        table.ledger-table th, table.ledger-table td { border: 1px solid #dee2e6; padding: 8px; text-align: left; font-size: 11px; }
        table.ledger-table th { background: #f8f9fa; font-weight: 600; }
        table.ledger-table tfoot tr { background: #f8f9fa; font-weight: bold; }
        table.ledger-table .col-date { width: 9%; }
        table.ledger-table .col-type { width: 10%; }
        table.ledger-table .col-purchase { width: 12%; }
        table.ledger-table .col-desc { width: 22%; }
        table.ledger-table .col-total { width: 9%; }
        table.ledger-table .col-paid { width: 9%; }
        table.ledger-table .col-due { width: 9%; }
        table.ledger-table .col-debit { width: 8%; }
        table.ledger-table .col-credit { width: 8%; }
        table.ledger-table .col-balance { width: 8%; }
        .text-right { text-align: right; }
        .text-center { text-align: center; }
        .footer { margin-top: 16px; padding-top: 8px; border-top: 1px solid #dee2e6; font-size: 10px; color: #6c757d; text-align: center; }
    </style>
</head>
<body>
    <div class="header">
        <h2>Supplier Ledger</h2>
        <p class="period">{{ \Carbon\Carbon::parse($start_date)->format('M d, Y') }} – {{ \Carbon\Carbon::parse($end_date)->format('M d, Y') }}</p>
    </div>

    <div class="supplier-info">
        <p><strong>{{ $supplier->shopname }}</strong> ({{ $supplier->phone }})</p>
        @if($supplier->email)
        <p>Email: {{ $supplier->email }}</p>
        @endif
        @if($supplier->address)
        <p>Address: {{ $supplier->address }}</p>
        @endif
    </div>

    <!-- 4 boxes in one row (same as print view) -->
    <div class="summary-boxes">
        <div class="box">
            <div class="card">
                <p class="label">Opening Balance</p>
                <p class="value">{{ number_format($opening_balance, 2) }}</p>
            </div>
        </div>
        <div class="box">
            <div class="card">
                <p class="label">Closing Balance</p>
                <p class="value">{{ number_format($closing_balance, 2) }}</p>
            </div>
        </div>
        <div class="box">
            <div class="card">
                <p class="label">Total Purchases</p>
                <p class="value">{{ $summary['total_purchases'] }}</p>
            </div>
        </div>
        <div class="box">
            <div class="card">
                <p class="label">Total Payments</p>
                <p class="value">{{ $summary['total_payments'] }}</p>
            </div>
        </div>
    </div>

    <!-- Summary: labels in one row, values in second row -->
    <div class="summary-card">
        <h6>Summary</h6>
        <div class="summary-rows">
            <div class="summary-row">
                <div class="col"><strong>Total Purchase Amount</strong></div>
                <div class="col"><strong>Total Paid</strong></div>
                <div class="col"><strong>Total Due</strong></div>
                <div class="col"><strong>Total Payment Amount</strong></div>
            </div>
            <div class="summary-row">
                <div class="col">{{ number_format($summary['total_purchase_amount'], 2) }}</div>
                <div class="col">{{ number_format($summary['total_paid'], 2) }}</div>
                <div class="col">{{ number_format($summary['total_due'], 2) }}</div>
                <div class="col">{{ number_format($summary['total_payment_amount'], 2) }}</div>
            </div>
        </div>
    </div>

    <!-- Transaction Ledger (full width) -->
    <div class="ledger-section">
        <h5>Transaction Ledger</h5>
        <p class="period">Period: {{ \Carbon\Carbon::parse($start_date)->format('M d, Y') }} – {{ \Carbon\Carbon::parse($end_date)->format('M d, Y') }}</p>
        <table class="ledger-table">
            <colgroup>
                <col class="col-date">
                <col class="col-type">
                <col class="col-purchase">
                <col class="col-desc">
                <col class="col-total">
                <col class="col-paid">
                <col class="col-due">
                <col class="col-debit">
                <col class="col-credit">
                <col class="col-balance">
            </colgroup>
            <thead>
                <tr>
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
            <tbody>
                @forelse ($transactions as $transaction)
                <tr>
                    <td>{{ \Carbon\Carbon::parse($transaction['date'])->format('Y-m-d') }}</td>
                    <td>{{ $transaction['type'] }}</td>
                    <td>{{ $transaction['type'] === 'Supplier Payment' ? 'Supplier payment' : ($transaction['purchase_no'] ?? ('Purchase #' . ($transaction['purchase_id'] ?? ''))) }}</td>
                    <td>{{ $transaction['description'] }}</td>
                    <td class="text-right">
                        @if($transaction['is_purchase'])
                            {{ number_format($transaction['total'], 2) }}
                        @else
                            —
                        @endif
                    </td>
                    <td class="text-right">{{ number_format($transaction['paid'], 2) }}</td>
                    <td class="text-right">
                        @if($transaction['is_purchase'])
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
                    <td colspan="10" class="text-center">No transactions found for the selected period.</td>
                </tr>
                @endforelse
            </tbody>
            <tfoot>
                <tr>
                    <td colspan="4" class="text-right">Totals:</td>
                    <td class="text-right">{{ number_format($summary['total_purchase_amount'], 2) }}</td>
                    <td class="text-right">{{ number_format($summary['total_paid'], 2) }}</td>
                    <td class="text-right">{{ number_format($summary['total_due'], 2) }}</td>
                    <td class="text-right">{{ number_format($transactions->sum('debit'), 2) }}</td>
                    <td class="text-right">{{ number_format($transactions->sum('credit'), 2) }}</td>
                    <td class="text-right">{{ number_format($closing_balance, 2) }}</td>
                </tr>
            </tfoot>
        </table>
    </div>

    <div class="footer">
        <p>Generated on {{ \Carbon\Carbon::now()->format('Y-m-d H:i:s') }}</p>
    </div>
</body>
</html>
