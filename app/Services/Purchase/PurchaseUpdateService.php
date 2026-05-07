<?php

namespace App\Services\Purchase;

use App\Models\Product;
use App\Models\Purchase;
use App\Models\PurchaseDetail;
use App\Models\PurchasePaymentLog;
use App\Models\Supplier;
use App\Services\Ledger\LedgerBalanceService;
use App\Services\Ledger\PurchaseLedgerService;
use App\Support\InterShopTransferStatus;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class PurchaseUpdateService
{
    public function __construct(
        private PurchaseLedgerService $purchaseLedgerService,
        private LedgerBalanceService $ledgerBalanceService,
        private PurchaseLandedCostService $landedCostService,
    ) {
    }

    /**
     * Update purchase lines and header; keep accounting in sync with supplier sub-ledger.
     *
     * Ledger: we soft-delete existing rows via reverseForPurchase, then recreate with createForPurchase
     * so purchase debit / AP / cash-bank entries match the new totals and payment logs without manual patching.
     */
    public function updatePurchase(Purchase $purchase, array $validatedData, int $authUserShopId): void
    {
        DB::transaction(function () use ($purchase, $validatedData, $authUserShopId) {
            /** @var Purchase $locked */
            $locked = Purchase::with(['paymentLogs'])
                ->lockForUpdate()
                ->findOrFail($purchase->id);

            if (($locked->landed_cost_status ?? '') === 'approved') {
                throw new \RuntimeException('Approved purchase cannot be edited');
            }
            if (in_array((string) ($locked->purchase_status ?? ''), ['complete', InterShopTransferStatus::COMPLETED], true)) {
                throw new \RuntimeException('Cannot edit purchase after it has been received.');
            }
            if ((bool) ($locked->is_system_generated ?? false)) {
                throw new \RuntimeException('System-generated purchase cannot be edited here.');
            }

            if ($locked->paymentLogs->count() > 1) {
                throw new \RuntimeException(
                    'Cannot edit purchase with multiple payment records. Use supplier ledger for additional payments.'
                );
            }

            $payAmount = (float) ($validatedData['pay'] ?? 0);
            $paymentStatus = $validatedData['payment_status'] ?? '';

            if ($payAmount > 0 && in_array(strtolower($paymentStatus), ['bank', 'cheque'], true)) {
                $shopBankId = $validatedData['shop_bank_id'] ?? null;
                if (empty($shopBankId)) {
                    throw new \InvalidArgumentException('Please select a bank when payment method is Bank or Cheque.');
                }
                if ($authUserShopId && !DB::table('bank_shop')->where('id', $shopBankId)->where('shop_id', $authUserShopId)->exists()) {
                    throw new \InvalidArgumentException('The selected bank is not valid for your shop.');
                }
            }

            // Remove old ledger rows (soft-delete) before amounts/payment logs change.
            $this->purchaseLedgerService->reverseForPurchase($locked);
            $this->syncSupplierCreditFromLedger($locked);

            // Replace line items (no stock impact — purchase not received).
            PurchaseDetail::where('purchase_id', $locked->id)->delete();

            $subtotal = 0;
            $totalProducts = 0;
            foreach ($validatedData['products'] as $product) {
                if (empty($product['product_id'])) {
                    continue;
                }
                $subtotal += (float) $product['total'];
                $totalProducts++;
            }

            $vat = $validatedData['vat'] ?? 0;
            $invoiceDiscount = $validatedData['invoice_discount'] ?? 0;
            $total = max(0, $subtotal + (float) $vat - (float) $invoiceDiscount);
            $due = $total - $payAmount;

            $purchaseDate = Carbon::parse($validatedData['purchase_date'])->format('Y-m-d');
            $shopBankId = ($validatedData['shop_bank_id'] ?? null) ? (string) $validatedData['shop_bank_id'] : null;

            $locked->fill([
                'supplier_id' => (int) $validatedData['supplier_id'],
                'purchase_date' => $purchaseDate,
                'total_products' => $totalProducts,
                'sub_total' => $subtotal,
                'invoice_discount' => $invoiceDiscount,
                'vat' => $vat,
                'total' => $total,
                'payment_status' => $validatedData['payment_status'],
                'pay' => $payAmount,
                'due' => $due,
                'comment' => $validatedData['comment'] ?? $locked->comment,
            ]);
            $locked->save();

            foreach ($validatedData['products'] as $product) {
                if (empty($product['product_id'])) {
                    continue;
                }

                $productModel = Product::findOrFail($product['product_id']);
                if ($productModel->shop_id !== $authUserShopId) {
                    throw new \RuntimeException("Product {$productModel->product_name} does not belong to your shop.");
                }

                PurchaseDetail::create([
                    'purchase_id' => $locked->id,
                    'product_id' => $product['product_id'],
                    'quantity' => $product['quantity'],
                    'unitcost' => $product['unit_price'],
                    'item_discount' => $product['item_discount'] ?? 0,
                    'total' => $product['total'],
                ]);
            }

            // Rebuild a single payment log to match store behaviour (only when pay > 0).
            PurchasePaymentLog::where('purchase_id', $locked->id)->delete();
            if ($payAmount > 0) {
                PurchasePaymentLog::create([
                    'purchase_id' => $locked->id,
                    'amount_paid' => $payAmount,
                    'type' => 'payment',
                    'shop_bank_id' => in_array(strtolower($paymentStatus), ['bank', 'cheque'], true) ? $shopBankId : null,
                ]);
            }

            $this->purchaseLedgerService->createForPurchase($locked->fresh(['paymentLogs']));
            $this->syncSupplierCreditFromLedger($locked);

            $this->landedCostService->calculate((int) $locked->id);
        });
    }

    private function syncSupplierCreditFromLedger(Purchase $purchase): void
    {
        if (!$purchase->supplier_id || !$purchase->shop_id) {
            return;
        }

        $balance = $this->ledgerBalanceService->getSupplierBalance((int) $purchase->supplier_id, (int) $purchase->shop_id);
        Supplier::withoutGlobalScope('shop')
            ->where('id', $purchase->supplier_id)
            ->update(['credit_amount' => $balance]);
    }
}
