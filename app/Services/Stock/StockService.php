<?php

namespace App\Services\Stock;

use App\Models\Product;
use App\Models\StockLog;
use App\Support\ProductUnitValidator;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Ledger-safe stock management: append-only stock_logs, product_store updated in transaction.
 * Controllers must ONLY call this service; no stock math in controllers.
 * Stock must NEVER go negative; all "out" operations validated before execution.
 * Quantities are DECIMAL(12,3) strings; piece vs kg rules come from StockValidator.
 */
class StockService
{
    public function __construct(
        private StockValidator $validator,
        private StockLossExpenseService $stockLossExpenseService,
        private ProductUnitValidator $units
    ) {}

    /**
     * Stock quantity column on products (codebase uses product_store).
     */
    private const STOCK_COLUMN = 'product_store';

    private const COST_SCALE = 4;

    /**
     * Add opening stock for a product (e.g. initial inventory).
     * source_type = opening; source_id not required.
     */
    public function addOpeningStock(Product $product, mixed $qty, float $price): StockLog
    {
        $this->validator->validateQty($qty, $product);
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
        mixed $qty,
        float $price,
        int $supplierId,
        int $purchaseId,
        $purchaseDate = null
    ): StockLog {
        $this->validator->validateInOperation($product, $qty, 'purchase', (string) $purchaseId, $supplierId);

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
        mixed $qty,
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
                if (! $product) {
                    continue;
                }
                $direction = $log->direction === 'in' ? 'out' : 'in';
                $newQty = $this->units->formatQuantity($log->qty ?? '0');
                if ($this->units->compare($newQty, '0') <= 0) {
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
        mixed $qty,
        string $direction,
        string $reason,
        ?string $adjustmentDate = null,
        ?string $lossSourceType = null
    ): StockLog {
        $this->validator->validateQty($qty, $product);
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
        mixed $qty,
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
        mixed $qty,
        int $orderId,
        float $unitPrice = 0,
        ?float $costPerUnit = null,
        $holdDate = null
    ): StockLog {
        $this->validator->validateInOperation($product, $qty, 'hold_release', (string) $orderId, null);

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
        mixed $qty,
        int $returnId,
        $returnDate = null,
        ?string $reason = null,
        float $price = 0.0
    ): StockLog {
        $this->validator->validateInOperation($product, $qty, 'sale_return', (string) $returnId, null);

        $adjustmentDate = $returnDate
            ? (\is_string($returnDate) ? $returnDate : \Illuminate\Support\Carbon::parse($returnDate)->toDateString())
            : null;

        return $this->insertAndUpdate(
            $product,
            $qty,
            'in',
            'sale_return',
            (string) $returnId,
            $price,
            null,
            $reason,
            $adjustmentDate
        );
    }

    /**
     * Record stock leaving for a purchase/transfer return (stock out, source_type = purchase_return).
     */
    public function purchaseReturnStock(
        Product $product,
        mixed $qty,
        int $returnId,
        $returnDate = null,
        ?string $reason = null,
        float $price = 0.0
    ): StockLog {
        $this->validator->validateOutOperation($product, $qty, 'purchase_return', (string) $returnId);

        $adjustmentDate = $returnDate
            ? (\is_string($returnDate) ? $returnDate : \Illuminate\Support\Carbon::parse($returnDate)->toDateString())
            : null;

        return $this->insertAndUpdate(
            $product,
            $qty,
            'out',
            'purchase_return',
            (string) $returnId,
            $price,
            $product->supplier_id ? (int) $product->supplier_id : null,
            $reason,
            $adjustmentDate
        );
    }

    /**
     * Single point: insert one stock_log row (append-only) and update product stock.
     * Call within DB::transaction; product must be locked by caller for single-call use, or locked inside when run in own transaction.
     */
    private function insertAndUpdate(
        Product $product,
        mixed $qty,
        string $direction,
        string $sourceType,
        ?string $sourceId,
        float $price,
        ?int $supplierId,
        ?string $reason = null,
        ?string $adjustmentDate = null,
        ?float $costPerUnit = null
    ): StockLog {
        $qty = $this->units->formatQuantity($qty);
        if ($this->units->compare($qty, '0') <= 0) {
            throw new InvalidArgumentException('Quantity must be positive.');
        }

        return DB::transaction(function () use ($product, $qty, $direction, $sourceType, $sourceId, $price, $supplierId, $reason, $adjustmentDate, $costPerUnit) {
            $product = Product::withoutGlobalScope('shop')->lockForUpdate()->findOrFail($product->id);
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
        mixed $qty,
        string $direction,
        string $sourceType,
        ?string $sourceId,
        float $price,
        ?int $supplierId,
        ?string $reason = null,
        ?string $adjustmentDate = null,
        ?float $costPerUnit = null
    ): StockLog {
        $qty = $this->units->formatQuantity($qty);
        $shopId = $product->shop_id;
        $currentQty = $this->units->formatQuantity($product->{self::STOCK_COLUMN} ?? '0');
        if ($this->units->compare($currentQty, '0') < 0) {
            $currentQty = '0.000'; // treat negative stock as 0 for avg calculation
        }
        $newStock = $direction === 'in'
            ? $this->units->add($currentQty, $qty)
            : $this->units->subtract($currentQty, $qty);

        if ($this->units->compare($newStock, '0') < 0) {
            throw new InvalidArgumentException(
                "Stock would go negative. Current: {$currentQty}, requested out: {$qty}."
            );
        }

        $unit = $this->validator->productUnit($product);
        $signedQty = $direction === 'out' ? $this->units->subtract('0', $qty) : $qty;

        $logData = [
            'shop_id' => $shopId,
            'product_id' => $product->id,
            'supplier_id' => $supplierId,
            'qty' => $qty,
            'unit' => $unit,
            'direction' => $direction,
            'source_type' => $sourceType,
            'source_id' => $sourceId,
            'price' => $price,
            'reason' => $reason,
            'adjustment_date' => $adjustmentDate,
            'stock_qty' => $signedQty,
        ];
        if ($costPerUnit !== null) {
            $logData['cost_per_unit'] = $this->formatCost($costPerUnit);
        }
        $log = StockLog::create($logData);

        $updateData = [self::STOCK_COLUMN => $newStock];

        if ($direction === 'in' && $sourceType === 'purchase') {
            $updateData['buying_price'] = $this->computeWeightedAverageCost(
                $currentQty,
                $product->buying_price ?? '0',
                $qty,
                $price
            );
        }

        $product->update($updateData);

        // System expense for stock loss (non-cash; affects P&L only). Inside same transaction.
        if ($direction === 'out' && in_array($sourceType, ['loss', 'expired', 'theft'], true)) {
            $this->stockLossExpenseService->recordExpenseFromStockLog($log);
        }

        return $log;
    }

    /**
     * Moving weighted average cost using decimal quantities and 4-decimal cost.
     * Formula: ((currentQty × currentAvg) + (incomingQty × incomingPrice)) / (currentQty + incomingQty)
     */
    private function computeWeightedAverageCost(
        mixed $currentQty,
        mixed $currentAvgCost,
        mixed $incomingQty,
        mixed $incomingUnitPrice
    ): string {
        $currentQty = $this->units->formatQuantity($currentQty);
        $incomingQty = $this->units->formatQuantity($incomingQty);
        $totalQty = $this->units->add($currentQty, $incomingQty);
        if ($this->units->compare($totalQty, '0') <= 0) {
            return '0.0000';
        }

        $workScale = 8;
        $existingValue = bcmul($currentQty, $this->formatCost($currentAvgCost), $workScale);
        $incomingValue = bcmul($incomingQty, $this->formatCost($incomingUnitPrice), $workScale);
        $totalValue = bcadd($existingValue, $incomingValue, $workScale);
        $average = bcdiv($totalValue, $totalQty, $workScale);

        return $this->bcRound($average, self::COST_SCALE);
    }

    /**
     * Recalculate and persist product.buying_price after a purchase receipt when
     * product_store was already updated outside this service (e.g. child shop
     * purchase created from mother sale). Uses the same weighted average formula
     * as purchase stock. Call after increasing product_store for the product.
     *
     * @param  mixed  $qtyAdded  Quantity that was just added (DECIMAL(12,3))
     * @param  float  $unitPrice Unit price of this receipt
     */
    public function updateBuyingPriceAfterPurchaseIn(Product $product, mixed $qtyAdded, float $unitPrice): void
    {
        $qtyAdded = $this->units->formatQuantity($qtyAdded);
        if ($this->units->compare($qtyAdded, '0') <= 0) {
            return;
        }
        $currentStock = $this->units->formatQuantity($product->product_store ?? '0');
        $currentQtyBefore = $this->units->compare($currentStock, $qtyAdded) <= 0
            ? '0.000'
            : $this->units->subtract($currentStock, $qtyAdded);
        $currentAvgCost = $product->buying_price ?? '0';

        $newAvgCost = $this->computeWeightedAverageCost(
            $currentQtyBefore,
            $currentAvgCost,
            $qtyAdded,
            $unitPrice
        );

        Product::withoutGlobalScope('shop')
            ->where('id', $product->id)
            ->update(['buying_price' => $newAvgCost]);
    }

    private function formatCost(mixed $cost): string
    {
        if (is_int($cost)) {
            return $cost.'.0000';
        }
        if (is_float($cost)) {
            return $this->bcRound(number_format($cost, self::COST_SCALE + 2, '.', ''), self::COST_SCALE);
        }

        $value = trim((string) $cost);
        if ($value === '' || ! preg_match('/^-?\d+(\.\d+)?$/', $value)) {
            return '0.0000';
        }

        return $this->bcRound($value, self::COST_SCALE);
    }

    /**
     * Round-half-up using bcmath, then truncate to $scale (avoids PHP float).
     */
    private function bcRound(string $number, int $scale): string
    {
        $half = '0.'.str_repeat('0', $scale).'5';
        if (bccomp($number, '0', $scale + 2) >= 0) {
            return bcadd($number, $half, $scale);
        }

        return bcsub($number, $half, $scale);
    }
}
