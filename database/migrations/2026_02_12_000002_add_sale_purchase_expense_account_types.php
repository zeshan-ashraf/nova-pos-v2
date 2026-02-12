<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Add account_type values for double-entry bookkeeping: sale (revenue), purchase (inventory), expense.
 * Does NOT modify or backfill existing rows. Only allows new transaction types going forward.
 */
return new class extends Migration
{
    public function up(): void
    {
        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'mysql') {
            DB::statement("ALTER TABLE account_transactions MODIFY COLUMN account_type ENUM('cash', 'bank', 'customer', 'supplier', 'sale', 'purchase', 'expense') NOT NULL");
        }
    }

    public function down(): void
    {
        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'mysql') {
            // Only revert if no rows use the new types (safety: do not drop data)
            $count = DB::table('account_transactions')
                ->whereIn('account_type', ['sale', 'purchase', 'expense'])
                ->count();
            if ($count > 0) {
                throw new \RuntimeException('Cannot down migration: account_transactions has rows with account_type sale, purchase, or expense. Remove those rows first.');
            }
            DB::statement("ALTER TABLE account_transactions MODIFY COLUMN account_type ENUM('cash', 'bank', 'customer', 'supplier') NOT NULL");
        }
    }
};
