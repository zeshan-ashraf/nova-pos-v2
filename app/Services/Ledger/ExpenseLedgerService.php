<?php

namespace App\Services\Ledger;

use App\Models\AccountTransaction;
use App\Models\Expense;
use Illuminate\Support\Facades\DB;

/**
 * Records expense payments in the ledger (account_transactions).
 * Expenses are always paid at creation; one expense = one debit entry.
 * System expenses (is_system / payment_method=system) are NON-CASH and must NOT create account_transactions.
 * Append-only; duplicate protection by source_type + source_id.
 */
class ExpenseLedgerService
{
    /**
     * Record an expense in the ledger (money spent = debit).
     * Skips ledger for system expenses (non-cash); duplicate protection otherwise.
     *
     * @param Expense $expense Must have id, shop_id, activity_cost (amount), payment_method, shop_bank_id (if bank)
     * @return AccountTransaction|null The created or existing ledger entry, or null for system expenses
     */
    public function recordExpense(Expense $expense): ?AccountTransaction
    {
        // System expenses (inventory loss etc.) are non-cash; do NOT create account_transactions
        if (($expense->is_system ?? false) || ($expense->payment_method ?? '') === 'system') {
            return null;
        }

        // Duplicate protection: do not insert a second ledger row for the same expense
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

        // transaction_date per spec: expense created_at (date only)
        $transactionDate = $expense->created_at
            ? $expense->created_at->toDateString()
            : now()->toDateString();

        return AccountTransaction::create([
            'shop_id' => $shopId,
            'account_type' => $accountType,
            'account_ref_id' => $accountRefId,
            'direction' => AccountTransaction::DIRECTION_DEBIT,
            'amount' => $amount,
            'source_type' => AccountTransaction::SOURCE_EXPENSE,
            'source_id' => $expense->id,
            'description' => 'Expense: ' . ($expense->title ?? '#' . $expense->id),
            'transaction_date' => $transactionDate,
        ]);
    }
}
