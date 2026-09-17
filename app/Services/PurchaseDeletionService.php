<?php

namespace App\Services;

use App\Models\AccountTransaction;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\PurchaseDetail;
use App\Models\StockLog;
use App\Models\Supplier;
use App\Support\InterShopTransferStatus;
use App\Services\Ledger\LedgerBalanceService;
use App\Services\Ledger\PurchaseLedgerService;
use App\Support\ProductUnitValidator;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class PurchaseDeletionService
{
    public function __construct(
        private PurchaseLedgerService $purchaseLedgerService,
        private LedgerBalanceService $ledgerBalanceService,
        private ProductUnitValidator $units,
    ) {
    }

    /**
     * Delete (soft delete) a purchase and reverse all of its effects.
     * When already inside a transaction (e.g. OrderController::update), runs in that same
     * transaction so the stock decrement is visible to subsequent child purchase creation.
     *
     * @throws \Throwable
     */
    public function deletePurchase(int $purchaseId): void
    {
        $run = function () use ($purchaseId) {
            $this->executeDelete($purchaseId);
        };

        if (DB::transactionLevel() > 0) {
            $run();
        } else {
            DB::transaction($run);
        }
    }

    /**
     * Performs the actual purchase deletion. Caller must ensure we're inside a DB transaction.
     */
    private function executeDelete(int $purchaseId): void
    {
        /** @var Purchase $purchase */
        $purchase = Purchase::with(['purchaseDetails.product', 'shop', 'paymentLogs'])
            ->lockForUpdate()
            ->findOrFail($purchaseId);

        if ($purchase->trashed()) {
            return;
        }

        // Load supplier without shop scope so we always get it (child purchase's supplier has child shop_id; current user may be mother shop).
        $supplier = $purchase->supplier_id
            ? Supplier::withoutGlobalScope('shop')->find($purchase->supplier_id)
            : null;

        $skipChildStockReversal = (bool) $purchase->is_system_generated
            && $purchase->source_sale_id
            && in_array((string) ($purchase->purchase_status ?? ''), [
                InterShopTransferStatus::PENDING,
                InterShopTransferStatus::APPROVED,
                InterShopTransferStatus::CANCELLED,
            ], true);

        // STEP 3 — Reverse stock for each purchase detail by updating products.product_store only.
        if (!$skipChildStockReversal) {
            foreach ($purchase->purchaseDetails as $detail) {
                $qty = $this->units->formatQuantity($detail->quantity ?? 0);
                if ($this->units->compare($qty, '0') <= 0) {
                    continue;
                }

                /** @var Product|null $product */
                $product = $detail->product instanceof Product
                    ? $detail->product
                    : Product::withoutGlobalScope('shop')->lockForUpdate()->find($detail->product_id);

                if (!$product) {
                    continue;
                }

                // Log BEFORE decrement: product_code, product id, qty, product_store
                \Log::info('PurchaseDeletionService: BEFORE decrement', [
                    'product_code' => $product->product_code,
                    'product_id' => $product->id,
                    'qty' => $qty,
                    'product_store' => $product->product_store,
                ]);

                // Reduce stock that was previously increased by this purchase.
                // Use query builder with shop scope removed so the UPDATE runs on the correct row
                // (child-shop products have different shop_id than current user; $product->decrement() would apply the scope and update 0 rows).
                Product::withoutGlobalScope('shop')
                    ->where('id', $product->id)
                    ->decrement('product_store', $qty);

                // Reload and log AFTER decrement
                $reloaded = Product::withoutGlobalScope('shop')->find($product->id);
                if ($reloaded) {
                    \Log::info('PurchaseDeletionService: AFTER decrement', [
                        'product_code' => $reloaded->product_code,
                        'product_id' => $reloaded->id,
                        'qty' => $qty,
                        'product_store' => $reloaded->product_store,
                    ]);
                }
            }
        }

        // STEP 4 — Soft delete original purchase stock_logs.
        StockLog::query()
            ->where('source_type', 'purchase')
            ->where('source_id', (string) $purchase->id)
            ->delete();

        // STEP 5 — Soft delete related account_transactions via dedicated ledger service.
        $this->purchaseLedgerService->reverseForPurchase($purchase);

        // STEP 6 — Recalculate supplier balance from remaining transactions and sync suppliers.credit_amount.
        if ($supplier) {
            $balance = $this->ledgerBalanceService->getSupplierBalance($supplier->id, $purchase->shop_id);
            Supplier::withoutGlobalScope('shop')
                ->where('id', $supplier->id)
                ->update(['credit_amount' => $balance]);
        }

        // STEP 7 — Rename purchase_no so the original number can be reused later.
        $originalPurchaseNo = $purchase->purchase_no;
        if ($originalPurchaseNo !== null && str_starts_with($originalPurchaseNo, 'DEL-') === false) {
            $purchase->purchase_no = 'DEL-' . $purchase->id . '-' . $originalPurchaseNo;
            $purchase->save();
        }

        // STEP 8 — Soft delete purchase_details (effects already reversed above).
        PurchaseDetail::where('purchase_id', $purchase->id)->delete();

        // STEP 9 — Soft delete the purchase record itself.
        $purchase->delete();
    }
}

