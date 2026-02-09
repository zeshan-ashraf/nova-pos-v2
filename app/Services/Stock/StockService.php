<?php

namespace App\Services\Stock;

use App\Models\Product;
use App\Models\StockLog;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Ledger-safe stock management: append-only stock_logs, product_store updated in transaction.
 * Controllers must ONLY call this service; no stock math in controllers.
 * Stock must NEVER go negative; all "out" operations validated before execution.
 */
class StockService
{
    public function __construct(
        private StockValidator $validator,
        private StockLossExpenseService $stockLossExpenseService
    ) {}

    /**
     * Stock quantity column on products (codebase uses product_store).
     */
    private const STOCK_COLUMN = 'product_store';

    /**
     * Add opening stock for a product (e.g. initial inventory).
     * source_type = opening; source_id not required.
     */
    public function addOpeningStock(Product $product, int $qty, float $price): StockLog
    {
        $this->validator->validateQty($qty);
        $this->validator->validateSourceType('opening');
        $this->validator->validateDirection('in');

        return $this->insertAndUpdate($product, $qty, 'in', 'opening', null, $price, null);
    }

    /**
     * Record purchase stock (incoming from supplier).
     * supplier_id and purchase_id (source_id) required.
     */
    public function purchaseStock(Product $product, int $qty, float $price, int $supplierId, int $purchaseId): StockLog
    {
        $this->validator->validateInOperation($qty, 'purchase', (string) $purchaseId, $supplierId);

        return $this->insertAndUpdate($product, $qty, 'in', 'purchase', (string) $purchaseId, $price, $supplierId);
    }

    /**
     * Record sale (stock out). Blocks if insufficient stock.
     */
    public function sellStock(Product $product, int $qty, float $price, int $orderId): StockLog
    {
        $this->validator->validateOutOperation($product, $qty, 'sale', (string) $orderId);

        return $this->insertAndUpdate($product, $qty, 'out', 'sale', (string) $orderId, $price, null);
    }

    /**
     * Reverse stock for a given source (e.g. sale/purchase deleted).
     * Does NOT delete old stock_logs; inserts reversal with opposite direction.
     * source_type/source_id identify the original movement; we find matching logs and insert one reversal per product/qty.
     */
    public function reverseStock(string $sourceType, $sourceId): void
    {
        $this->validator->validateSourceType($sourceType);
        $this->validator->validateSourceId($sourceType, $sourceId);

        $logs = StockLog::where('source_type', $sourceType)
            ->where('source_id', (string) $sourceId)
            ->whereNotNull('qty')
            ->get();

        if ($logs->isEmpty()) {
            // No ledger-style logs for this source; nothing to reverse (e.g. legacy data)
            return;
        }

        DB::transaction(function () use ($logs, $sourceType, $sourceId) {
            foreach ($logs as $log) {
                $product = Product::lockForUpdate()->find($log->product_id);
                if (!$product) {
                    continue;
                }
                $direction = $log->direction === 'in' ? 'out' : 'in';
                $newQty = (int) $log->qty;
                if ($newQty < 1) {
                    continue;
                }
                // Reversal: opposite direction; no nested transaction (we're already in one)
                $this->insertLogAndUpdateProduct($product, $newQty, $direction, $sourceType, (string) $sourceId, (float) ($log->price ?? 0), $log->supplier_id, null);
            }
        });
    }

    /**
     * Adjustment (manual in/out): e.g. damaged, found, correction.
     * When $lossSourceType is loss/expired/theft and direction is 'out', uses that as source_type and creates a system expense (non-cash).
     * $adjustmentDate: Y-m-d string (or Carbon/date); stored in stock_logs.adjustment_date.
     */
    public function adjustStock(
        Product $product,
        int $qty,
        string $direction,
        string $reason,
        ?string $adjustmentDate = null,
        ?string $lossSourceType = null
    ): StockLog {
        $this->validator->validateQty($qty);
        $this->validator->validateDirection($direction);

        $isLossOut = $direction === 'out' && $lossSourceType && in_array($lossSourceType, ['loss', 'expired', 'theft'], true);
        $sourceType = $isLossOut ? $lossSourceType : 'adjustment';
        $this->validator->validateSourceType($sourceType);

        if ($direction === 'out') {
            $this->validator->validateAvailableStock($product, $qty);
        }

        $price = $isLossOut ? (float) ($product->buying_price ?? 0) : 0;
        return $this->insertAndUpdate($product, $qty, $direction, $sourceType, null, $price, null, $reason, $adjustmentDate);
    }

    /**
     * Single point: insert one stock_log row (append-only) and update product stock.
     * Call within DB::transaction; product must be locked by caller for single-call use, or locked inside when run in own transaction.
     */
    private function insertAndUpdate(
        Product $product,
        int $qty,
        string $direction,
        string $sourceType,
        ?string $sourceId,
        float $price,
        ?int $supplierId,
        ?string $reason = null,
        ?string $adjustmentDate = null
    ): StockLog {
        if ($qty < 1) {
            throw new InvalidArgumentException('Quantity must be positive.');
        }

        return DB::transaction(function () use ($product, $qty, $direction, $sourceType, $sourceId, $price, $supplierId, $reason, $adjustmentDate) {
            $product = Product::lockForUpdate()->findOrFail($product->id);
            return $this->insertLogAndUpdateProduct($product, $qty, $direction, $sourceType, $sourceId, $price, $supplierId, $reason, $adjustmentDate);
        });
    }

    /**
     * Insert one stock_log and update product_store. Call only inside an active DB::transaction with product locked.
     * WHY: Ensures ledger and product_store stay in sync; no silent failures.
     */
    private function insertLogAndUpdateProduct(
        Product $product,
        int $qty,
        string $direction,
        string $sourceType,
        ?string $sourceId,
        float $price,
        ?int $supplierId,
        ?string $reason = null,
        ?string $adjustmentDate = null
    ): StockLog {
        $shopId = $product->shop_id;
        $current = (int) ($product->{self::STOCK_COLUMN} ?? 0);
        $delta = $direction === 'in' ? $qty : -$qty;
        $newStock = $current + $delta;

        if ($newStock < 0) {
            throw new InvalidArgumentException(
                "Stock would go negative. Current: {$current}, requested out: {$qty}."
            );
        }

        $log = StockLog::create([
            'shop_id'          => $shopId,
            'product_id'       => $product->id,
            'supplier_id'      => $supplierId,
            'qty'              => $qty,
            'direction'        => $direction,
            'source_type'      => $sourceType,
            'source_id'        => $sourceId,
            'price'            => $price,
            'reason'           => $reason,
            'adjustment_date'  => $adjustmentDate,
            'stock_qty'        => $direction === 'out' ? -$qty : $qty, // legacy column
        ]);

        $product->update([self::STOCK_COLUMN => $newStock]);

        // System expense for stock loss (non-cash; affects P&L only). Inside same transaction.
        if ($direction === 'out' && in_array($sourceType, ['loss', 'expired', 'theft'], true)) {
            $this->stockLossExpenseService->recordExpenseFromStockLog($log);
        }

        return $log;
    }
}
