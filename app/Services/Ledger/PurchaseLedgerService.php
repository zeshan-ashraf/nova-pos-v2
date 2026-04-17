<?php

namespace App\Services\Ledger;

use App\Models\AccountTransaction;
use App\Models\Purchase;
use App\Models\PurchasePaymentLog;
use Illuminate\Support\Carbon;

/**
 * Double-entry ledger for purchases.
 * Rule: Purchase (inventory) = debit; Supplier (AP) = credit (we owe more), debit (we owe less); Cash/Bank = credit when paying.
 * - Credit purchase: purchase debit (total), supplier credit (due).
 * - Paid at creation: purchase debit (total), cash/bank credit (pay).
 * - Partial: purchase debit (total), cash/bank credit (pay), supplier credit (due).
 * - Later payment: supplier debit, cash/bank credit.
 */
class PurchaseLedgerService
{
    /**
     * Record purchase DEBIT (inventory/cost increase). Call once per purchase inside same DB transaction as Purchase::create.
     */
    public function recordPurchaseDebit(Purchase $purchase): ?AccountTransaction
    {
        if (!$purchase->shop_id) {
            return null;
        }

        $amount = (float) $purchase->total;
        if ($amount <= 0) {
            return null;
        }

        $existing = AccountTransaction::query()
            ->where('account_type', AccountTransaction::ACCOUNT_TYPE_PURCHASE)
            ->where('source_type', AccountTransaction::SOURCE_PURCHASE)
            ->where('source_id', $purchase->id)
            ->where('shop_id', $purchase->shop_id)
            ->first();
        if ($existing) {
            return $existing;
        }

        $transactionDate = $purchase->purchase_date
            ? Carbon::parse($purchase->purchase_date)->toDateString()
            : now()->toDateString();

        return AccountTransaction::create([
            'shop_id' => $purchase->shop_id,
            'account_type' => AccountTransaction::ACCOUNT_TYPE_PURCHASE,
            'account_ref_id' => null,
            'direction' => AccountTransaction::DIRECTION_DEBIT,
            'amount' => $amount,
            'source_type' => AccountTransaction::SOURCE_PURCHASE,
            'source_id' => $purchase->id,
            'description' => 'Purchase ' . ($purchase->purchase_no ?? (string) $purchase->id),
            'transaction_date' => $transactionDate,
        ]);
    }

    /**
     * Record supplier CREDIT when a purchase is created (amount we owe = due only).
     * Call once per purchase when due > 0, inside same DB transaction as Purchase::create.
     */
    public function recordPurchaseSupplierCredit(Purchase $purchase): ?AccountTransaction
    {
        if (!$purchase->supplier_id || !$purchase->shop_id) {
            return null;
        }

        $due = (float) ($purchase->due ?? 0);
        if ($due <= 0) {
            return null;
        }

        $existing = AccountTransaction::query()
            ->where('account_type', AccountTransaction::ACCOUNT_TYPE_SUPPLIER)
            ->where('account_ref_id', $purchase->supplier_id)
            ->where('source_type', AccountTransaction::SOURCE_PURCHASE)
            ->where('source_id', $purchase->id)
            ->first();
        if ($existing) {
            return $existing;
        }

        $transactionDate = $purchase->purchase_date
            ? Carbon::parse($purchase->purchase_date)->toDateString()
            : now()->toDateString();

        return AccountTransaction::create([
            'shop_id' => $purchase->shop_id,
            'account_type' => AccountTransaction::ACCOUNT_TYPE_SUPPLIER,
            'account_ref_id' => $purchase->supplier_id,
            'direction' => AccountTransaction::DIRECTION_CREDIT,
            'amount' => $due,
            'source_type' => AccountTransaction::SOURCE_PURCHASE,
            'source_id' => $purchase->id,
            'description' => 'Purchase ' . ($purchase->purchase_no ?? (string) $purchase->id),
            'transaction_date' => $transactionDate,
        ]);
    }

