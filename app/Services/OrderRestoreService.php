<?php

namespace App\Services;

use App\Models\AccountTransaction;
use App\Models\Order;
use App\Models\OrderDetails;
use App\Models\PaymentLog;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\PurchaseDetail;
use App\Models\PurchasePaymentLog;
use App\Models\StockLog;
use App\Models\Customer;
use App\Models\Supplier;
use App\Services\Ledger\LedgerBalanceService;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Restores a soft-deleted order and all related soft-deleted records:
 * order_details, payment_logs, account_transactions (sale), stock_logs (sale).
 * Reverses stock (decrements product_store) and syncs customer credit.
 * Optionally restores linked child purchase (shop transfer) and its related data.
 */
class OrderRestoreService
{
    public function __construct(
        private LedgerBalanceService $ledgerBalanceService,
    ) {
    }

    /**
     * Restore a soft-deleted order and all its related entries.
     *
     * @throws RuntimeException
     */
    public function restoreOrder(int $orderId): void
    {
        $order = Order::withTrashed()
            ->with(['orderDetails' => fn ($q) => $q->withTrashed()])
            ->find($orderId);

        if (!$order) {
            throw new RuntimeException("Order with ID {$orderId} not found.");
        }

        if (!$order->trashed()) {
            throw new RuntimeException("Order {$orderId} is not deleted. Nothing to restore.");
        }

        DB::transaction(function () use ($order) {
            $this->restoreOrderAndRelated($order);

            // Restore linked child purchase (shop transfer) if it was deleted with this order
            $childPurchase = Purchase::withTrashed()
                ->where('source_sale_id', $order->id)
                ->first();

            if ($childPurchase && $childPurchase->trashed()) {
                $this->restoreChildPurchase($childPurchase);
            }
        });
    }

    /**
     * Restore the order, its details, payment logs, ledger entries, stock logs, and re-apply stock + customer balance.
     */
    private function restoreOrderAndRelated(Order $order): void
    {
        $orderId = (int) $order->id;

        // 1. Restore order first (so FKs are valid)
        $order->restore();

        // 2. Restore order_details
        OrderDetails::withTrashed()
            ->where('order_id', $orderId)
            ->restore();

        // 3. Restore payment_logs
        PaymentLog::withTrashed()
            ->where('order_id', $orderId)
            ->restore();

        // 4. Restore account_transactions for this sale
        AccountTransaction::withTrashed()
            ->where('source_type', AccountTransaction::SOURCE_SALE)
            ->where('source_id', $orderId)
            ->restore();

        // 5. Restore stock_logs for this sale
        StockLog::withTrashed()
            ->where('source_type', 'sale')
            ->where('source_id', (string) $orderId)
            ->restore();

        // 6. Re-apply stock: decrement product_store (reverse of delete which incremented)
        $order->load(['orderDetails']);
        foreach ($order->orderDetails as $orderDetail) {
            $qty = (int) $orderDetail->quantity;
            if ($qty <= 0) {
                continue;
            }
            Product::withoutGlobalScope('shop')
                ->where('id', $orderDetail->product_id)
                ->decrement('product_store', $qty);
        }

        // 7. Sync customer credit from ledger
        if ($order->customer_id) {
            $balance = $this->ledgerBalanceService->getCustomerBalance(
                (int) $order->customer_id,
                $order->shop_id
            );
            Customer::where('id', $order->customer_id)->update(['credit_amount' => $balance]);
        }
    }

    /**
     * Restore a soft-deleted child purchase (shop transfer) and its related records.
     */
    private function restoreChildPurchase(Purchase $purchase): void
    {
        $purchaseId = (int) $purchase->id;

        // Restore purchase_no (strip DEL- prefix if present)
        $purchaseNo = $purchase->purchase_no;
        if ($purchaseNo && str_starts_with($purchaseNo, 'DEL-')) {
            $prefix = 'DEL-' . $purchase->id . '-';
            if (str_starts_with($purchaseNo, $prefix)) {
                $purchase->purchase_no = substr($purchaseNo, strlen($prefix));
                $purchase->save();
            }
        }

        // Restore purchase record
        $purchase->restore();

        // Restore purchase_details
        PurchaseDetail::withTrashed()
            ->where('purchase_id', $purchaseId)
            ->restore();

        // Restore purchase payment logs (if they were soft-deleted; deletion flow may not touch them)
        PurchasePaymentLog::withTrashed()
            ->where('purchase_id', $purchaseId)
            ->restore();

        // Restore stock_logs for this purchase
        StockLog::withTrashed()
            ->where('source_type', 'purchase')
            ->where('source_id', (string) $purchaseId)
            ->restore();

        // Restore account_transactions for this purchase (purchase + purchase_payment)
        $paymentLogIds = PurchasePaymentLog::where('purchase_id', $purchaseId)->pluck('id')->toArray();
        AccountTransaction::withTrashed()
            ->where('shop_id', $purchase->shop_id)
            ->where(function ($q) use ($purchaseId, $paymentLogIds) {
                $q->where(function ($q2) use ($purchaseId) {
                    $q2->where('source_type', AccountTransaction::SOURCE_PURCHASE)
                        ->where('source_id', $purchaseId);
                });
                if (count($paymentLogIds) > 0) {
                    $q->orWhere(function ($q2) use ($paymentLogIds) {
                        $q2->where('source_type', AccountTransaction::SOURCE_PURCHASE_PAYMENT)
                            ->whereIn('source_id', $paymentLogIds);
                    });
                }
            })
            ->restore();

        // Re-apply stock: increment product_store for each purchase detail (reverse of delete)
        $purchase->load(['purchaseDetails']);
        foreach ($purchase->purchaseDetails as $detail) {
            $qty = (int) ($detail->quantity ?? 0);
            if ($qty <= 0) {
                continue;
            }
            Product::withoutGlobalScope('shop')
                ->where('id', $detail->product_id)
                ->increment('product_store', $qty);
        }

        // Sync supplier credit from ledger
        if ($purchase->supplier_id) {
            $balance = $this->ledgerBalanceService->getSupplierBalance(
                (int) $purchase->supplier_id,
                $purchase->shop_id
            );
            Supplier::withoutGlobalScope('shop')
                ->where('id', $purchase->supplier_id)
                ->update(['credit_amount' => $balance]);
        }
    }
}
