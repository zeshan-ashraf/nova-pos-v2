<div class="order-drawer-content">
    @php
        $selectedName = trim((string) ($highlightProduct?->product_name ?? ''));
        $selectedCode = trim((string) ($highlightProduct?->product_code ?? ''));
        $selectedId = (int) ($highlightProductId ?? 0);
        $selectedParentId = (int) ($highlightProduct?->parent_product_id ?? 0);
        $normalize = static function (?string $value): string {
            $value = strtoupper(trim((string) $value));
            return preg_replace('/[^A-Z0-9]/', '', $value) ?? '';
        };
        $selectedNameNorm = $normalize($selectedName);
        $selectedCodeNorm = $normalize($selectedCode);
        $invoiceDisplay = $order->invoice_no ? '#' . $order->invoice_no : '#' . $order->id;
    @endphp

    <div class="row mx-n1">
        <div class="col-6 px-1 mb-3">
            <div class="font-weight-bold text-uppercase" style="font-size: 0.68rem; letter-spacing: 0.04em;">Invoice</div>
            <div class="text-break">{{ $invoiceDisplay }}</div>
        </div>
        <div class="col-6 px-1 mb-3">
            <div class="font-weight-bold text-uppercase" style="font-size: 0.68rem; letter-spacing: 0.04em;">Customer</div>
            <div class="text-break">{{ $order->customer?->name ?? $order->customer?->shopname ?? $order->customer_name ?? 'Walk-in' }}</div>
        </div>
        <div class="col-6 px-1 mb-3">
            <div class="font-weight-bold text-uppercase" style="font-size: 0.68rem; letter-spacing: 0.04em;">Date</div>
            <div>{{ $order->order_date ?? $order->created_at }}</div>
        </div>
        <div class="col-6 px-1 mb-3">
            <div class="font-weight-bold text-uppercase" style="font-size: 0.68rem; letter-spacing: 0.04em;">Payment</div>
            <div class="text-capitalize">{{ $order->payment_status ?? '—' }}</div>
        </div>
        <div class="col-6 px-1 mb-3">
            <div class="font-weight-bold text-uppercase" style="font-size: 0.68rem; letter-spacing: 0.04em;">Status</div>
            <div class="text-capitalize">{{ $order->order_status ?? '—' }}</div>
        </div>
        <div class="col-6 px-1 mb-3">
            <div class="font-weight-bold text-uppercase" style="font-size: 0.68rem; letter-spacing: 0.04em;">Total</div>
            <div>{{ number_format((float) ($order->total ?? 0), 2) }}</div>
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
                @forelse($order->orderDetails as $item)
                    @php
                        $itemProductId = (int) $item->product_id;
                        $itemParentId = (int) ($item->product->parent_product_id ?? 0);
                        $itemNameNorm = $normalize($item->product->product_name ?? '');
                        $itemCodeNorm = $normalize($item->product->product_code ?? '');

                        $isHighlight = ($selectedId > 0 && ($itemProductId === $selectedId || $itemParentId === $selectedId))
                            || ($selectedParentId > 0 && $itemProductId === $selectedParentId)
                            || ($selectedNameNorm !== '' && ($itemNameNorm === $selectedNameNorm || str_contains($itemNameNorm, $selectedNameNorm) || str_contains($selectedNameNorm, $itemNameNorm)))
                            || ($selectedCodeNorm !== '' && ($itemCodeNorm === $selectedCodeNorm || str_contains($itemCodeNorm, $selectedCodeNorm) || str_contains($selectedCodeNorm, $itemCodeNorm)));
                    @endphp
                    <tr class="{{ $isHighlight ? 'drawer-product-highlight-row' : '' }}">
                        <td class="{{ $isHighlight ? 'drawer-product-highlight-cell' : '' }}">{{ $item->product->product_name ?? 'N/A' }}</td>
                        <td class="{{ $isHighlight ? 'drawer-product-highlight-cell' : '' }}">{{ $item->product->product_code ?? '—' }}</td>
                        <td class="{{ $isHighlight ? 'drawer-product-highlight-cell' : '' }}">{{ $item->quantity }}</td>
                        <td class="text-right {{ $isHighlight ? 'drawer-product-highlight-cell' : '' }}">{{ number_format((float) ($item->unitcost ?? 0), 2) }}</td>
                        <td class="text-right {{ $isHighlight ? 'drawer-product-highlight-cell' : '' }}">{{ number_format((float) ($item->total ?? 0), 2) }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="text-muted">No items found for this order.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-3 pt-2 border-top">
        <a href="{{ route('order.orderDetails', ['order_id' => $order->id]) }}" target="_blank" rel="noopener noreferrer" class="btn btn-outline-primary btn-sm btn-block">
            <i class="ri-external-link-line mr-1"></i> Open full page
        </a>
    </div>
</div>
