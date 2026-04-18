<?php

namespace App\Services\Purchase;

use App\Models\Activity;
use App\Models\AccountTransaction;
use App\Models\Purchase;
use App\Services\Ledger\ExpenseLedgerService;
use Illuminate\Support\Facades\DB;

class PurchaseExpenseService
{
    public function __construct(
        private ExpenseLedgerService $expenseLedgerService
    ) {}

    /**
     * Add a purchase expense (stored as an Activity linked to the purchase).
     *
     * SRP: only create the Activity (+ matching expense ledger rows, consistent with existing ExpenseController).
     */
    public function addExpense(int $purchaseId, array $data): Activity
    {
        return DB::transaction(function () use ($purchaseId, $data) {
            $purchase = Purchase::query()->with('shop')->findOrFail($purchaseId);

            if (($purchase->purchase_status ?? '') === 'complete') {
                throw new \RuntimeException('Cannot edit expenses after purchase is received.');
            }

            if (($purchase->landed_cost_status ?? 'pending') === 'approved') {
                throw new \RuntimeException('Cannot add expenses after landed cost is approved.');
            }

            $activity = Activity::create([
                'purchase_id' => $purchaseId,
                'expense_id' => $data['expense_id'],
                'title' => null,
                'description' => $data['description'] ?? null,
                'date' => $data['date'],
                'activity_cost' => $data['activity_cost'],
                // Expenses are always treated as cash expenses in this ERP unless payment_method is provided.
                'payment_method' => $data['payment_method'] ?? 'cash',
                'shop_bank_id' => $data['shop_bank_id'] ?? null,
                'customer_id' => null,
                'shop_id' => $purchase->shop_id,
                'images' => json_encode([]),
            ]);

            // Keep existing accounting behavior for expenses.
            $this->expenseLedgerService->recordExpense($activity);

            return $activity;
        });
    }

    /**
     * Update a purchase expense Activity.
     *
     * SRP: only update the Activity fields (+ do NOT re-create ledger duplicates; mirrors ExpenseController behavior).
     */
    public function updateExpense(int $activityId, array $data): Activity
    {
        return DB::transaction(function () use ($activityId, $data) {
            $activity = Activity::query()->findOrFail($activityId);
            $purchaseId = (int) $activity->purchase_id;

            $purchase = Purchase::query()->findOrFail($purchaseId);
            if (($purchase->purchase_status ?? '') === 'complete') {
                throw new \RuntimeException('Cannot edit expenses after purchase is received.');
            }

            if (($purchase->landed_cost_status ?? 'pending') === 'approved') {
                throw new \RuntimeException('Cannot edit expenses after landed cost is approved.');
            }

            $activity->update([
                'expense_id' => $data['expense_id'],
                'description' => $data['description'] ?? null,
                'date' => $data['date'],
                'activity_cost' => $data['activity_cost'],
                'payment_method' => $data['payment_method'] ?? ($activity->payment_method ?? 'cash'),
                'shop_bank_id' => $data['shop_bank_id'] ?? $activity->shop_bank_id,
            ]);

            return $activity;
        });
    }

    /**
     * Delete (soft delete) a purchase expense Activity.
     *
     * SRP: only remove the Activity (+ delete related expense ledger rows, consistent with ExpenseController@destroy).
     */
    public function deleteExpense(int $activityId): void
    {
        DB::transaction(function () use ($activityId) {
            $activity = Activity::query()->with('purchase')->findOrFail($activityId);
            $purchase = $activity->purchase;
            if (!$purchase) {
                throw new \RuntimeException('Invalid purchase expense: purchase link missing.');
            }

            if (($purchase->purchase_status ?? '') === 'complete') {
                throw new \RuntimeException('Cannot edit expenses after purchase is received.');
            }

            if (($purchase->landed_cost_status ?? 'pending') === 'approved') {
                throw new \RuntimeException('Cannot delete expenses after landed cost is approved.');
            }

            AccountTransaction::query()
                ->where('source_type', AccountTransaction::SOURCE_EXPENSE)
                ->where('source_id', $activity->id)
                ->delete();

            $activity->delete();
        });
    }
}

