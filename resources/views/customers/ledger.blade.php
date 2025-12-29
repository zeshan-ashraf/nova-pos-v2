@extends('dashboard.body.main')

@section('container')
<div class="container-fluid mb-3" style="padding-top: 90px;">
    <div class="row mb-3">
        <div class="col-lg-8">
            <h4 class="mb-1">Customer Ledger</h4>
            <p class="mb-0 text-muted">{{ $customer->shopname }} ({{ $customer->phone }})</p>
        </div>
        <div class="col-lg-4 text-right">
            <a href="{{ route('customers.show', $customer->id) }}" class="btn btn-secondary">Back to Profile</a>
        </div>
    </div>

    <!-- Date Filter Section -->
    <div class="card mb-3">
        <div class="card-body">
            <form action="{{ route('customers.ledger', $customer->id) }}" method="GET" id="dateFilterForm">
                <div class="row align-items-end">
                    <div class="col-md-3">
                        <label for="date_filter" class="form-label">Date Filter</label>
                        <select class="form-control" name="date_filter" id="date_filter" onchange="toggleCustomDates()">
                            <option value="current_month" {{ $date_filter == 'current_month' ? 'selected' : '' }}>Current Month</option>
                            <option value="last_30_days" {{ $date_filter == 'last_30_days' ? 'selected' : '' }}>Last 30 Days</option>
                            <option value="custom" {{ $date_filter == 'custom' ? 'selected' : '' }}>Custom Range</option>
                        </select>
                    </div>
                    <div class="col-md-3" id="start_date_group" style="display: {{ $date_filter == 'custom' ? 'block' : 'none' }};">
                        <label for="start_date" class="form-label">Start Date</label>
                        <input type="date" class="form-control" name="start_date" id="start_date" value="{{ $start_date }}">
                    </div>
                    <div class="col-md-3" id="end_date_group" style="display: {{ $date_filter == 'custom' ? 'block' : 'none' }};">
                        <label for="end_date" class="form-label">End Date</label>
                        <input type="date" class="form-control" name="end_date" id="end_date" value="{{ $end_date }}">
                    </div>
                    <div class="col-md-3">
                        <button type="submit" class="btn btn-primary">Filter</button>
                        <button type="button" class="btn btn-info ml-2" onclick="window.print()">
                            <i class="ri-printer-line mr-1"></i> Print
                        </button>
                        <a href="{{ route('customers.ledgerPdf', ['customer' => $customer->id, 'date_filter' => $date_filter, 'start_date' => $start_date, 'end_date' => $end_date]) }}" class="btn btn-danger ml-2" target="_blank">
                            <i class="ri-file-pdf-line mr-1"></i> Export PDF
                        </a>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Summary Section -->
    <div class="row mb-3">
        <div class="col-md-3">
            <div class="card">
                <div class="card-body">
                    <p class="text-muted mb-1">Opening Balance</p>
                    <h4 class="mb-0">{{ number_format($opening_balance, 2) }}</h4>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card">
                <div class="card-body">
                    <p class="text-muted mb-1">Closing Balance</p>
                    <h4 class="mb-0">{{ number_format($closing_balance, 2) }}</h4>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card">
                <div class="card-body">
                    <p class="text-muted mb-1">Total Orders</p>
                    <h4 class="mb-0">{{ $summary['total_orders'] }}</h4>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card">
                <div class="card-body">
                    <p class="text-muted mb-1">Total Payments</p>
                    <h4 class="mb-0">{{ $summary['total_payments'] }}</h4>
                </div>
            </div>
        </div>
    </div>

    <!-- Detailed Summary -->
    <div class="row mb-3">
        <div class="col-md-12">
            <div class="card">
                <div class="card-body">
                    <h6 class="card-title mb-3">Summary</h6>
                    <div class="row">
                        <div class="col-md-3">
                            <p class="mb-1"><strong>Total Order Amount:</strong></p>
                            <p class="mb-0">{{ number_format($summary['total_order_amount'], 2) }}</p>
                        </div>
                        <div class="col-md-3">
                            <p class="mb-1"><strong>Total Paid:</strong></p>
                            <p class="mb-0">{{ number_format($summary['total_paid'], 2) }}</p>
                        </div>
                        <div class="col-md-3">
                            <p class="mb-1"><strong>Total Due:</strong></p>
                            <p class="mb-0">{{ number_format($summary['total_due'], 2) }}</p>
                        </div>
                        <div class="col-md-3">
                            <p class="mb-1"><strong>Total Payment Amount:</strong></p>
                            <p class="mb-0">{{ number_format($summary['total_payment_amount'], 2) }}</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Ledger Table -->
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5 class="mb-0">Transaction Ledger</h5>
            <div>
                <span class="text-muted">Period: {{ \Carbon\Carbon::parse($start_date)->format('M d, Y') }} - {{ \Carbon\Carbon::parse($end_date)->format('M d, Y') }}</span>
            </div>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table mb-0" id="ledgerTable">
                    <thead class="bg-white text-uppercase">
                        <tr class="ligth ligth-data">
                            <th>Date</th>
                            <th>Type</th>
                            <th>Invoice/Order</th>
                            <th>Description</th>
                            <th>Order Status</th>
                            <th>Payment Status</th>
                            <th class="text-right">Total</th>
                            <th class="text-right">Paid</th>
                            <th class="text-right">Due</th>
                            <th class="text-right">Debit</th>
                            <th class="text-right">Credit</th>
                            <th class="text-right">Balance</th>
                        </tr>
                    </thead>
                    <tbody class="ligth-body">
                        @forelse ($transactions as $transaction)
                            <tr>
                                <td>{{ \Carbon\Carbon::parse($transaction['date'])->format('Y-m-d') }}</td>
                                <td>
                                    <span class="badge {{ $transaction['type_badge'] }}">{{ $transaction['type'] }}</span>
                                </td>
                                <td>
                                    @if($transaction['order_id'])
                                        <a href="{{ route('order.orderDetails', $transaction['order_id']) }}" class="text-primary">
                                            {{ $transaction['invoice_no'] ?? ('Order #' . $transaction['order_id']) }}
                                        </a>
                                    @else
                                        —
                                    @endif
                                </td>
                                <td>{{ $transaction['description'] }}</td>
                                <td>
                                    @if($transaction['is_order'])
                                        <span class="badge {{ $transaction['order_status'] == 'complete' ? 'badge-success' : 'badge-warning' }}">
                                            {{ $transaction['order_status'] }}
                                        </span>
                                    @else
                                        —
                                    @endif
                                </td>
                                <td>
                                    @if($transaction['is_order'])
                                        <span class="badge badge-info">{{ $transaction['payment_status'] }}</span>
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
                                <td class="text-right">
                                    @if($transaction['is_order'])
                                        {{ number_format($transaction['paid'], 2) }}
                                    @else
                                        {{ number_format($transaction['paid'], 2) }}
                                    @endif
                                </td>
                                <td class="text-right">
                                    @if($transaction['is_order'])
                                        {{ number_format($transaction['due'], 2) }}
                                    @else
                                        —
                                    @endif
                                </td>
                                <td class="text-right">
                                    @if($transaction['debit'] > 0)
                                        <span class="text-danger">{{ number_format($transaction['debit'], 2) }}</span>
                                    @else
                                        —
                                    @endif
                                </td>
                                <td class="text-right">
                                    @if($transaction['credit'] > 0)
                                        <span class="text-success">{{ number_format($transaction['credit'], 2) }}</span>
                                    @else
                                        —
                                    @endif
                                </td>
                                <td class="text-right">
                                    <strong>{{ number_format($transaction['balance'], 2) }}</strong>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="12" class="text-center text-muted py-4">No transactions found for the selected period.</td>
                            </tr>
                        @endforelse
                    </tbody>
                    <tfoot class="bg-light">
                        <tr>
                            <td colspan="6" class="text-right"><strong>Totals:</strong></td>
                            <td class="text-right"><strong>{{ number_format($summary['total_order_amount'], 2) }}</strong></td>
                            <td class="text-right"><strong>{{ number_format($summary['total_paid'], 2) }}</strong></td>
                            <td class="text-right"><strong>{{ number_format($summary['total_due'], 2) }}</strong></td>
                            <td class="text-right"><strong>{{ number_format($transactions->sum('debit'), 2) }}</strong></td>
                            <td class="text-right"><strong>{{ number_format($transactions->sum('credit'), 2) }}</strong></td>
                            <td class="text-right"><strong>{{ number_format($closing_balance, 2) }}</strong></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
function toggleCustomDates() {
    const dateFilter = document.getElementById('date_filter').value;
    const startDateGroup = document.getElementById('start_date_group');
    const endDateGroup = document.getElementById('end_date_group');
    
    if (dateFilter === 'custom') {
        startDateGroup.style.display = 'block';
        endDateGroup.style.display = 'block';
    } else {
        startDateGroup.style.display = 'none';
        endDateGroup.style.display = 'none';
    }
}
</script>

<style>
@media print {
    .btn, .card-header .text-muted, #dateFilterForm {
        display: none !important;
    }
    .card {
        border: none !important;
        box-shadow: none !important;
    }
}
</style>
@endsection
