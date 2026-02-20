@extends('dashboard.body.main')

@section('container')
@php
    $fmt = function ($n) {
        $n = (float) ($n ?? 0);
        return number_format($n, 2);
    };
@endphp
<div class="container-fluid">
    <div class="row">
        <div class="col-lg-12">
            <div class="d-flex flex-wrap align-items-center justify-content-between mb-4">
                <div>
                    <h4 class="mb-1">P&amp;L Line Detail (Sales &amp; COGS)</h4>
                    <p class="mb-0 text-muted small">{{ $dateRange['start_date'] ?? '' }} to {{ $dateRange['end_date'] ?? '' }}</p>
                </div>
                <div>
                    <a href="{{ route('reports.financial.profit-loss', request()->only(['date_filter', 'start_date', 'end_date', 'shop_id'])) }}" class="btn btn-secondary btn-sm">Back to P&amp;L Report</a>
                </div>
            </div>
        </div>

        <div class="col-lg-12">
            <div class="table-responsive bg-white border rounded">
                <table class="table table-bordered table-hover mb-0">
                    <thead class="thead-light">
                        <tr>
                            <th>#</th>
                            <th>Order ID</th>
                            <th>Order Date</th>
                            <th>Product</th>
                            <th class="text-right">Qty</th>
                            <th class="text-right">Unit Sell Price</th>
                            <th class="text-right">Line Revenue</th>
                            <th class="text-right">Cost/Unit</th>
                            <th class="text-right">Line COGS</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($rows as $index => $row)
                        <tr>
                            <td>{{ $index + 1 }}</td>
                            <td>{{ $row->order_id }}</td>
                            <td>{{ \Carbon\Carbon::parse($row->order_date)->format('Y-m-d') }}</td>
                            <td>{{ $row->product_name ?? $row->product_code ?? '—' }}</td>
                            <td class="text-right">{{ (int) $row->quantity }}</td>
                            <td class="text-right">{{ $fmt($row->unit_sell_price) }}</td>
                            <td class="text-right">{{ $fmt($row->line_revenue) }}</td>
                            <td class="text-right">{{ $fmt($row->cost_per_unit_used) }}</td>
                            <td class="text-right">{{ $fmt($row->line_cogs) }}</td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="9" class="text-center text-muted py-4">No order lines in this period.</td>
                        </tr>
                        @endforelse
                    </tbody>
                    @if($rows->isNotEmpty())
                    <tfoot class="font-weight-bold">
                        <tr>
                            <td colspan="6" class="text-right">Totals</td>
                            <td class="text-right">{{ $fmt($rows->sum('line_revenue')) }}</td>
                            <td></td>
                            <td class="text-right">{{ $fmt($rows->sum('line_cogs')) }}</td>
                        </tr>
                    </tfoot>
                    @endif
                </table>
            </div>
        </div>
    </div>
</div>
@endsection
