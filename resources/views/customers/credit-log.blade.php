@extends('dashboard.body.main')

@section('container')
<div class="container-fluid">
    <div class="row mb-3">
        <div class="col-lg-8">
            <h4 class="mb-1">Customer Credit Trail</h4>
            <p class="mb-0 text-muted">{{ $customer->shopname }} ({{ $customer->phone }})</p>
        </div>
        <div class="col-lg-4 text-right">
            <a href="{{ route('customers.show', $customer->id) }}" class="btn btn-secondary">Back to Profile</a>
        </div>
    </div>

    <div class="row">
        <div class="col-md-4 mb-3">
            <div class="card">
                <div class="card-body">
                    <p class="text-muted mb-1">Current Credit</p>
                    <h4 class="mb-0">{{ number_format($current_credit, 2) }}</h4>
                </div>
            </div>
        </div>
        <div class="col-md-4 mb-3">
            <div class="card">
                <div class="card-body">
                    <p class="text-muted mb-1">Credit Limit</p>
                    <h4 class="mb-0">{{ number_format($credit_limit, 2) }}</h4>
                </div>
            </div>
        </div>
        <div class="col-md-4 mb-3">
            <div class="card">
                <div class="card-body">
                    <p class="text-muted mb-1">Entries</p>
                    <h4 class="mb-0">{{ $events->count() }}</h4>
                </div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5 class="mb-0">Credit Activity</h5>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table mb-0">
                    <thead class="bg-white text-uppercase">
                        <tr class="ligth ligth-data">
                            <th>#</th>
                            <th>Date</th>
                            <th>Type</th>
                            <th>Invoice / Order</th>
                            <th class="text-right">Total Amount</th>
                            <th class="text-right">Amount</th>
                        </tr>
                    </thead>
                    <tbody class="ligth-body">
                        @forelse ($events as $index => $event)
                            <tr>
                                <td>{{ $index + 1 }}</td>
                                <td>{{ \Carbon\Carbon::parse($event['date'])->format('Y-m-d H:i') }}</td>
                                <td>
                                    @if($event['direction'] === 'increase')
                                        <span class="badge badge-warning">Credit Added</span>
                                    @else
                                        <span class="badge badge-success">Payment</span>
                                    @endif
                                    <div class="small text-muted">{{ $event['type'] }}</div>
                                </td>
                                <td>
                                    @if(!empty($event['order_id']))
                                        <a href="{{ route('order.orderDetails', $event['order_id']) }}">
                                            {{ $event['invoice_no'] ?? ('Order #' . $event['order_id']) }}
                                        </a>
                                    @else
                                        —
                                    @endif
                                </td>
                                <td class="text-right">
                                    @if(!empty($event['total']))
                                        {{ number_format($event['total'], 2) }}
                                    @else
                                        —
                                    @endif
                                </td>
                                <td class="text-right">
                                    @if($event['direction'] === 'increase')
                                        +{{ number_format($event['amount'], 2) }}
                                    @else
                                        -{{ number_format($event['amount'], 2) }}
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="text-center text-muted py-4">No credit activity found for this customer.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection

