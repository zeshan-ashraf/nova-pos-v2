<?php

namespace App\Services;

use App\Models\AccountTransaction;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Ledger operations for shop bank accounts.
 * Balance is NEVER stored; only derived from account_transactions.
 * Never update or delete existing ledger rows; use adjustment entries for corrections.
 */
class ShopBankLedgerService
{
    /**
     * Get current balance for a bank account (derived: sum of credits - sum of debits).
     *
     * @param int $shopId
     * @param int $shopBankId bank_shop.id
     * @return float
     */
    public function getBankCurrentBalance(int $shopId, int $shopBankId): float
    {
        $rows = AccountTransaction::query()
            ->where('shop_id', $shopId)
            ->where('account_type', AccountTransaction::ACCOUNT_TYPE_BANK)
            ->where('account_ref_id', $shopBankId)
            ->get();

        $balance = 0;
        foreach ($rows as $row) {
            if ($row->direction === AccountTransaction::DIRECTION_CREDIT) {
                $balance += (float) $row->amount;
            } else {
                $balance -= (float) $row->amount;
            }
        }
        return round($balance, 2);
    }

    /**
     * Get existing opening balance for a bank (sum of opening entries; normally one).
     *
     * @param int $shopId
     * @param int $shopBankId bank_shop.id
     * @return float
     */
    public function getBankOpeningBalance(int $shopId, int $shopBankId): float
    {
        return (float) AccountTransaction::query()
            ->where('shop_id', $shopId)
            ->where('account_type', AccountTransaction::ACCOUNT_TYPE_BANK)
            ->where('account_ref_id', $shopBankId)
            ->where('source_type', AccountTransaction::SOURCE_OPENING)
            ->sum(DB::raw("CASE WHEN direction = 'credit' THEN amount ELSE -amount END"));
    }

    /**
     * Create one opening ledger entry for a shop bank. Zero amount => no entry.
     *
     * @param int $shopId
     * @param int $shopBankId bank_shop.id
     * @param float|string $amount
     * @param \Carbon\Carbon|string $transactionDate
     * @return AccountTransaction|null
     */
    public function createBankOpeningEntry(int $shopId, int $shopBankId, $amount, $transactionDate): ?AccountTransaction
    {
        $amount = (float) $amount;
        if ($amount <= 0) {
            return null;
        }

        $date = Carbon::parse($transactionDate)->toDateString();

        return AccountTransaction::create([
            'shop_id' => $shopId,
            'account_type' => AccountTransaction::ACCOUNT_TYPE_BANK,
            'account_ref_id' => $shopBankId,
            'direction' => AccountTransaction::DIRECTION_CREDIT,
            'amount' => $amount,
            'source_type' => AccountTransaction::SOURCE_OPENING,
            'source_id' => $shopId,
            'description' => 'Bank opening balance',
            'transaction_date' => $date,
        ]);
    }

    /**
     * Insert adjustment entry to zero a bank (e.g. when bank is removed). Never delete ledger rows.
     *
     * @param int $shopId
     * @param int $shopBankId bank_shop.id
     * @param float $currentBalance positive = credit balance, we debit to zero
     * @param string $description
     * @return AccountTransaction|null
     */
    public function insertZeroingAdjustment(int $shopId, int $shopBankId, float $currentBalance, string $description = 'Bank removed from shop'): ?AccountTransaction
    {
        if (abs($currentBalance) < 0.01) {
            return null;
        }

        $direction = $currentBalance > 0
            ? AccountTransaction::DIRECTION_DEBIT
            : AccountTransaction::DIRECTION_CREDIT;
        $amount = abs($currentBalance);

        return AccountTransaction::create([
            'shop_id' => $shopId,
            'account_type' => AccountTransaction::ACCOUNT_TYPE_BANK,
            'account_ref_id' => $shopBankId,
            'direction' => $direction,
            'amount' => $amount,
            'source_type' => AccountTransaction::SOURCE_ADJUSTMENT,
            'source_id' => null,
            'description' => $description,
            'transaction_date' => now()->toDateString(),
        ]);
    }

    /**
     * Insert adjustment for opening balance correction (difference from existing opening).
     *
     * @param int $shopId
     * @param int $shopBankId bank_shop.id
     * @param float $difference new_opening - old_opening (positive => credit, negative => debit)
     * @return AccountTransaction|null
     */
    public function insertOpeningBalanceCorrection(int $shopId, int $shopBankId, float $difference): ?AccountTransaction
    {
        if (abs($difference) < 0.01) {
            return null;
        }

        $direction = $difference > 0
            ? AccountTransaction::DIRECTION_CREDIT
            : AccountTransaction::DIRECTION_DEBIT;
        $amount = abs($difference);

        return AccountTransaction::create([
            'shop_id' => $shopId,
            'account_type' => AccountTransaction::ACCOUNT_TYPE_BANK,
            'account_ref_id' => $shopBankId,
            'direction' => $direction,
            'amount' => $amount,
            'source_type' => AccountTransaction::SOURCE_ADJUSTMENT,
            'source_id' => null,
            'description' => 'Opening balance correction',
            'transaction_date' => now()->toDateString(),
        ]);
    }
}
