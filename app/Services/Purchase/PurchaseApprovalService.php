<?php

namespace App\Services\Purchase;

use App\Models\Activity;
use App\Models\Purchase;
use App\Models\PurchaseDetail;
use Illuminate\Support\Facades\DB;

class PurchaseApprovalService
{
    /**
     * Approve landed cost for a purchase.
     *
     * Approval responsibilities:
     * - Validate landed_unit_cost for all items.
     * - Lock expenses (allocation_locked = 1).
     * - Set purchases.landed_cost_status = 'approved'.
     */
    public function approveLandedCost(int $purchaseId): void
    {
        DB::transaction(function () use ($purchaseId) {
            $purchase = Purchase::query()
                ->lockForUpdate()
                ->findOrFail($purchaseId);

            if (($purchase->purchase_status ?? '') === 'complete') {
                throw new \RuntimeException('Purchase has already been received; cannot approve landed cost.');
            }

            if (($purchase->landed_cost_status ?? 'pending') === 'approved') {
                return; // Idempotent
            }

            $totalExpense = (float) Activity::query()
                ->where('purchase_id', $purchaseId)
                ->sum('activity_cost');

            $hasAnyExpenses = Activity::query()
                ->where('purchase_id', $purchaseId)
                ->count() > 0;

            if (!$hasAnyExpenses || $totalExpense <= 0) {
                throw new \RuntimeException('Cannot approve: no purchase expenses calculated.');
            }

            $details = PurchaseDetail::query()
                ->where('purchase_id', $purchaseId)
                ->get(['id', 'product_id', 'quantity', 'unitcost', 'landed_unit_cost']);

            if ($details->isEmpty()) {
                throw new \RuntimeException('Cannot approve: purchase has no details.');
            }

            foreach ($details as $detail) {
                $landedUnitCost = $detail->landed_unit_cost;

                // Backward compatibility: if landed_unit_cost is NULL, fallback to unitcost.
                if ($landedUnitCost === null) {
                    $landedUnitCost = $detail->unitcost;
                }

                $landedUnitCost = (float) $landedUnitCost;

                if ($landedUnitCost <= 0) {
                    throw new \RuntimeException('Cannot approve: all items must have landed unit cost > 0.');
                }
            }

            // Lock expenses so they can no longer be edited.
            Activity::query()
                ->where('purchase_id', $purchaseId)
                ->update(['allocation_locked' => 1]);

            $purchase->update(['landed_cost_status' => 'approved']);
        });
    }
}

