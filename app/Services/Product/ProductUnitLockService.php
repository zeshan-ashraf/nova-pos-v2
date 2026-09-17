<?php

namespace App\Services\Product;

use App\Models\OrderDetails;
use App\Models\Product;
use App\Models\PurchaseDetail;
use App\Models\PurchaseReturnDetail;
use App\Models\SaleReturnDetail;
use App\Models\StockLog;

/**
 * Phase 1 foundation: a product unit must not change after transaction history exists.
 *
 * Full enforcement on product create/update belongs to the Product Management phase.
 * Call canChangeUnit() / hasTransactionHistory() from that phase before saving unit.
 */
class ProductUnitLockService
{
    public function hasTransactionHistory(Product $product): bool
    {
        $productId = $product->getKey();

        if ($productId === null) {
            return false;
        }

        return OrderDetails::withTrashed()->where('product_id', $productId)->exists()
            || PurchaseDetail::withTrashed()->where('product_id', $productId)->exists()
            || SaleReturnDetail::withTrashed()->where('product_id', $productId)->exists()
            || PurchaseReturnDetail::withTrashed()->where('product_id', $productId)->exists()
            || StockLog::withTrashed()->where('product_id', $productId)->exists();
    }

    public function canChangeUnit(Product $product): bool
    {
        return ! $this->hasTransactionHistory($product);
    }
}
