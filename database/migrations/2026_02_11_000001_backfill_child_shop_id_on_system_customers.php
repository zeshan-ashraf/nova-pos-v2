<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * One-time backfill: set shop_id and child_shop_id on existing system customers
     * that were created with phone pattern ...-SYS-{child_shop_id}.
     *
     * Uses the query builder (not Eloquent) so SoftDeletes / global scopes on the
     * Customer model cannot reference columns that do not exist yet at this point
     * in the migration order (deleted_at is added later on 2026_02_18).
     */
    public function up(): void
    {
        $customers = DB::table('customers')
            ->where('is_system', true)
            ->whereNotNull('phone')
            ->get();

        $updatedPairs = []; // (mother_id, child_id) already assigned to avoid duplicate unique key

        foreach ($customers as $customer) {
            if (!preg_match('/-SYS-(\d+)$/', $customer->phone, $m)) {
                continue;
            }
            $childId = (int) $m[1];
            $childShop = DB::table('shops')->where('id', $childId)->first();
            if (!$childShop || !$childShop->parent_shop_id) {
                continue;
            }
            $motherId = $childShop->parent_shop_id;
            $key = "{$motherId}_{$childId}";
            if (isset($updatedPairs[$key])) {
                continue; // already have one customer for this (mother, child)
            }
            // Ensure no other row already has this (mother_id, child_id) before we update
            $exists = DB::table('customers')
                ->where('shop_id', $motherId)
                ->where('child_shop_id', $childId)
                ->where('id', '!=', $customer->id)
                ->exists();
            if ($exists) {
                $updatedPairs[$key] = true;
                continue;
            }
            DB::table('customers')
                ->where('id', $customer->id)
                ->update([
                    'shop_id' => $motherId,
                    'child_shop_id' => $childId,
                    'updated_at' => now(),
                ]);
            $updatedPairs[$key] = true;
        }
    }

    /**
     * Reverse is a no-op; we don't clear child_shop_id on rollback.
     */
    public function down(): void
    {
        // Intentionally leave data as-is on rollback
    }
};
