<?php

namespace App\Services\Ledger;

use App\Models\AccountTransaction;
use App\Models\Activity;

/**
 * Double-entry ledger for expenses.
 * Rule: Expense = debit (increase expense); Cash/Bank = credit (money out).
 * System expenses (is_system / payment_method=system) are NON-CASH and must NOT create account_transactions.
 */
class ExpenseLedgerService
{
    /**
     * Record expense (double-entry): (1) expense debit, (2) cash/bank credit.
     * Skips ledger for system expenses (non-cash); duplicate protection otherwise.
     *
     * @param Activity $expense Must have id, shop_id, activity_cost (amount), payment_method, shop_bank_id (if bank)
     * @return AccountTransaction|null The expense debit entry, or null for system expenses
     */
    public function recordExpense(Activity $expense): ?AccountTransaction
    {
        // System expenses (inventory loss etc.) are non-cash; do NOT create account_transactions
        if (($expense->is_system ?? false) || ($expense->payment_method ?? '') === 'system') {
            return null;
        }

        // Duplicate protection: do not insert a second set of ledger rows for the same expense
        $existing = AccountTransaction::query()
            ->where('source_type', AccountTransaction::SOURCE_EXPENSE)
            ->where('source_id', $expense->id)
            ->first();

        if ($existing) {
            return $existing;
        }

        $amount = (float) $expense->activity_cost;
        if ($amount <= 0) {
            throw new \InvalidArgumentException('Expense ledger requires positive amount (activity_cost).');
        }

        $shopId = $expense->shop_id;
        if (!$shopId) {
            throw new \InvalidArgumentException('Expense must have a shop_id for ledger recording.');
        }

        $paymentMethod = $expense->payment_method ?? 'cash';
        $isBank = in_array(strtolower((string) $paymentMethod), ['bank', 'cheque'], true);
        $accountType = $isBank ? AccountTransaction::ACCOUNT_TYPE_BANK : AccountTransaction::ACCOUNT_TYPE_CASH;
        $accountRefId = $isBank && $expense->shop_bank_id ? $expense->shop_bank_id : null;

        $transactionDate = $expense->created_at
            ? $expense->created_at->toDateString()
            : now()->toDateString();

        $description = 'Expense: ' . ($expense->title ?? '#' . $expense->id);

        // Row 1: Expense DEBIT — increase expense (double-entry: offsets cash/bank credit)
        $expenseEntry = AccountTransaction::create([
            'shop_id' => $shopId,
            'account_type' => AccountTransaction::ACCOUNT_TYPE_EXPENSE,
            'account_ref_id' => null,
            'direction' => AccountTransaction::DIRECTION_DEBIT,
            'amount' => $amount,
            'source_type' => AccountTransaction::SOURCE_EXPENSE,
            'source_id' => $expense->id,
            'description' => $description,
            'transaction_date' => $transactionDate,
        ]);

        // Row 2: Cash/Bank CREDIT — money paid out (asset decrease)
        AccountTransaction::create([
            'shop_id' => $shopId,
            'account_type' => $accountType,
            'account_ref_id' => $accountRefId,
            'direction' => AccountTransaction::DIRECTION_CREDIT,
            'amount' => $amount,
            'source_type' => AccountTransaction::SOURCE_EXPENSE,
            'source_id' => $expense->id,
            'description' => $description,
            'transaction_date' => $transactionDate,
        ]);

        return $expenseEntry;
    }
}
