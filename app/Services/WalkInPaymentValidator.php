<?php

namespace App\Services;

use App\Models\Customer;
use Illuminate\Validation\ValidationException;

class WalkInPaymentValidator
{
    public function isWalkIn(?Customer $customer): bool
    {
        return $customer !== null && (bool) $customer->is_walkin;
    }

    /**
     * Validate walk-in invoice payment lines (create / edit / complete hold).
     *
     * @param  array{method1: string, pay1: float|int|string, bank1: int|null, method2: ?string, pay2: float|int|string, bank2: int|null}  $payments
     *
     * @throws ValidationException
     */
    public function validateInvoicePayments(Customer $customer, float $invoiceTotal, array $payments, string $errorKey = 'pay_1'): void
    {
        if (!$this->isWalkIn($customer)) {
            return;
        }

        $method1 = (string) ($payments['method1'] ?? '');
        $method2 = ($payments['method2'] ?? null) !== null && ($payments['method2'] ?? '') !== ''
            ? (string) $payments['method2']
            : null;
        $pay1 = $this->money($payments['pay1'] ?? 0);
        $pay2 = $this->money($payments['pay2'] ?? 0);
        $bank1 = !empty($payments['bank1']) ? (int) $payments['bank1'] : null;
        $bank2 = !empty($payments['bank2']) ? (int) $payments['bank2'] : null;
        $total = $this->money($invoiceTotal);

        $this->failIf($method1 === '' || !in_array($method1, ['cash', 'bank'], true), $errorKey, 'Walk-in sales only support Cash or Bank payment.');
        $this->failIf($method1 === 'bank' && !$bank1, 'shop_bank_id_1', 'Please select a bank for Payment 1.');

        if ($method2 && $method2 === 'bank') {
            $this->failIf(!$bank2, 'shop_bank_id_2', 'Please select a bank for Payment 2.');
        }

        if ($pay2 > 0) {
            $this->failIf(!$method2, 'payment_method_2', 'Please select a payment method for Payment 2.');
            $this->failIf(!in_array($method2, ['cash', 'bank'], true), 'payment_method_2', 'Walk-in sales only support Cash or Bank payment.');
            $this->failIf($method1 === 'cash' && $method2 === 'cash', $errorKey, 'Walk-in split payment cannot use Cash for both payments.');
            $this->failIf($method1 === 'bank' && $method2 === 'bank' && $bank1 === $bank2, 'shop_bank_id_2', 'Walk-in split payment requires two different bank accounts.');
        }

        $paid = 0.0;
        if (in_array($method1, ['cash', 'bank'], true)) {
            $paid += $pay1;
        }
        if ($pay2 > 0 && $method2 && in_array($method2, ['cash', 'bank'], true)) {
            $paid += $pay2;
        }

        $this->failIf(
            $this->money($paid) !== $total,
            $errorKey,
            'Walk-in sale must be fully paid. Payment total must equal invoice total.'
        );
    }

    /**
     * Validate legacy POS cart order payment for walk-in customers.
     *
     * @throws ValidationException
     */
    public function validatePosPayment(Customer $customer, float $invoiceTotal, string $paymentStatus, float $payAmount, ?int $shopBankId = null): void
    {
        if (!$this->isWalkIn($customer)) {
            return;
        }

        if (!in_array($paymentStatus, ['HandCash', 'Bank'], true)) {
            throw ValidationException::withMessages([
                'payment_status' => ['Walk-in sales only support Cash or Bank payment.'],
            ]);
        }

        if ($paymentStatus === 'Bank' && !$shopBankId) {
            throw ValidationException::withMessages([
                'shop_bank_id' => ['Please select a bank for this payment.'],
            ]);
        }

        if ($this->money($payAmount) !== $this->money($invoiceTotal)) {
            throw ValidationException::withMessages([
                'pay' => ['Walk-in sale must be fully paid. Payment total must equal invoice total.'],
            ]);
        }
    }

    private function money(float|int|string $amount): float
    {
        return round((float) $amount, 2);
    }

    private function failIf(bool $condition, string $key, string $message): void
    {
        if ($condition) {
            throw ValidationException::withMessages([$key => [$message]]);
        }
    }
}
