@if(empty($rows))
<div class="erp-empty-state"><i class="ri-inbox-line"></i>No purchases yet</div>
@else
<table class="table table-sm erp-dash-table mb-0">
    <thead>
        <tr>
            <th>Ref No</th>
            <th>Supplier</th>
            <th class="text-right">Amount</th>
            <th>Date</th>
        </tr>
    </thead>
    <tbody>
        @foreach($rows as $row)
        <tr>
            <td><a href="{{ $row['url'] }}">{{ $row['ref_no'] }}</a></td>
            <td class="cell-wrap" title="{{ $row['supplier'] }}">{{ $row['supplier'] }}</td>
            <td class="text-right">{{ $currency }}{{ number_format($row['amount'], 2) }}</td>
            <td class="text-muted small">{{ $row['date'] ?? '—' }}</td>
        </tr>
        @endforeach
    </tbody>
</table>
@endif
