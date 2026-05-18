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
     * Optional $purchaseDate sets stock_logs.adjustment_date for COGS/valuation (Y-m-d or Carbon).
     */
    public function purchaseStock(
        Product $product,
        int $qty,
        float $price,
        int $supplierId,
        int $purchaseId,
        $purchaseDate = null
    ): StockLog {
        $this->validator->validateInOperation($qty, 'purchase', (string) $purchaseId, $supplierId);

        $adjustmentDate = $purchaseDate
            ? (\is_string($purchaseDate) ? $purchaseDate : \Illuminate\Support\Carbon::parse($purchaseDate)->toDateString())
            : null;

        return $this->insertAndUpdate($product, $qty, 'in', 'purchase', (string) $purchaseId, $price, $supplierId, null, $adjustmentDate, $price);
    }

    /**
     * Record sale (stock out). Blocks if insufficient stock.
     * $price = selling price (stored in stock_logs.price); $costPerUnit = cost at time of sale (stored in stock_logs.cost_per_unit).
     * $saleDate (optional) sets stock_logs.adjustment_date for COGS/valuation (Y-m-d or Carbon/DateTime).
     */
    public function sellStock(
        Product $product,
        int $qty,
        float $price,
        int $orderId,
        ?float $costPerUnit = null,
        $saleDate = null
    ): StockLog {
        $this->validator->validateOutOperation($product, $qty, 'sale', (string) $orderId);

        $cost = $costPerUnit !== null ? $costPerUnit : (float) ($product->buying_price ?? 0);

        $adjustmentDate = $saleDate
            ? (\is_string($saleDate) ? $saleDate : \Illuminate\Support\Carbon::parse($saleDate)->toDateString())
            : null;

        return $this->insertAndUpdate($product, $qty, 'out', 'sale', (string) $orderId, $price, null, null, $adjustmentDate, $cost);
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
     * Hold invoice line: stock out on product_store; pair with reserved_stock increment in HoldInvoiceService.
     */
    public function holdStock(
        Product $product,
        int $qty,
        int $orderId,
        float $unitPrice = 0,
        ?float $costPerUnit = null,
        $holdDate = null
    ): StockLog {
        $this->validator->validateOutOperation($product, $qty, 'hold', (string) $orderId);

        $cost = $costPerUnit !== null ? $costPerUnit : (float) ($product->buying_price ?? 0);
        $adjustmentDate = $holdDate
            ? (\is_string($holdDate) ? $holdDate : \Illuminate\Support\Carbon::parse($holdDate)->toDateString())
            : null;

        return $this->insertAndUpdate(
            $product,
            $qty,
            'out',
            'hold',
            (string) $orderId,
            $unitPrice,
            null,
            null,
            $adjustmentDate,
            $cost
        );
    }

    /**
     * Release held stock back to product_store (cancel hold or reduce held qty).
     */
    public function holdReleaseStock(
        Product $product,
        int $qty,
        int $orderId,
        float $unitPrice = 0,
        ?float $costPerUnit = null,
        $holdDate = null
    ): StockLog {
        $this->validator->validateInOperation($qty, 'hold_release', (string) $orderId, null);

        $cost = $costPerUnit !== null ? $costPerUnit : (float) ($product->buying_price ?? 0);
        $adjustmentDate = $holdDate
            ? (\is_string($holdDate) ? $holdDate : \Illuminate\Support\Carbon::parse($holdDate)->toDateString())
            : null;

        return $this->insertAndUpdate(
            $product,
            $qty,
            'in',
            'hold_release',
            (string) $orderId,
            $unitPrice,
            null,
            null,
            $adjustmentDate,
            $cost
        );
    }

    /**
     * Record stock for a sale return (stock in, source_type = sale_return).
     * $returnDate (optional) sets stock_logs.adjustment_date (Y-m-d or Carbon/DateTime).
     */
    public function saleReturnStock(
        Product $product,
        int $qty,
        int $returnId,
        $returnDate = null
    ): StockLog {
        $this->validator->validateInOperation($qty, 'sale_return', (string) $returnId, null);

        $adjustmentDate = $returnDate
            ? (\is_string($returnDate) ? $returnDate : \Illuminate\Support\Carbon::parse($returnDate)->toDateString())
            : null;

        return $this->insertAndUpdate(
            $product,
            $qty,
            'in',
            'sale_return',
            (string) $returnId,
            0.0,
            null,
            null,
            $adjustmentDate
        );
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
        ?string $adjustmentDate = null,
        ?float $costPerUnit = null
    ): StockLog {
        if ($qty < 1) {
            throw new InvalidArgumentException('Quantity must be positive.');
        }

        return DB::transaction(function () use ($product, $qty, $direction, $sourceType, $sourceId, $price, $supplierId, $reason, $adjustmentDate, $costPerUnit) {
            $product = Product::lockForUpdate()->findOrFail($product->id);
            return $this->insertLogAndUpdateProduct($product, $qty, $direction, $sourceType, $sourceId, $price, $supplierId, $reason, $adjustmentDate, $costPerUnit);
        });
    }

    /**
     * Insert one stock_log and update product_store. Call only inside an active DB::transaction with product locked.
     * WHY: Ensures ledger and product_store stay in sync; no silent failures.
     *
     * Moving Weighted Average Costing: on purchase (direction=in, source_type=purchase), recalculate
     * product.buying_price (running average cost) and update product_store. On sale/out, only update
     * product_store; never modify buying_price. stock_logs always stores original purchase price (price).
     * cost_per_unit is set for sales (cost at time of sale).
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
        ?string $adjustmentDate = null,
        ?float $costPerUnit = null
    ): StockLog {
        $shopId = $product->shop_id;
        $currentQty = (int) ($product->{self::STOCK_COLUMN} ?? 0);
        $currentQty = max($currentQty, 0); // treat negative stock as 0 for avg calculation
        $delta = $direction === 'in' ? $qty : -$qty;
        $newStock = $currentQty + $delta;

        if ($newStock < 0) {
            throw new InvalidArgumentException(
                "Stock would go negative. Current: {$currentQty}, requested out: {$qty}."
            );
        }

        $logData = [
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
        ];
        if ($costPerUnit !== null) {
            $logData['cost_per_unit'] = $costPerUnit;
        }
        $log = StockLog::create($logData);

        $updateData = [self::STOCK_COLUMN => $newStock];

        if ($direction === 'in' && $sourceType === 'purchase') {
            $updateData['buying_price'] = round($this->computeWeightedAverageCost(
                $currentQty,
                (float) ($product->buying_price ?? 0),
                $qty,
                $price
            ), 4);
        }

        $product->update($updateData);

        // System expense for stock loss (non-cash; affects P&L only). Inside same transaction.
        if ($direction === 'out' && in_array($sourceType, ['loss', 'expired', 'theft'], true)) {
            $this->stockLossExpenseService->recordExpenseFromStockLog($log);
        }

        return $log;
    }

    /**
     * Single place for moving weighted average cost formula.
     * Used when recording purchase stock (in insertLogAndUpdateProduct) and when
     * stock was already increased elsewhere (e.g. child purchase from mother sale).
     *
     * @param int   $currentQty       Stock qty before this receipt
     * @param float $currentAvgCost  Current product buying_price (running average)
     * @param int   $incomingQty     Qty received in this receipt
     * @param float $incomingUnitPrice Unit price of this receipt
     * @return float New weighted average cost (4-decimal precision applied by caller)
     */
    private function computeWeightedAverageCost(
        int $currentQty,
        float $currentAvgCost,
        int $incomingQty,
        float $incomingUnitPrice
    ): float {
        $totalQty = $currentQty + $incomingQty;
        if ($totalQty <= 0) {
            return 0.0;
        }
        return (($currentQty * $currentAvgCost) + ($incomingQty * $incomingUnitPrice)) / $totalQty;
    }

    /**
     * Recalculate and persist product.buying_price after a purchase receipt when
     * product_store was already updated outside this service (e.g. child shop
     * purchase created from mother sale). Uses the same weighted average formula
     * as purchase stock. Call after increasing product_store for the product.
     *
     * @param Product $product    Product after product_store has been increased (refresh first if needed)
     * @param int     $qtyAdded   Quantity that was just added
     * @param float   $unitPrice Unit price of this receipt
     */
    public function updateBuyingPriceAfterPurchaseIn(Product $product, int $qtyAdded, float $unitPrice): void
    {
        if ($qtyAdded <= 0) {
            return;
        }
        $currentStock = (int) ($product->product_store ?? 0);
        $currentQtyBefore = max(0, $currentStock - $qtyAdded);
        $currentAvgCost = (float) ($product->buying_price ?? 0);

        $newAvgCost = $this->computeWeightedAverageCost(
            $currentQtyBefore,
            $currentAvgCost,
            $qtyAdded,
            $unitPrice
        );

        Product::withoutGlobalScope('shop')
            ->where('id', $product->id)
            ->update(['buying_price' => round($newAvgCost, 4)]);
    }
}
