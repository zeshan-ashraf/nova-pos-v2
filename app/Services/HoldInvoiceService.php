<?php

namespace App\Services;

use App\Models\Order;
use App\Models\OrderDetails;
use App\Models\Product;
use App\Services\Stock\StockService;
use App\Support\ProductUnitValidator;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Hold draft invoices: product_store reduced via stock_logs (hold) + reserved_stock tracks held qty.
 *
 * Available for new sales/holds = product_store only (reserved_stock is informational).
 */
class HoldInvoiceService
{
    public const STATUS_HOLD = 'hold';

    public const STATUS_CANCELLED = 'cancelled';

    public const STOCK_COLUMN = 'product_store';

    public function __construct(
        private StockService $stockService,
        private ProductUnitValidator $units,
    ) {}

    public function availableStock(Product $product, float $addBackQty = 0): float
    {
        $physical = (float) ($product->{self::STOCK_COLUMN} ?? 0);

        return max(0, $physical + $addBackQty);
    }

    /**
     * @param  array<int, array{product_id: mixed, quantity: mixed}>  $lines
     */
    public function assertSufficientStock(array $lines, int $shopId, ?Order $excludeHoldOrder = null): void
    {
        $addBackByProduct = $this->reservedQtyByProductForOrder($excludeHoldOrder);
        $qtyByProduct = [];
        $models = [];

        foreach ($lines as $line) {
            $productId = (int) ($line['product_id'] ?? 0);
            if ($productId <= 0) {
                continue;
            }

            $product = Product::query()->whereKey($productId)->first();
            if (!$product) {
                throw new InvalidArgumentException('Selected product could not be found.');
            }
            if ($shopId && (int) $product->shop_id !== $shopId) {
                throw new InvalidArgumentException("Product {$product->product_name} does not belong to your shop.");
            }

            $qty = $this->normalizeQty($line['quantity'] ?? 0);
            $unit = $product->unit ?: Product::UNIT_PIECE;
            $this->units->validateQuantity($qty, $unit, false);

            $qtyByProduct[$productId] = isset($qtyByProduct[$productId])
                ? $this->units->add($qtyByProduct[$productId], $qty)
                : $qty;
            $models[$productId] = $product;
        }

        foreach ($qtyByProduct as $productId => $qty) {
            $product = $models[$productId];
            $physical = $this->units->formatQuantity($product->{self::STOCK_COLUMN} ?? '0');
            $addBack = $this->units->formatQuantity($addBackByProduct[$productId] ?? '0');
            $available = $this->units->add($physical, $addBack);
            if ($this->units->compare($available, $qty) < 0) {
                $label = $product->product_code ?: $product->product_name;
                throw new InvalidArgumentException("Insufficient stock for {$label}. Available: {$available}, requested: {$qty}.");
            }
        }
    }

    /**
     * @param  array<int, array{product_id: mixed, quantity: mixed, unit_price?: mixed}>  $lines
     */
    public function applyHoldStock(Order $order, array $lines, int $shopId): void
    {
        $orderId = (int) $order->id;
        $holdDate = $order->order_date;

        foreach ($lines as $line) {
            $productId = (int) ($line['product_id'] ?? 0);
            if ($productId <= 0) {
                continue;
            }
            $qty = $this->normalizeQty($line['quantity'] ?? 0);
            if ($qty <= 0) {
                continue;
            }

            $product = Product::query()->whereKey($productId)->lockForUpdate()->firstOrFail();
            if ($shopId && (int) $product->shop_id !== $shopId) {
                throw new InvalidArgumentException("Product {$product->product_name} does not belong to your shop.");
            }

            $unitPrice = isset($line['unit_price']) ? (float) $line['unit_price'] : 0;
            $this->stockService->holdStock(
                $product,
                $qty,
                $orderId,
                $unitPrice,
                (float) ($product->buying_price ?? 0),
                $holdDate
            );

            Product::whereKey($productId)->update([
                'reserved_stock' => DB::raw('reserved_stock + ' . $qty),
            ]);
        }
    }

    /**
     * Restore physical stock and clear reservation (cancel hold or replace hold lines).
     */
    public function releaseHoldStock(Order $order): void
    {
        $order->loadMissing('orderDetails');
        $orderId = (int) $order->id;
        $holdDate = $order->order_date;

        foreach ($order->orderDetails as $detail) {
            $qty = $this->normalizeQty($detail->quantity);
            if ($qty <= 0) {
                continue;
            }

            $product = Product::query()->whereKey($detail->product_id)->lockForUpdate()->first();
            if (!$product) {
                continue;
            }

            $this->stockService->holdReleaseStock(
                $product,
                $qty,
                $orderId,
                (float) ($detail->unitcost ?? 0),
                (float) ($detail->cost_per_unit ?? $product->buying_price ?? 0),
                $holdDate
            );

            Product::whereKey($detail->product_id)->update([
                'reserved_stock' => DB::raw('GREATEST(0, reserved_stock - ' . $qty . ')'),
            ]);
        }
    }

