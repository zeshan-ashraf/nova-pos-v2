{{-- Right-panel purchase summary (list drawer); full edit/approve flows stay on purchases.show. --}}
<div class="purchase-drawer-content">
    <div class="row mx-n1">
        <div class="col-6 px-1 mb-3">
            <div class="font-weight-bold text-uppercase" style="font-size: 0.68rem; letter-spacing: 0.04em;">Invoice</div>
            <div class="text-break">{{ $purchase->purchase_no ?: '—' }}</div>
        </div>
        <div class="col-6 px-1 mb-3">
            <div class="font-weight-bold text-uppercase" style="font-size: 0.68rem; letter-spacing: 0.04em;">Supplier</div>
            <div class="text-break">{{ $purchase->supplier->shopname ?? $purchase->supplier->name ?? '—' }}</div>
        </div>
        <div class="col-6 px-1 mb-3">
            <div class="font-weight-bold text-uppercase" style="font-size: 0.68rem; letter-spacing: 0.04em;">Date</div>
            <div>{{ $purchase->purchase_date }}</div>
        </div>
        <div class="col-6 px-1 mb-3">
            <div class="font-weight-bold text-uppercase" style="font-size: 0.68rem; letter-spacing: 0.04em;">Payment</div>
            <div class="text-capitalize">{{ $purchase->payment_status }}</div>
        </div>
        <div class="col-6 px-1 mb-3">
            <div class="font-weight-bold text-uppercase" style="font-size: 0.68rem; letter-spacing: 0.04em;">Status</div>
            <div class="text-capitalize">{{ $purchase->purchaseStatusDisplayLabel() }}</div>
        </div>
        <div class="col-6 px-1 mb-3">
            <div class="font-weight-bold text-uppercase" style="font-size: 0.68rem; letter-spacing: 0.04em;">Total</div>
            <div>{{ number_format((float) ($purchase->total ?? 0), 2) }}</div>
        </div>
    </div>

    <hr class="my-2">

    <div class="table-responsive">
        <table class="table table-sm mb-0">
            <thead>
                <tr>
                    <th>Product</th>
                    <th>Code</th>
                    <th>Qty</th>
                    <th class="text-right">Price</th>
                    <th class="text-right">Line total</th>
                </tr>
            </thead>
            <tbody>
                @forelse($purchase->purchaseDetails as $item)
                    <tr>
                        <td>{{ $item->product->product_name ?? 'N/A' }}</td>
                        <td>{{ $item->product->product_code ?? '—' }}</td>
                        <td>{{ $item->quantityWithUnit() }}</td>
                        <td class="text-right">{{ $item->unitPriceWithUnit() }}</td>
                        <td class="text-right">{{ number_format((float) ($item->total ?? 0), 2) }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="text-muted">No line items.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-3 pt-2 border-top">
        <a href="{{ route('purchases.show', $purchase->id) }}" target="_blank" rel="noopener noreferrer" class="btn btn-outline-primary btn-sm btn-block">
            <i class="ri-external-link-line mr-1"></i> Open full page
        </a>
    </div>
</div>
