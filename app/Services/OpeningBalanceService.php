<?php

namespace App\Services;

use App\Exceptions\DuplicateOpeningBalanceException;
use App\Models\AccountTransaction;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class OpeningBalanceService
{
    /**
     * Set opening balance for the shop's cash account.
     * Only one opening entry per cash account is allowed.
     *
     * @param int $shopId
     * @param float|string $amount
     * @param \Carbon\Carbon|string $date
     * @return AccountTransaction
     * @throws ValidationException
     * @throws DuplicateOpeningBalanceException
     */
    public function setCashOpeningBalance(int $shopId, $amount, $date): AccountTransaction
    {
        $this->validateCashInputs($shopId, $amount, $date);

        return DB::transaction(function () use ($shopId, $amount, $date) {
            $this->rejectDuplicateCashOpening($shopId);

            $transactionDate = Carbon::parse($date)->toDateString();

            return AccountTransaction::create([
                'shop_id' => $shopId,
                'account_type' => AccountTransaction::ACCOUNT_TYPE_CASH,
                'account_ref_id' => null,
                'direction' => AccountTransaction::DIRECTION_CREDIT,
                'amount' => $amount,
                'source_type' => AccountTransaction::SOURCE_OPENING,
                'source_id' => null,
                'description' => 'Cash opening balance',
                'transaction_date' => $transactionDate,
            ]);
        });
    }

    /**
     * Set opening balance for a specific bank account (shop_bank / bank_shop row).
     * Only one opening entry per bank account is allowed.
     *
     * @param int $shopId
     * @param int $shopBankId bank_shop.id (pivot row id)
     * @param float|string $amount
     * @param \Carbon\Carbon|string $date
     * @return AccountTransaction
     * @throws ValidationException
     * @throws DuplicateOpeningBalanceException
     */
    public function setBankOpeningBalance(int $shopId, int $shopBankId, $amount, $date): AccountTransaction
    {
        $this->validateBankInputs($shopId, $shopBankId, $amount, $date);

        return DB::transaction(function () use ($shopId, $shopBankId, $amount, $date) {
            $this->rejectDuplicateBankOpening($shopId, $shopBankId);

            $transactionDate = Carbon::parse($date)->toDateString();

            return AccountTransaction::create([
                'shop_id' => $shopId,
                'account_type' => AccountTransaction::ACCOUNT_TYPE_BANK,
                'account_ref_id' => $shopBankId,
                'direction' => AccountTransaction::DIRECTION_CREDIT,
                'amount' => $amount,
                'source_type' => AccountTransaction::SOURCE_OPENING,
                'source_id' => null,
                'description' => 'Bank opening balance',
                'transaction_date' => $transactionDate,
            ]);
        });
    }

    /**
     * Validate inputs for cash opening balance.
     *
     * @throws ValidationException
     */
    protected function validateCashInputs(int $shopId, $amount, $date): void
    {
        $validator = Validator::make(
            [
                'shop_id' => $shopId,
                'amount' => $amount,
                'date' => $date,
            ],
            [
                'shop_id' => ['required', 'integer', 'exists:shops,id'],
                'amount' => ['required', 'numeric', 'min:0'],
                'date' => ['required', 'date'],
            ],
            [
                'shop_id.exists' => 'The selected shop does not exist.',
                'amount.min' => 'The amount must be at least 0.',
            ]
        );

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }
    }

    /**
     * Validate inputs for bank opening balance.
     *
     * @throws ValidationException
     */
    protected function validateBankInputs(int $shopId, int $shopBankId, $amount, $date): void
    {
        $validator = Validator::make(
            [
                'shop_id' => $shopId,
                'shop_bank_id' => $shopBankId,
                'amount' => $amount,
                'date' => $date,
            ],
            [
                'shop_id' => ['required', 'integer', 'exists:shops,id'],
                'shop_bank_id' => [
                    'required',
                    'integer',
                    'exists:bank_shop,id',
                    function (string $attribute, int $value, \Closure $fail) use ($shopId) {
                        $exists = DB::table('bank_shop')
                            ->where('id', $value)
                            ->where('shop_id', $shopId)
                            ->exists();
                        if (!$exists) {
                            $fail('The selected bank account does not belong to this shop.');
                        }
                    },
                ],
                'amount' => ['required', 'numeric', 'min:0'],
                'date' => ['required', 'date'],
            ],
            [
                'shop_id.exists' => 'The selected shop does not exist.',
                'shop_bank_id.exists' => 'The selected bank account does not exist.',
                'amount.min' => 'The amount must be at least 0.',
            ]
        );

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }
    }

    /**
     * Reject if cash opening balance already exists for this shop.
     *
     * @throws DuplicateOpeningBalanceException
     */
    protected function rejectDuplicateCashOpening(int $shopId): void
    {
        $exists = AccountTransaction::query()
            ->where('shop_id', $shopId)
            ->where('account_type', AccountTransaction::ACCOUNT_TYPE_CASH)
            ->whereNull('account_ref_id')
            ->where('source_type', AccountTransaction::SOURCE_OPENING)
            ->exists();

        if ($exists) {
            throw DuplicateOpeningBalanceException::forCash($shopId);
        }
    }

    /**
     * Reject if bank opening balance already exists for this shop_bank.
     *
     * @throws DuplicateOpeningBalanceException
     */
    protected function rejectDuplicateBankOpening(int $shopId, int $shopBankId): void
    {
        $exists = AccountTransaction::query()
            ->where('shop_id', $shopId)
            ->where('account_type', AccountTransaction::ACCOUNT_TYPE_BANK)
            ->where('account_ref_id', $shopBankId)
            ->where('source_type', AccountTransaction::SOURCE_OPENING)
            ->exists();

        if ($exists) {
            throw DuplicateOpeningBalanceException::forBank($shopId, $shopBankId);
        }
    }
}
