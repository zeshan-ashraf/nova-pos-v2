<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class InvoicePaymentValidator
{
    /**
     * Require shop bank account when payment method is Bank or Cheque.
     *
     * @throws ValidationException
     */
    public function validateBankSelections(
        string $paymentMethod1,
        ?int $shopBankId1,
        ?string $paymentMethod2,
        ?int $shopBankId2,
        ?int $shopId = null
    ): void {
        if (in_array($paymentMethod1, ['bank', 'cheque'], true)) {
            if (!$shopBankId1) {
                throw ValidationException::withMessages([
                    'shop_bank_id_1' => ['Please select a bank for Payment 1.'],
                ]);
            }
            $this->assertBankBelongsToShop($shopBankId1, $shopId, 'shop_bank_id_1');
        }

        if ($paymentMethod2 && in_array($paymentMethod2, ['bank', 'cheque'], true)) {
            if (!$shopBankId2) {
                throw ValidationException::withMessages([
                    'shop_bank_id_2' => ['Please select a bank for Payment 2.'],
                ]);
            }
            $this->assertBankBelongsToShop($shopBankId2, $shopId, 'shop_bank_id_2');
        }
    }

    private function assertBankBelongsToShop(int $shopBankId, ?int $shopId, string $field): void
    {
        if (!$shopId) {
            return;
        }

        if (!DB::table('bank_shop')->where('id', $shopBankId)->where('shop_id', $shopId)->exists()) {
            throw ValidationException::withMessages([
                $field => ['The selected bank is not valid for your shop.'],
            ]);
        }
    }
}