    /**
     * Complete held sale: clear reserved_stock only (product_store already reduced at hold).
     */
    public function clearReservationForOrder(Order $order): void
    {
        $order->loadMissing('orderDetails');
        foreach ($order->orderDetails as $detail) {
            $qty = $this->normalizeQty($detail->quantity);
            if ($qty <= 0) {
                continue;
            }
            Product::whereKey($detail->product_id)->update([
                'reserved_stock' => DB::raw('GREATEST(0, reserved_stock - ' . $qty . ')'),
            ]);
        }
    }

    /**
     * When completing with changed lines: adjust hold stock/reservation deltas, then clear reservation on final lines.
     *
     * @param  array<int, array{product_id: mixed, quantity: mixed, unit_price?: mixed}>  $newLines
     */
    public function syncHoldStockForComplete(Order $order, array $newLines, int $shopId): void
    {
        $order->loadMissing('orderDetails');
        $oldByProduct = $this->reservedQtyByProductForOrder($order);
        $newByProduct = [];
        foreach ($newLines as $line) {
            $pid = (int) ($line['product_id'] ?? 0);
            if ($pid <= 0) {
                continue;
            }
            $qty = $this->normalizeQty($line['quantity'] ?? 0);
            $newByProduct[$pid] = isset($newByProduct[$pid])
                ? $this->units->add($newByProduct[$pid], $qty)
                : $qty;
        }

        $allProductIds = array_unique(array_merge(array_keys($oldByProduct), array_keys($newByProduct)));
        $orderId = (int) $order->id;
        $holdDate = $order->order_date;

        foreach ($allProductIds as $productId) {
            $oldQty = $oldByProduct[$productId] ?? '0.000';
            $newQty = $newByProduct[$productId] ?? '0.000';
            if ($this->units->compare($oldQty, $newQty) === 0) {
                continue;
            }

            $product = Product::query()->whereKey($productId)->lockForUpdate()->firstOrFail();
            if ($shopId && (int) $product->shop_id !== $shopId) {
                throw new InvalidArgumentException("Product {$product->product_name} does not belong to your shop.");
            }

            if ($this->units->compare($newQty, $oldQty) > 0) {
                $delta = $this->units->subtract($newQty, $oldQty);
                $unitPrice = 0;
                foreach ($newLines as $line) {
                    if ((int) ($line['product_id'] ?? 0) === $productId) {
                        $unitPrice = (float) ($line['unit_price'] ?? 0);
                        break;
                    }
                }
                $this->stockService->holdStock(
                    $product,
                    $delta,
                    $orderId,
                    $unitPrice,
                    (float) ($product->buying_price ?? 0),
                    $holdDate
                );
                Product::whereKey($productId)->update([
                    'reserved_stock' => DB::raw('reserved_stock + ' . $delta),
                ]);
            } else {
                $delta = $this->units->subtract($oldQty, $newQty);
                $detail = $order->orderDetails->firstWhere('product_id', $productId);
                $unitPrice = (float) ($detail->unitcost ?? 0);
                $cost = (float) ($detail->cost_per_unit ?? $product->buying_price ?? 0);
                $this->stockService->holdReleaseStock(
                    $product,
                    $delta,
                    $orderId,
                    $unitPrice,
                    $cost,
                    $holdDate
                );
                Product::whereKey($productId)->update([
                    'reserved_stock' => DB::raw('GREATEST(0, reserved_stock - ' . $delta . ')'),
                ]);
            }
        }
    }

    /**
     * @param  array<int, array{product_id: mixed, quantity: mixed, unit_price: mixed, total: mixed, item_discount?: mixed}>  $lines
     */
    public function syncHoldOrderLines(Order $order, array $lines, int $shopId): void
    {
        $this->releaseHoldStock($order);
        OrderDetails::where('order_id', $order->id)->delete();
        $this->insertOrderDetails($order, $lines, $shopId);
        $this->applyHoldStock($order, $lines, $shopId);
    }

