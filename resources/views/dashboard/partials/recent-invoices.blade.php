@if(empty($rows))
<div class="erp-empty-state"><i class="ri-inbox-line"></i>No invoices yet</div>
@else
<table class="table table-sm erp-dash-table mb-0">
    <thead>
        <tr>
            <th>Invoice</th>
            <th>Customer</th>
            <th class="text-right">Amount</th>
            <th>Status</th>
            <th>Date</th>
        </tr>
    </thead>
    <tbody>
        @foreach($rows as $row)
        <tr>
            <td><a href="{{ $row['url'] }}">{{ $row['invoice_no'] }}</a></td>
            <td class="cell-wrap" title="{{ $row['customer'] }}">{{ $row['customer'] }}</td>
            <td class="text-right">{{ $currency }}{{ number_format($row['total'], 2) }}</td>
            <td><span class="badge badge-{{ in_array($row['status'], ['complete']) ? 'success' : 'warning' }} status-badge">{{ $row['status'] }}</span></td>
            <td class="text-muted small">{{ $row['date'] ?? '—' }}</td>
        </tr>
        @endforeach
    </tbody>
</table>
@endif
