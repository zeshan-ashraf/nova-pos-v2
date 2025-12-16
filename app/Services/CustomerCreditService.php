<?php

namespace App\Services;

use App\Models\Customer;
use Illuminate\Support\Facades\DB;

class CustomerCreditService
{
    /**
     * Increase customer's credit balance by pending amount (e.g., new credit order).
     * Returns the new credit_amount.
     */
    public function addPending(Customer $customer, float $pendingAmount): float
    {
        if ($pendingAmount <= 0) {
            return $customer->credit_amount;
        }

        // Lock row to avoid race conditions when multiple payments/orders touch the same customer.
        return DB::transaction(function () use ($customer, $pendingAmount) {
            /** @var Customer $locked */
            $locked = Customer::whereKey($customer->getKey())->lockForUpdate()->firstOrFail();
            $locked->credit_amount = ($locked->credit_amount ?? 0) + $pendingAmount;
            $locked->save();

            return $locked->credit_amount;
        });
    }

    /**
     * Decrease customer's credit balance when payment is applied.
     * Will not allow credit_amount to go negative.
     * Returns the new credit_amount.
     */
    public function applyPayment(Customer $customer, float $paymentAmount): float
    {
        if ($paymentAmount <= 0) {
            return $customer->credit_amount;
        }

        return DB::transaction(function () use ($customer, $paymentAmount) {
            /** @var Customer $locked */
            $locked = Customer::whereKey($customer->getKey())->lockForUpdate()->firstOrFail();
            $newAmount = max(0, ($locked->credit_amount ?? 0) - $paymentAmount);
            $locked->credit_amount = $newAmount;
            $locked->save();

            return $locked->credit_amount;
        });
    }

    /**
     * Check if adding a pending amount would cross the credit limit.
     * Returns boolean for warning purposes.
     */
    public function exceedsLimit(Customer $customer, float $pendingAmount): bool
    {
        $limit = $customer->credit_limit ?? 0;
        if ($limit <= 0) {
            return false;
        }

        $current = $customer->credit_amount ?? 0;
        return ($current + max(0, $pendingAmount)) > $limit;
    }
}