    /**
     * Record paid amount at purchase creation: cash/bank CREDIT only (money out).
     * Call only when payAmount > 0. No supplier debit at creation (liability is already only "due").
     */
    public function recordPurchasePayment(
        Purchase $purchase,
        float $payAmount,
        ?string $shopBankId = null
    ): AccountTransaction {
        if ($payAmount <= 0) {
            throw new \InvalidArgumentException('Purchase ledger requires positive pay amount.');
        }

        $this->guardDuplicateCashBankPurchase($purchase->id);

        $transactionDate = $purchase->purchase_date
            ? Carbon::parse($purchase->purchase_date)->toDateString()
            : now()->toDateString();

        $paymentStatus = strtolower((string) ($purchase->payment_status ?? ''));
        $isBank = in_array($paymentStatus, ['bank', 'cheque'], true);
        $accountType = $isBank ? AccountTransaction::ACCOUNT_TYPE_BANK : AccountTransaction::ACCOUNT_TYPE_CASH;
        $accountRefId = $isBank && $shopBankId !== null && $shopBankId !== '' ? (int) $shopBankId : null;

        // Cash/Bank CREDIT — money paid out (asset decrease)
        return AccountTransaction::create([
            'shop_id' => $purchase->shop_id,
            'account_type' => $accountType,
            'account_ref_id' => $accountRefId,
            'direction' => AccountTransaction::DIRECTION_CREDIT,
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

        // Cash/Bank CREDIT — money paid out (asset decrease); one row per payment log
        $bankOrCashEntry = AccountTransaction::query()
            ->whereIn('account_type', [AccountTransaction::ACCOUNT_TYPE_CASH, AccountTransaction::ACCOUNT_TYPE_BANK])
            ->where('source_type', AccountTransaction::SOURCE_PURCHASE_PAYMENT)
            ->where('source_id', $paymentLog->id)
            ->first();
        if (!$bankOrCashEntry) {
            $isBank = $paymentLog->shop_bank_id > 0;
            $accountType = $isBank ? AccountTransaction::ACCOUNT_TYPE_BANK : AccountTransaction::ACCOUNT_TYPE_CASH;
            $accountRefId = $isBank ? (int) $paymentLog->shop_bank_id : null;

            $bankOrCashEntry = AccountTransaction::create([
                'shop_id' => $purchase->shop_id,
                'account_type' => $accountType,
                'account_ref_id' => $accountRefId,
                'direction' => AccountTransaction::DIRECTION_CREDIT,
                'amount' => $paymentLog->amount_paid,
                'source_type' => AccountTransaction::SOURCE_PURCHASE_PAYMENT,
                'source_id' => $paymentLog->id,
                'description' => 'Payment for purchase',
                'transaction_date' => $transactionDate,
            ]);
        }

        $this->recordSupplierDebitForPayment($purchase, (float) $paymentLog->amount_paid, $transactionDate, $paymentLog->id, AccountTransaction::SOURCE_PURCHASE_PAYMENT);

        return $bankOrCashEntry;
    }

    /**
     * Insert supplier debit (reduces amount we owe). Duplicate-safe per source_type + source_id.
     */
    private function recordSupplierDebitForPayment(Purchase $purchase, float $amount, string $transactionDate, $sourceId, string $sourceType): void
    {
        if (!$purchase->supplier_id || $amount <= 0) {
            return;
        }

        $exists = AccountTransaction::query()
            ->where('account_type', AccountTransaction::ACCOUNT_TYPE_SUPPLIER)
            ->where('source_type', $sourceType)
            ->where('source_id', $sourceId)
            ->exists();
        if ($exists) {
            return;
        }

        AccountTransaction::create([
            'shop_id' => $purchase->shop_id,
            'account_type' => AccountTransaction::ACCOUNT_TYPE_SUPPLIER,
            'account_ref_id' => $purchase->supplier_id,
            'direction' => AccountTransaction::DIRECTION_DEBIT,
            'amount' => $amount,
            'source_type' => $sourceType,
            'source_id' => $sourceId,
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

        // Soft-delete so historical rows remain in DB but balances exclude them (SoftDeletes on AccountTransaction).
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

    /**
     * Recreate all purchase-related ledger rows after totals/payments/details change.
     *
     * We reverse first (see reverseForPurchase) because purchase debit, supplier AP, and cash/bank lines
     * are keyed by amounts derived from the purchase; updating in place would risk duplicates or drift.
     * Order: purchase debit → supplier credit (due) → first payment uses source "purchase"; further
     * payments use source "purchase_payment" (matches store + later payment flows).
     */
    public function createForPurchase(Purchase $purchase): void
    {
        $purchase->loadMissing('paymentLogs');
        $purchase->refresh();

        $this->recordPurchaseDebit($purchase);
        $this->recordPurchaseSupplierCredit($purchase);

        $logs = $purchase->paymentLogs()->orderBy('id')->get();
        $transactionDate = $purchase->purchase_date
            ? Carbon::parse($purchase->purchase_date)->toDateString()
            : now()->toDateString();

        foreach ($logs as $index => $log) {
            $amount = (float) ($log->amount_paid ?? 0);
            if ($amount <= 0) {
                continue;
            }
            if ($index === 0) {
                $shopBankId = $log->shop_bank_id !== null && $log->shop_bank_id !== ''
                    ? (string) $log->shop_bank_id
                    : null;
                $this->recordPurchasePayment($purchase, $amount, $shopBankId);
            } else {
                $this->recordPurchasePaymentLog($log, $transactionDate);
            }
        }
    }

    /** Guard: only one bank/cash entry per purchase (source_type=purchase, source_id=purchase.id). */
    private function guardDuplicateCashBankPurchase($purchaseId): void
    {
        $existing = AccountTransaction::query()
            ->whereIn('account_type', [AccountTransaction::ACCOUNT_TYPE_CASH, AccountTransaction::ACCOUNT_TYPE_BANK])
            ->where('source_type', AccountTransaction::SOURCE_PURCHASE)
            ->where('source_id', $purchaseId)
            ->first();
        if ($existing) {
            throw new \InvalidArgumentException("Ledger entry already exists for purchase:{$purchaseId}.");
        }
    }
}
