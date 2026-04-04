<?php

namespace App\Services\Purchase;

use App\Models\Activity;
use App\Models\Purchase;
use App\Models\PurchaseDetail;
use Illuminate\Support\Facades\DB;

class PurchaseLandedCostService
{
    /**
     * Calculate landed cost allocation using expense ratio on product value.
     *
     * Writes allocated_expense, landed_unit_cost, landed_total into purchase_details.
     */
    public function calculate(int $purchaseId): void
    {
        DB::transaction(function () use ($purchaseId) {
            $purchase = Purchase::query()->findOrFail($purchaseId);

            $details = PurchaseDetail::query()
                ->where('purchase_id', $purchaseId)
                ->get(['id', 'product_id', 'quantity', 'unitcost', 'updated_at']);

            if ($details->isEmpty()) {
                return;
            }

            $totalPurchaseValue = (float) $details->sum(function (PurchaseDetail $d) {
                $qty = (int) ($d->quantity ?? 0);
                $unitCost = (float) ($d->unitcost ?? 0);
                return $qty * $unitCost;
            });

            $totalExpense = (float) Activity::query()
                ->where('purchase_id', $purchaseId)
                ->sum('activity_cost');

            // Avoid division by zero; if totalPurchaseValue is 0 we allocate 0 expense to all items.
            $ratioBase = $totalPurchaseValue > 0 ? $totalPurchaseValue : 0.0;

            foreach ($details as $detail) {
                $qty = (int) ($detail->quantity ?? 0);
                $unitCost = (float) ($detail->unitcost ?? 0);

                $productValue = $qty * $unitCost;
                $ratio = $ratioBase > 0 ? ($productValue / $ratioBase) : 0.0;
                $allocatedExpense = $totalExpense * $ratio;
                $landedTotal = $productValue + $allocatedExpense;
                $landedUnitCost = $qty > 0 ? ($landedTotal / $qty) : 0.0;

                $detail->update([
                    'allocated_expense' => round($allocatedExpense, 4),
                    'landed_unit_cost' => round($landedUnitCost, 4),
                    'landed_total' => round($landedTotal, 4),
                ]);
            }
        });
    }

    /**
     * Apply manual landed unit costs entered by the user.
     *
     * For each purchase detail:
     * - landed_total = landed_unit_cost * qty
     * - allocated_expense = landed_total - (qty * unitcost)
     */
    public function applyManualAdjustment(int $purchaseId, array $landedUnitCosts): void
    {
        DB::transaction(function () use ($purchaseId, $landedUnitCosts) {
            $details = PurchaseDetail::query()
                ->where('purchase_id', $purchaseId)
                ->get(['id', 'product_id', 'quantity', 'unitcost']);

            foreach ($details as $detail) {
                if (!array_key_exists((string) $detail->id, $landedUnitCosts) && !array_key_exists($detail->id, $landedUnitCosts)) {
                    continue; // Only update rows present in request payload
                }

                $qty = (int) ($detail->quantity ?? 0);
                $unitCost = (float) ($detail->unitcost ?? 0);
                $landedUnitCost = (float) $landedUnitCosts[$detail->id] ?? (float) $landedUnitCosts[(string) $detail->id];

                $landedTotal = $landedUnitCost * $qty;
                $allocatedExpense = $landedTotal - ($qty * $unitCost);

                $detail->update([
                    'allocated_expense' => round($allocatedExpense, 4),
                    'landed_unit_cost' => round($landedUnitCost, 4),
                    'landed_total' => round($landedTotal, 4),
                ]);
            }
        });
    }
}

