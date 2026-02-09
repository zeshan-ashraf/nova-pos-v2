<?php

namespace App\Exceptions;

use Exception;

class DuplicateOpeningBalanceException extends Exception
{
    /**
     * Create for cash account.
     */
    public static function forCash(int $shopId): self
    {
        return new self("Opening balance for cash account already exists for shop [{$shopId}]. Only one opening entry per account is allowed.");
    }

    /**
     * Create for bank account.
     */
    public static function forBank(int $shopId, int $shopBankId): self
    {
        return new self("Opening balance for bank account [{$shopBankId}] already exists for shop [{$shopId}]. Only one opening entry per account is allowed.");
    }
}
