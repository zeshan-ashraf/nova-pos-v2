@extends('dashboard.body.main')

@section('container')
@php
    $fmt = function ($n) {
        $n = (float) $n;
        return number_format($n, 2);
    };
    $fmtBalance = function ($n) {
        $n = (float) $n;
        if ($n < 0) return '(' . number_format(abs($n), 2) . ')';
        return number_format($n, 2);
    };
@endphp
<div class="container-fluid mb-3" style="padding-top: 90px;">
    <div class="row mb-3">
        <div class="col-lg-8">
            <h4 class="mb-1">Customer Ledger</h4>
            <p class="mb-0 text-muted">{{ $customer->shopname ?? $customer->name }} ({{ $customer->phone ?? '—' }})</p>
        </div>
        <div class="col-lg-4 text-right">
            <a href="{{ route('customer-payments.create', ['customer_id' => $customer->id]) }}" class="btn btn-success btn-sm mr-2">Record Payment</a>
            <a href="{{ route('customers.show', $customer->id) }}" class="btn btn-secondary btn-sm">Back to Profile</a>
        </div>
    </div>

    <!-- Date Filter -->
    <div class="border rounded p-3 bg-white mb-3">
        <form action="{{ route('customers.ledger', $customer->id) }}" method="GET" id="dateFilterForm">
            <div class="row align-items-end">
                <div class="col-md-3">
                    <label for="date_filter" class="form-label">Date Filter</label>
                    <select class="form-control form-control-sm" name="date_filter" id="date_filter" onchange="toggleCustomDates()">
                        <option value="all" {{ ($date_filter ?? '') == 'all' ? 'selected' : '' }}>All</option>
                        <option value="today" {{ ($date_filter ?? 'today') == 'today' ? 'selected' : '' }}>Today</option>
                        <option value="yesterday" {{ ($date_filter ?? '') == 'yesterday' ? 'selected' : '' }}>Yesterday</option>
                        <option value="last_7_days" {{ ($date_filter ?? '') == 'last_7_days' ? 'selected' : '' }}>Last 7 Days</option>
                        <option value="current_month" {{ ($date_filter ?? '') == 'current_month' ? 'selected' : '' }}>Current Month</option>
                        <option value="last_30_days" {{ ($date_filter ?? '') == 'last_30_days' ? 'selected' : '' }}>Last 30 Days</option>
                        <option value="custom" {{ ($date_filter ?? '') == 'custom' ? 'selected' : '' }}>Custom Range</option>
                    </select>
                </div>
                <div class="col-md-3" id="start_date_group" style="display: {{ ($date_filter ?? '') == 'custom' ? 'block' : 'none' }};">
                    <label for="start_date" class="form-label">From Date</label>
                    <input type="date" class="form-control form-control-sm" name="start_date" id="start_date" value="{{ $start_date ?? '' }}">
                </div>
                <div class="col-md-3" id="end_date_group" style="display: {{ ($date_filter ?? '') == 'custom' ? 'block' : 'none' }};">
                    <label for="end_date" class="form-label">To Date</label>
                    <input type="date" class="form-control form-control-sm" name="end_date" id="end_date" value="{{ $end_date ?? '' }}">
                </div>
                <div class="col-md-3">
                    <button type="submit" class="btn btn-primary btn-sm">Filter</button>
                    <button type="button" class="btn btn-outline-secondary btn-sm ml-2" onclick="window.print()">Print</button>
                    <a href="{{ route('customers.ledgerPdf', ['customer' => $customer->id, 'date_filter' => $date_filter ?? '', 'start_date' => $start_date ?? '', 'end_date' => $end_date ?? '']) }}" class="btn btn-outline-danger btn-sm ml-2" target="_blank">PDF</a>
                </div>
            </div>
        </form>
    </div>

    <!-- Accounting Summary (journal-based from account_transactions) -->
    @php
        $fmtAmt = function ($n) {
            $n = (float) $n;
            if ($n < 0) {
                return '(' . number_format(abs($n), 2) . ')';
            }
            return number_format($n, 2);
        };
        $showAdvance = function ($n) {
            return (float) $n < 0;
        };
    @endphp
    <div class="row mb-3">
        <div class="col-md-3">
            <div class="card border shadow-none">
                <div class="card-body py-3">
                    <div class="text-muted small">Opening Balance</div>
                    <div class="text-right font-weight-bold {{ $showAdvance($opening_balance ?? 0) ? 'text-danger' : '' }}">
                        {{ $fmtAmt($opening_balance ?? 0) }}
                        @if($showAdvance($opening_balance ?? 0)) <span class="small">(Advance)</span> @endif
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border shadow-none">
                <div class="card-body py-3">
                    <div class="text-muted small">Total Debits</div>
                    <div class="text-right">{{ $fmtAmt($total_debits ?? 0) }}</div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border shadow-none">
                <div class="card-body py-3">
                    <div class="text-muted small">Total Credits</div>
                    <div class="text-right">{{ $fmtAmt($total_credits ?? 0) }}</div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border shadow-none">
                <div class="card-body py-3">
                    <div class="text-muted small">Closing Balance</div>
                    <div class="text-right font-weight-bold {{ $showAdvance($closing_balance ?? 0) ? 'text-danger' : '' }}">
                        {{ $fmtAmt($closing_balance ?? 0) }}
                        @if($showAdvance($closing_balance ?? 0)) <span class="small">(Advance)</span> @endif
                    </div>
                </div>
            </div>
        </div>
    </div>
    @if(isset($start_date) && isset($end_date) && $start_date && $end_date)
    <div class="row mb-2">
        <div class="col-12 text-right text-muted small">
            Period: {{ \Carbon\Carbon::parse($start_date)->format('M d, Y') }} – {{ \Carbon\Carbon::parse($end_date)->format('M d, Y') }}
        </div>
    </div>
    @endif

    <!-- Ledger Table -->
    <div class="border rounded bg-white">
        <div class="table-responsive">
            <table class="table table-sm mb-0" id="ledgerTable">
                <thead class="thead-light">
                    <tr>
                        <th>Date</th>
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
                            <td>{{ $txn['reference'] }}</td>
                            <td>{{ $txn['description'] }}</td>
                            <td class="text-right">{{ $txn['debit'] > 0 ? $fmt($txn['debit']) : '—' }}</td>
                            <td class="text-right">{{ $txn['credit'] > 0 ? $fmt($txn['credit']) : '—' }}</td>
                            <td class="text-right">{{ $fmtBalance($txn['balance']) }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="text-center text-muted py-4">No transactions for the selected period.</td>
                        </tr>
                    @endforelse
                </tbody>
                @if(!empty($transactions) && count($transactions) > 0)
                <tfoot class="bg-light">
                    <tr>
                        <td colspan="5" class="text-right"><strong>Closing Balance</strong></td>
                        <td class="text-right"><strong>{{ $fmtBalance($closing_balance ?? 0) }}</strong> @if((float)($closing_balance ?? 0) < 0)<span class="small text-danger">(Advance)</span>@endif</td>
                    </tr>
                </tfoot>
                @endif
            </table>
        </div>
    </div>
</div>

<script>
function toggleCustomDates() {
    var f = document.getElementById('date_filter').value;
    document.getElementById('start_date_group').style.display = f === 'custom' ? 'block' : 'none';
    document.getElementById('end_date_group').style.display = f === 'custom' ? 'block' : 'none';
}
</script>
<style>
@media print {
    .btn, #dateFilterForm, .border.rounded.p-3 { display: none !important; }
}
</style>
@endsection
