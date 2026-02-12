<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Extend account_transactions for customer/supplier ledger. Safe: preserves existing data.
     * - account_type: add 'customer', 'supplier'
     * - source_type: add 'customer_payment', 'supplier_payment'
     * - Drop FK on account_ref_id so it can store customer_id / supplier_id (polymorphic ref).
     */
    public function up(): void
    {
        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'mysql') {
            DB::statement("ALTER TABLE account_transactions MODIFY COLUMN account_type ENUM('cash', 'bank', 'customer', 'supplier') NOT NULL");
            DB::statement("ALTER TABLE account_transactions MODIFY COLUMN source_type ENUM('opening', 'sale', 'purchase', 'purchase_payment', 'customer_payment', 'supplier_payment', 'expense', 'transfer', 'adjustment') NOT NULL");
        }

        Schema::table('account_transactions', function (Blueprint $table) {
            $table->dropForeign(['account_ref_id']);
        });
    }

    /**
     * Reverse the migrations.
     * Note: Re-add FK only if no rows use account_type IN ('customer','supplier').
     */
    public function down(): void
    {
        Schema::table('account_transactions', function (Blueprint $table) {
            $table->foreign('account_ref_id')->references('id')->on('bank_shop')->nullOnDelete();
        });

        $driver = Schema::getConnection()->getDriverName();
        if ($driver === 'mysql') {
            DB::statement("ALTER TABLE account_transactions MODIFY COLUMN account_type ENUM('cash', 'bank') NOT NULL");
            DB::statement("ALTER TABLE account_transactions MODIFY COLUMN source_type ENUM('opening', 'sale', 'purchase', 'purchase_payment', 'expense', 'transfer', 'adjustment') NOT NULL");
        }
    }
};
