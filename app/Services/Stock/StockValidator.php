<?php

namespace App\Services\Stock;

use App\Models\Product;
use App\Support\ProductUnitValidator;
use InvalidArgumentException;

/**
 * Validates stock operations before execution.
 * Quantity rules are unit-aware (piece = whole numbers, kg = DECIMAL(12,3) >= 0.001).
 * Controllers must NOT do stock math; they call StockService which uses this validator.
 */
class StockValidator
{
    public const SOURCE_TYPES = [
        'opening',
        'purchase',
        'sale',
        'purchase_return',
        'sale_return',
        'adjustment',
        'loss',
        'expired',
        'theft',
        'purchase_edit_reverse', // audit trail when reversing child purchase for mother-sale edit
        'mother_sale',           // child shop stock in from mother shop transfer
        'hold',                  // invoice draft hold (stock out)
        'hold_release',          // cancel hold / adjust hold qty down (stock in)
    ];

    public const DIRECTIONS = ['in', 'out'];

    public function __construct(
        private ProductUnitValidator $units
    ) {}

    public function productUnit(Product $product): string
    {
        $unit = $product->unit ?: Product::UNIT_PIECE;
        $this->units->validateUnit($unit);

        return $unit;
    }

    /**
     * Validate quantity against the product's unit (piece vs kg).
     */
    public function validateQty(mixed $qty, Product $product): void
    {
        $this->units->validateQuantity($qty, $this->productUnit($product), false);
    }

    /**
     * Validate that product has enough stock for an "out" operation (sale, loss, etc.).
     * WHY: Prevents negative stock; must be called inside transaction after locking product.
     */
    public function validateAvailableStock(Product $product, mixed $qtyOut): void
    {
        $available = $this->units->formatQuantity($product->product_store ?? '0');
        $requested = $this->units->formatQuantity($qtyOut);
        if ($this->units->compare($available, $requested) < 0) {
            throw new InvalidArgumentException(
                'Insufficient stock for product: '.($product->product_code ?? $product->product_name).". Available: {$available}, requested: {$requested}."
            );
        }
    }

    /**
     * Validate source_type is one of the allowed enum values.
     */
    public function validateSourceType(string $sourceType): void
    {
        if (! in_array($sourceType, self::SOURCE_TYPES, true)) {
            throw new InvalidArgumentException(
                "Invalid source_type: {$sourceType}. Allowed: ".implode(', ', self::SOURCE_TYPES)
            );
        }
    }

    /**
     * Validate direction is 'in' or 'out'.
     */
    public function validateDirection(string $direction): void
    {
        if (! in_array($direction, self::DIRECTIONS, true)) {
            throw new InvalidArgumentException("Invalid direction: {$direction}. Must be 'in' or 'out'.");
        }
    }

    /**
     * supplier_id is required only for purchase (incoming stock from supplier).
     */
    public function validateSupplierForPurchase(?int $supplierId): void
    {
        if ($supplierId === null || $supplierId < 1) {
            throw new InvalidArgumentException('supplier_id is required for purchase.');
        }
    }

    /**
     * source_id is required for all source_types except opening, adjustment, and loss types.
     */
    public function validateSourceId(string $sourceType, $sourceId): void
    {
        $allowedWithoutSourceId = ['opening', 'adjustment', 'loss', 'expired', 'theft'];
        if (in_array($sourceType, $allowedWithoutSourceId, true)) {
            return;
        }
        if ($sourceId === null || $sourceId === '') {
            throw new InvalidArgumentException("source_id is required for source_type: {$sourceType}.");
        }
    }

    /**
     * Run all validations for an "out" operation (e.g. sale, loss) in one place.
     */
    public function validateOutOperation(Product $product, mixed $qty, string $sourceType, $sourceId): void
    {
        $this->validateQty($qty, $product);
        $this->validateSourceType($sourceType);
        $this->validateDirection('out');
        $this->validateSourceId($sourceType, $sourceId);
        $this->validateAvailableStock($product, $qty);
    }

    /**
     * Run all validations for an "in" operation (e.g. purchase, opening, sale_return).
     */
    public function validateInOperation(Product $product, mixed $qty, string $sourceType, $sourceId, ?int $supplierId = null): void
    {
        $this->validateQty($qty, $product);
        $this->validateSourceType($sourceType);
        $this->validateDirection('in');
        $this->validateSourceId($sourceType, $sourceId);
        if ($sourceType === 'purchase') {
            $this->validateSupplierForPurchase($supplierId);
        }
    }
}
