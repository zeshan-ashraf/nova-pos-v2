<?php

namespace App\Services\Ledger;

use App\Models\AccountTransaction;
use App\Models\Purchase;
use App\Models\PurchasePaymentLog;
use Illuminate\Support\Carbon;

/**
 * Records purchase-related cash movements in the ledger (account_transactions).
 * - Purchase is NOT an expense; it affects inventory (stock_logs) and cash when paid.
 * - PAID purchase: one debit entry (cash outflow) at creation.
 * - CREDIT purchase: no ledger entry at creation; cash flow only when payment is made later.
 * - Paying credit purchase later: one debit entry (source_type = purchase_payment).
 * Append-only; duplicate protection by source_type + source_id.
 */
class PurchaseLedgerService
{
    /**
     * Record paid amount at purchase creation (cash/bank outflow).
     * Call only when payAmount > 0 and payment is cash/bank/cheque (not credit-only).
     * Does NOT insert for credit-only purchases (no cash movement).
     *
     * @param Purchase $purchase Must have id, shop_id, purchase_date, total, pay, payment_status
     * @param float $payAmount Amount paid at creation (debit)
     * @param string|null $shopBankId bank_shop.id when payment_status is bank/cheque, null for cash
     * @return AccountTransaction
     */
    public function recordPurchasePayment(
        Purchase $purchase,
        float $payAmount,
        ?string $shopBankId = null
    ): AccountTransaction {
        if ($payAmount <= 0) {
            throw new \InvalidArgumentException('Purchase ledger requires positive pay amount.');
        }

        $this->guardDuplicate(AccountTransaction::SOURCE_PURCHASE, $purchase->id);

        $paymentStatus = strtolower((string) ($purchase->payment_status ?? ''));
        $isBank = in_array($paymentStatus, ['bank', 'cheque'], true);
        $accountType = $isBank ? AccountTransaction::ACCOUNT_TYPE_BANK : AccountTransaction::ACCOUNT_TYPE_CASH;
        $accountRefId = $isBank && $shopBankId !== null && $shopBankId !== '' ? (int) $shopBankId : null;

        $transactionDate = $purchase->purchase_date
            ? Carbon::parse($purchase->purchase_date)->toDateString()
            : now()->toDateString();

        return AccountTransaction::create([
            'shop_id' => $purchase->shop_id,
            'account_type' => $accountType,
            'account_ref_id' => $accountRefId,
            'direction' => AccountTransaction::DIRECTION_DEBIT,
            'amount' => $payAmount,
            'source_type' => AccountTransaction::SOURCE_PURCHASE,
            'source_id' => $purchase->id,
            'description' => 'Inventory purchase',
            'transaction_date' => $transactionDate,
        ]);
    }

    /**
     * Record a later payment against a credit purchase (cash outflow).
     * Call when user pays an existing purchase (e.g. from supplier ledger).
     * DO NOT touch stock_logs.
     *
     * @param PurchasePaymentLog $paymentLog Must have purchase (with shop_id), amount_paid, shop_bank_id (if bank)
     * @param string $transactionDate Y-m-d date of payment
     * @return AccountTransaction
     */
    public function recordPurchasePaymentLog(
        PurchasePaymentLog $paymentLog,
        string $transactionDate
    ): AccountTransaction {
        $paymentLog->loadMissing('purchase');
        $purchase = $paymentLog->purchase;
        if (!$purchase || !$purchase->shop_id) {
            throw new \InvalidArgumentException('Payment log must belong to a purchase with shop_id.');
        }
        if ($paymentLog->amount_paid <= 0) {
            throw new \InvalidArgumentException('Purchase payment ledger requires positive amount_paid.');
        }

        // One ledger row per payment log (source_id = payment log id for purchase_payment)
        $existing = AccountTransaction::query()
            ->where('source_type', AccountTransaction::SOURCE_PURCHASE_PAYMENT)
            ->where('source_id', $paymentLog->id)
            ->first();
        if ($existing) {
            return $existing;
        }

        $isBank = $paymentLog->shop_bank_id > 0;
        $accountType = $isBank ? AccountTransaction::ACCOUNT_TYPE_BANK : AccountTransaction::ACCOUNT_TYPE_CASH;
        $accountRefId = $isBank ? (int) $paymentLog->shop_bank_id : null;

        return AccountTransaction::create([
            'shop_id' => $purchase->shop_id,
            'account_type' => $accountType,
            'account_ref_id' => $accountRefId,
            'direction' => AccountTransaction::DIRECTION_DEBIT,
            'amount' => $paymentLog->amount_paid,
            'source_type' => AccountTransaction::SOURCE_PURCHASE_PAYMENT,
            'source_id' => $paymentLog->id,
            'description' => 'Payment for purchase',
            'transaction_date' => $transactionDate,
        ]);
    }

    /**
     * Remove ledger entries for a purchase (on purchase delete).
     * Deletes rows where source_type in (purchase, purchase_payment) and source_id = purchase.id
     * or for purchase_payment we need to delete by payment log ids. Actually:
     - source_type=purchase, source_id=purchase.id → one row (initial payment at creation)
     - source_type=purchase_payment, source_id=payment_log.id → one row per later payment
     * So we need to delete: (source_type=purchase AND source_id=purchase.id) OR (source_type=purchase_payment AND source_id IN (payment log ids)).
     */
    public function reverseForPurchase(Purchase $purchase): void
    {
        $paymentLogIds = $purchase->paymentLogs()->pluck('id')->toArray();

        AccountTransaction::query()
            ->where('shop_id', $purchase->shop_id)
            ->where(function ($q) use ($purchase, $paymentLogIds) {
                $q->where(function ($q2) use ($purchase) {
                    $q2->where('source_type', AccountTransaction::SOURCE_PURCHASE)
                       ->where('source_id', $purchase->id);
                });
                if (count($paymentLogIds) > 0) {
                    $q->orWhere(function ($q2) use ($paymentLogIds) {
                        $q2->where('source_type', AccountTransaction::SOURCE_PURCHASE_PAYMENT)
                           ->whereIn('source_id', $paymentLogIds);
                    });
                }
            })
            ->delete();
    }

    private function guardDuplicate(string $sourceType, $sourceId): void
    {
        $existing = AccountTransaction::query()
            ->where('source_type', $sourceType)
            ->where('source_id', $sourceId)
            ->first();
        if ($existing) {
            throw new \InvalidArgumentException("Ledger entry already exists for {$sourceType}:{$sourceId}.");
        }
    }
}
