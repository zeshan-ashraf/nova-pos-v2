<?php

namespace App\Services;

use App\Models\Supplier;
use Illuminate\Support\Facades\DB;

class SupplierCreditService
{
    /**
     * Increase supplier's credit balance by pending amount (e.g., new credit purchase).
     * Returns the new credit_amount.
     */
    public function addPending(Supplier $supplier, float $pendingAmount): float
    {
        if ($pendingAmount <= 0) {
            return $supplier->credit_amount ?? 0;
        }

        // Lock row to avoid race conditions when multiple payments/purchases touch the same supplier.
        return DB::transaction(function () use ($supplier, $pendingAmount) {
            /** @var Supplier $locked */
            $locked = Supplier::whereKey($supplier->getKey())->lockForUpdate()->firstOrFail();
            $locked->credit_amount = ($locked->credit_amount ?? 0) + $pendingAmount;
            $locked->save();

            return $locked->credit_amount;
        });
    }

    /**
     * Decrease supplier's credit balance when payment is applied.
     * Will not allow credit_amount to go negative.
     * Returns the new credit_amount.
     */
    public function applyPayment(Supplier $supplier, float $paymentAmount): float
    {
        if ($paymentAmount <= 0) {
            return $supplier->credit_amount ?? 0;
        }

        return DB::transaction(function () use ($supplier, $paymentAmount) {
            /** @var Supplier $locked */
            $locked = Supplier::whereKey($supplier->getKey())->lockForUpdate()->firstOrFail();
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
    public function exceedsLimit(Supplier $supplier, float $pendingAmount): bool
    {
        $limit = $supplier->credit_limit ?? 0;
        if ($limit <= 0) {
            return false;
        }

        $current = $supplier->credit_amount ?? 0;
        return ($current + max(0, $pendingAmount)) > $limit;
    }

    /**
     * Decrease supplier's credit balance by pending amount (reverse of addPending).
     * Used when deleting a purchase that had a due amount.
     * Returns the new credit_amount.
     */
    public function removePending(Supplier $supplier, float $pendingAmount): float
    {
        if ($pendingAmount <= 0) {
            return $supplier->credit_amount ?? 0;
        }

        return DB::transaction(function () use ($supplier, $pendingAmount) {
            /** @var Supplier $locked */
            $locked = Supplier::whereKey($supplier->getKey())->lockForUpdate()->firstOrFail();
            $newAmount = max(0, ($locked->credit_amount ?? 0) - $pendingAmount);
            $locked->credit_amount = $newAmount;
            $locked->save();

            return $locked->credit_amount;
        });
    }

    /**
     * Increase supplier's credit balance by payment amount (reverse of applyPayment).
     * Used when deleting a purchase to reverse payments that were made.
     * Returns the new credit_amount.
     */
    public function reversePayment(Supplier $supplier, float $paymentAmount): float
    {
        if ($paymentAmount <= 0) {
            return $supplier->credit_amount ?? 0;
        }

        return DB::transaction(function () use ($supplier, $paymentAmount) {
            /** @var Supplier $locked */
            $locked = Supplier::whereKey($supplier->getKey())->lockForUpdate()->firstOrFail();
            $locked->credit_amount = ($locked->credit_amount ?? 0) + $paymentAmount;
            $locked->save();

            return $locked->credit_amount;
        });
    }
}
