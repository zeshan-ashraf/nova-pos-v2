<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Customer Ledger - {{ $customer->shopname ?? $customer->name }}</title>
    <style>
        body { font-family: Arial, sans-serif; font-size: 12px; margin: 12px; padding: 0; }
        .header { margin-bottom: 16px; text-align: center; }
        .header h2 { margin: 0 0 4px 0; font-size: 18px; }
        .header .period { color: #6c757d; margin: 0; font-size: 11px; }
        .customer-info { margin-bottom: 12px; }
        .customer-info p { margin: 4px 0; }
        /* 4 summary boxes in one row (similar to screen/print view) */
        .summary-boxes { display: table; width: 100%; table-layout: fixed; margin: 8px 0 16px 0; }
        .summary-boxes .box { display: table-cell; width: 25%; padding: 0 6px; vertical-align: top; }
        .summary-boxes .card { border: 1px solid #dee2e6; border-radius: 4px; padding: 8px 10px; background: #fff; }
        .summary-boxes .label { color: #6c757d; font-size: 11px; margin: 0 0 4px 0; }
        .summary-boxes .value { font-size: 13px; font-weight: bold; margin: 0; }
        .summary-boxes .value-advance { color: #dc3545; }
        /* Ledger table full width with fixed column widths */
        .ledger-section { margin-top: 4px; }
        .ledger-section .period { color: #6c757d; font-size: 11px; margin: 0 0 6px 0; text-align: right; }
        table.ledger-table { width: 100%; border-collapse: collapse; table-layout: fixed; margin-bottom: 12px; }
        table.ledger-table th,
        table.ledger-table td { border: 1px solid #ddd; padding: 6px 6px; text-align: left; font-size: 11px; box-sizing: border-box; }
        table.ledger-table th { background-color: #f2f2f2; font-weight: 600; }
        table.ledger-table tfoot tr { background-color: #f2f2f2; font-weight: bold; }
        /* Column widths on cells so PDF renderer (e.g. Dompdf) applies them */
        table.ledger-table th:nth-child(1), table.ledger-table td:nth-child(1) { width: 12%; }
        table.ledger-table th:nth-child(2), table.ledger-table td:nth-child(2) { width: 10%; }
        table.ledger-table th:nth-child(3), table.ledger-table td:nth-child(3) { width: 16%; }
        table.ledger-table th:nth-child(4), table.ledger-table td:nth-child(4) { width: 28%; }
        table.ledger-table th:nth-child(5), table.ledger-table td:nth-child(5) { width: 12%; }
        table.ledger-table th:nth-child(6), table.ledger-table td:nth-child(6) { width: 12%; }
        table.ledger-table th:nth-child(7), table.ledger-table td:nth-child(7) { width: 10%; }
        .text-right { text-align: right; }
        .text-center { text-align: center; }
        .footer { margin-top: 12px; padding-top: 8px; border-top: 1px solid #ddd; font-size: 10px; text-align: center; color: #6c757d; }
    </style>
</head>
<body>
    <div class="header">
        <h2>Customer Ledger</h2>
        @if(!empty($start_date) && !empty($end_date))
            <p class="period">Period: {{ \Carbon\Carbon::parse($start_date)->format('M d, Y') }} – {{ \Carbon\Carbon::parse($end_date)->format('M d, Y') }}</p>
        @else
            <p class="period">Period: All</p>
        @endif
    </div>

    <div class="customer-info">
        <p><strong>{{ $customer->shopname ?? $customer->name }}</strong> ({{ $customer->phone ?? '—' }})</p>
        @if($customer->email ?? null)
            <p>Email: {{ $customer->email }}</p>
        @endif
        @if($customer->address ?? null)
            <p>Address: {{ $customer->address }}</p>
        @endif
    </div>

    <!-- 4 summary boxes in one row: Opening, Debits, Credits, Closing -->
    @php
        $fmtAmt = function ($n) {
            $n = (float) $n;
            return number_format($n, 2);
        };
        $fmtBalance = function ($n) {
            $n = (float) $n;
            if ($n < 0) return '(' . number_format(abs($n), 2) . ')';
            return number_format($n, 2);
        };
        $showAdvance = function ($n) {
            return (float) $n < 0;
        };
    @endphp
    <div class="summary-boxes">
        <div class="box">
            <div class="card">
                <p class="label">Opening Balance</p>
                <p class="value {{ $showAdvance($opening_balance ?? 0) ? 'value-advance' : '' }}">
                    {{ $fmtBalance($opening_balance ?? 0) }}
                    @if($showAdvance($opening_balance ?? 0)) Advance @endif
                </p>
            </div>
        </div>
        <div class="box">
            <div class="card">
                <p class="label">Total Debits</p>
                <p class="value">{{ $fmtAmt($total_debits ?? 0) }}</p>
            </div>
        </div>
        <div class="box">
            <div class="card">
                <p class="label">Total Credits</p>
                <p class="value">{{ $fmtAmt($total_credits ?? 0) }}</p>
            </div>
        </div>
        <div class="box">
            <div class="card">
                <p class="label">Closing Balance</p>
                <p class="value {{ $showAdvance($closing_balance ?? 0) ? 'value-advance' : '' }}">
                    {{ $fmtBalance($closing_balance ?? 0) }}
                    @if($showAdvance($closing_balance ?? 0)) Advance @endif
                </p>
            </div>
        </div>
    </div>

    <!-- Ledger table (full width, fixed columns like screen print) -->
    <div class="ledger-section">
        @if(!empty($start_date) && !empty($end_date))
            <p class="period">Period: {{ \Carbon\Carbon::parse($start_date)->format('M d, Y') }} – {{ \Carbon\Carbon::parse($end_date)->format('M d, Y') }}</p>
        @endif
        <table class="ledger-table">
            <colgroup>
                <col class="col-date">
                <col class="col-type">
                <col class="col-ref">
                <col class="col-desc">
                <col class="col-debit">
                <col class="col-credit">
                <col class="col-balance">
            </colgroup>
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
                        <td>
                            @if(!empty($txn['is_opening']))
                                Opening Balance
                            @elseif(!empty($txn['order_id']))
                                Sale
                            @elseif(!empty($txn['payment_transaction_id']))
                                Payment
                            @else
                                —
                            @endif
                        </td>
                        <td>{{ $txn['reference'] }}</td>
                        <td>{{ $txn['description'] }}</td>
                        <td class="text-right">{{ $txn['debit'] > 0 ? $fmtAmt($txn['debit']) : '—' }}</td>
                        <td class="text-right">{{ $txn['credit'] > 0 ? $fmtAmt($txn['credit']) : '—' }}</td>
                        <td class="text-right">{{ $fmtBalance($txn['balance']) }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="text-center">No transactions for the selected period.</td>
                    </tr>
                @endforelse
            </tbody>
            @if(!empty($transactions) && count($transactions) > 0)
            <tfoot>
                <tr>
                    <td colspan="6" class="text-right">Closing Balance</td>
                    <td class="text-right">
                        {{ $fmtBalance($closing_balance ?? 0) }}
                        @if($showAdvance($closing_balance ?? 0)) Advance @endif
                    </td>
                </tr>
            </tfoot>
            @endif
        </table>
    </div>

    <div class="footer">Generated on: {{ \Carbon\Carbon::now()->format('Y-m-d H:i:s') }}</div>
</body>
</html>
