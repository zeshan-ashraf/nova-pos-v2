<div class="order-drawer-content">
    @php
        $selectedName = trim((string) ($highlightProduct->product_name ?? ''));
        $selectedCode = trim((string) ($highlightProduct->product_code ?? ''));
        $selectedId = (int) ($highlightProductId ?? 0);
        $selectedParentId = (int) ($highlightProduct->parent_product_id ?? 0);
        $normalize = static function (?string $value): string {
            $value = strtoupper(trim((string) $value));
            return preg_replace('/[^A-Z0-9]/', '', $value) ?? '';
        };
        $selectedNameNorm = $normalize($selectedName);
        $selectedCodeNorm = $normalize($selectedCode);
    @endphp

    <h6 class="mb-2">Order #{{ $order->id }}</h6>
    <p class="mb-1"><strong>Customer:</strong> {{ $order->customer?->name ?? $order->customer_name ?? 'Walk-in Customer' }}</p>
    <p class="mb-2"><strong>Date:</strong> {{ $order->order_date ?? $order->created_at }}</p>

    <hr>

    <div class="table-responsive">
        <table class="table table-sm mb-0">
            <thead>
                <tr>
                    <th>Product</th>
                    <th>Code</th>
                    <th>Qty</th>
                    <th class="text-right">Price</th>
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
                    </tr>
                @empty
                    <tr>
                        <td colspan="4" class="text-muted">No items found for this order.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