    /**
     * @param  array<int, array{product_id: mixed, quantity: mixed, unit_price: mixed, total: mixed, item_discount?: mixed}>  $lines
     */
    public function insertOrderDetails(Order $order, array $lines, int $shopId): void
    {
        foreach ($lines as $product) {
            $productId = (int) ($product['product_id'] ?? 0);
            if ($productId <= 0) {
                continue;
            }
            $productModel = Product::findOrFail($productId);
            if ($shopId && (int) $productModel->shop_id !== $shopId) {
                throw new InvalidArgumentException("Product {$productModel->product_name} does not belong to your shop.");
            }

            OrderDetails::create([
                'order_id' => $order->id,
                'product_id' => $productId,
                'quantity' => $this->normalizeQty($product['quantity'] ?? 0),
                'unit' => $productModel->unit ?: Product::UNIT_PIECE,
                'unitcost' => $product['unit_price'],
                'cost_per_unit' => (float) ($productModel->buying_price ?? 0),
                'item_discount' => $product['item_discount'] ?? 0,
                'total' => $product['total'],
            ]);
        }
    }

    /**
     * @return array<int, string>
     */
    public function reservedQtyByProductForOrder(?Order $order): array
    {
        if (!$order) {
            return [];
        }
        $order->loadMissing('orderDetails');
        $map = [];
        foreach ($order->orderDetails as $detail) {
            $pid = (int) $detail->product_id;
            $qty = $this->normalizeQty($detail->quantity);
            $map[$pid] = isset($map[$pid])
                ? $this->units->add($map[$pid], $qty)
                : $qty;
        }

        return $map;
    }

    /**
     * @return array{subtotal: float, total_products: int, total: float}
     */
    public function calculateTotals(array $lines, float $vat, float $invoiceDiscount): array
    {
        $subtotal = 0.0;
        $totalProducts = 0;
        foreach ($lines as $product) {
            if (empty($product['product_id']) || (int) $product['product_id'] <= 0) {
                continue;
            }
            $subtotal += (float) $product['total'];
            $totalProducts++;
        }

        $total = max(0, $subtotal + $vat - $invoiceDiscount);

        return [
            'subtotal' => $subtotal,
            'total_products' => $totalProducts,
            'total' => $total,
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function normalizeProductLines(array $productsInput): array
    {
        $filtered = array_filter($productsInput, function ($product) {
            return !empty($product['product_id']) && (int) $product['product_id'] > 0;
        });

        return array_values($filtered);
    }

    public function buildReloadPayload(Order $order): array
    {
        $order->loadMissing(['orderDetails.product', 'customer', 'paymentLogs']);

        return [
            'customer_id' => $order->customer_id,
            'customer_is_system' => $order->customer->is_system ?? false,
            'customer_child_shop_id' => $order->customer->child_shop_id ?? null,
            'order_date' => $order->order_date ? \Illuminate\Support\Carbon::parse($order->order_date)->format('Y-m-d\TH:i') : '',
            'comment' => $order->comment ?? '',
            'vat' => $order->vat ?? 0,
            'invoice_discount' => $order->invoice_discount ?? 0,
            'pay' => $order->pay ?? 0,
            'due' => $order->due ?? 0,
            'invoice_no' => $order->invoice_no,
            'payment_logs' => $order->paymentLogs ? $order->paymentLogs->map(function ($log) {
                return [
                    'payment_method' => $log->payment_method,
                    'amount_paid' => $log->amount_paid,
                    'shop_bank_id' => $log->shop_bank_id,
                ];
            })->values()->all() : [],
            'order_details' => $order->orderDetails ? $order->orderDetails->map(function ($d) {
                $p = $d->product;

                return [
                    'product_id' => $d->product_id,
                    'quantity' => $d->quantity,
                    'unit' => $d->unit ?: ($p?->unit ?: Product::UNIT_PIECE),
                    'unit_price' => $d->unitcost,
                    'total' => $d->total,
                    'item_discount' => $d->item_discount ?? 0,
                    'product_name' => $p ? $p->product_name : '',
                    'product_code' => $p ? ($p->product_code ?? '') : '',
                    'product_store' => $p ? ($p->{self::STOCK_COLUMN} ?? 0) : 0,
                    'buying_price' => $p ? $p->buying_price : null,
                ];
            })->values()->all() : [],
        ];
    }

    private function normalizeQty(mixed $qty): string
    {
        $normalized = $this->units->normalizeQuantity($qty);
        if ($normalized === null) {
            throw new InvalidArgumentException('Invalid quantity for stock movement.');
        }

        return $this->units->formatQuantity($normalized);
    }
}
