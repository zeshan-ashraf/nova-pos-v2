<?php

namespace App\Services\Purchase;

use App\Models\Product;
use App\Models\Purchase;
use App\Models\PurchaseDetail;
use App\Services\Stock\StockService;
use App\Support\ProductUnitValidator;
use Illuminate\Support\Facades\DB;

class PurchaseReceiveService
{
    public function __construct(
        private StockService $stockService,
        private ProductUnitValidator $units,
    ) {}

    /**
     * Receive purchase:
     * - external purchases: require landed_cost_status = 'approved' and use landed_unit_cost as cost per unit
     * - internal purchases (system-generated transfers): do NOT re-apply stock movement (order flow already updated stock)
     */
    public function receivePurchase(int $purchaseId): void
    {
        DB::transaction(function () use ($purchaseId) {
            $purchase = Purchase::query()
                ->lockForUpdate()
                ->findOrFail($purchaseId);

            $isInternal = (bool) ($purchase->is_system_generated ?? false);

            if (!$isInternal) {
                if (($purchase->landed_cost_status ?? 'pending') !== 'approved') {
                    throw new \RuntimeException('Approve landed cost first');
                }
            }

            // Load all details with products.
            $details = PurchaseDetail::query()
                ->where('purchase_id', $purchaseId)
                ->get(['product_id', 'quantity', 'unitcost', 'landed_unit_cost']);

            if ($isInternal) {
                throw new \RuntimeException(
                    'Inter-shop transfer purchases are finalized when the mother shop completes the linked sale after approval.'
                );
            }

            $supplierId = (int) ($purchase->supplier_id ?? 0);
            $purchaseDate = $purchase->purchase_date;

            foreach ($details as $detail) {
                $qty = $this->units->formatQuantity($detail->quantity ?? 0);
                if ($this->units->compare($qty, '0') <= 0) {
                    continue;
                }

                $productId = (int) $detail->product_id;
                $product = Product::query()->findOrFail($productId);

                $landedUnitCost = $detail->landed_unit_cost;
                if ($landedUnitCost === null || (float) $landedUnitCost <= 0) {
                    $landedUnitCost = (float) ($detail->unitcost ?? 0);
                }

                $this->stockService->purchaseStock(
                    $product,
                    $qty,
                    (float) $landedUnitCost,
                    $supplierId,
                    $purchaseId,
                    $purchaseDate
                );

                // Make product sellable after it is actually received/valued.
                if (($product->status ?? '') === 'ordered') {
                    Product::query()
                        ->where('id', $productId)
                        ->update(['status' => 'active']);
                }
            }

            $purchase->update(['purchase_status' => 'complete']);
        });
    }
}

