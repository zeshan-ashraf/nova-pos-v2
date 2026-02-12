<?php

namespace App\Services\Ledger;

use App\Models\AccountTransaction;

/**
 * Balance calculation from account_transactions (double-entry).
 * Customer balance = SUM(debit) - SUM(credit): positive = customer owes us, negative = advance from customer.
 * Supplier balance = SUM(credit) - SUM(debit): positive = we owe supplier, negative = advance paid to supplier.
 */
class LedgerBalanceService
{
    /**
     * Customer balance = SUM(debit - credit) where account_type='customer' and account_ref_id=customer_id.
     * Positive = customer owes us. Negative = advance (we owe them or credit balance).
     */
    public function getCustomerBalance(int $customerId, ?int $shopId = null): float
    {
        $query = AccountTransaction::query()
            ->where('account_type', AccountTransaction::ACCOUNT_TYPE_CUSTOMER)
            ->where('account_ref_id', $customerId);

        if ($shopId !== null) {
            $query->where('shop_id', $shopId);
        }

        $raw = $query->selectRaw(
            "SUM(CASE WHEN direction = 'debit' THEN amount ELSE -amount END) as balance"
        )->value('balance');

        return (float) ($raw ?? 0);
    }

    /**
     * Supplier balance = SUM(credit - debit) where account_type='supplier' and account_ref_id=supplier_id.
     * Positive = we owe supplier. Negative = advance (they owe us or we overpaid).
     */
    public function getSupplierBalance(int $supplierId, ?int $shopId = null): float
    {
        $query = AccountTransaction::query()
            ->where('account_type', AccountTransaction::ACCOUNT_TYPE_SUPPLIER)
            ->where('account_ref_id', $supplierId);

        if ($shopId !== null) {
            $query->where('shop_id', $shopId);
        }

        $raw = $query->selectRaw(
            "SUM(CASE WHEN direction = 'credit' THEN amount ELSE -amount END) as balance"
        )->value('balance');

        return (float) ($raw ?? 0);
    }
}
