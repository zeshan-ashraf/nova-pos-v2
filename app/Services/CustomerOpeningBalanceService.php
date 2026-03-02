<?php

namespace App\Services;

use App\Models\AccountTransaction;
use App\Models\Customer;
use App\Services\Ledger\LedgerBalanceService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Manages customer opening balance via account_transactions.
 * - One active (non–soft-deleted) customer_opening row per customer.
 * - Positive opening = debit (customer owes). Negative opening = credit (advance).
 * - Does not touch sale or payment transactions; does not create adjustment entries.
 */
class CustomerOpeningBalanceService
{
    public function __construct(
        protected LedgerBalanceService $balanceService
    ) {}

    /**
     * Insert opening balance entry for a newly created customer and sync credit_amount.
     * Call only when opening_balance != 0. Uses DB::transaction.
     */
    public function postOpeningBalance(Customer $customer, float $openingBalance, ?Carbon $transactionDate = null): void
    {
        if ($openingBalance == 0) {
            return;
        }

        $shopId = $customer->shop_id;
        if ($shopId === null) {
            throw new \InvalidArgumentException('Customer must have a shop_id to post opening balance.');
        }

        DB::transaction(function () use ($customer, $openingBalance, $transactionDate, $shopId) {
            $this->insertOpeningEntry(
                $shopId,
                (int) $customer->id,
                $openingBalance,
                $transactionDate ?? now()
            );

            $balance = $this->balanceService->getCustomerBalance((int) $customer->id, $shopId);
            Customer::where('id', $customer->id)->update(['credit_amount' => $balance]);
        });
    }

    /**
     * Replace existing opening balance: soft-delete current customer_opening row(s),
     * insert new one if openingBalance != 0, update customer fields, sync credit_amount.
     * Idempotent: multiple edits do not duplicate entries.
     */
    public function updateOpeningBalance(
        Customer $customer,
        float $openingBalance,
        ?Carbon $openingBalanceDate = null
    ): void {
        $shopId = $customer->shop_id;
        if ($shopId === null) {
            throw new \InvalidArgumentException('Customer must have a shop_id to update opening balance.');
        }

        DB::transaction(function () use ($customer, $openingBalance, $openingBalanceDate, $shopId) {
            $this->softDeleteExistingOpeningEntries((int) $customer->id);

            if ($openingBalance != 0) {
                $this->insertOpeningEntry(
                    $shopId,
                    (int) $customer->id,
                    $openingBalance,
                    $openingBalanceDate ?? now()
                );
            }

            Customer::where('id', $customer->id)->update([
                'opening_balance' => $openingBalance,
                'opening_balance_date' => $openingBalanceDate,
            ]);

            $balance = $this->balanceService->getCustomerBalance((int) $customer->id, $shopId);
            Customer::where('id', $customer->id)->update(['credit_amount' => $balance]);
        });
    }

    /**
     * Soft-delete any active account_transactions where source_type = customer_opening and source_id = customerId.
     */
    protected function softDeleteExistingOpeningEntries(int $customerId): void
    {
        AccountTransaction::query()
            ->where('source_type', AccountTransaction::SOURCE_CUSTOMER_OPENING)
            ->where('source_id', $customerId)
            ->delete(); // Soft delete
    }

    /**
     * Insert one opening balance row.
     * Positive amount → debit (customer owes). Negative amount → credit (advance).
     */
    protected function insertOpeningEntry(
        int $shopId,
        int $customerId,
        float $openingBalance,
        Carbon $transactionDate
    ): void {
        $amount = abs($openingBalance);
        $direction = $openingBalance > 0
            ? AccountTransaction::DIRECTION_DEBIT
            : AccountTransaction::DIRECTION_CREDIT;

        AccountTransaction::create([
            'shop_id' => $shopId,
            'account_type' => AccountTransaction::ACCOUNT_TYPE_CUSTOMER,
            'account_ref_id' => $customerId,
            'direction' => $direction,
            'amount' => $amount,
            'source_type' => AccountTransaction::SOURCE_CUSTOMER_OPENING,
            'source_id' => $customerId,
            'description' => 'Opening Balance',
            'transaction_date' => $transactionDate,
        ]);
    }
}
