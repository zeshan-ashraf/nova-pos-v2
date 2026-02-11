<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Add 'purchase_payment' to account_transactions.source_type for paying credit purchases later.
     */
    public function up(): void
    {
        if (Schema::getConnection()->getDriverName() !== 'mysql') {
            return;
        }
        DB::statement("ALTER TABLE account_transactions MODIFY COLUMN source_type ENUM('opening', 'sale', 'purchase', 'purchase_payment', 'expense', 'transfer', 'adjustment') NOT NULL");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::getConnection()->getDriverName() !== 'mysql') {
            return;
        }
        DB::statement("ALTER TABLE account_transactions MODIFY COLUMN source_type ENUM('opening', 'sale', 'purchase', 'expense', 'transfer', 'adjustment') NOT NULL");
    }
};
