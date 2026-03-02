<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Add 'customer_opening' and 'supplier_opening' to account_transactions.source_type.
     */
    public function up(): void
    {
        if (Schema::getConnection()->getDriverName() !== 'mysql') {
            return;
        }

        DB::statement("ALTER TABLE account_transactions MODIFY COLUMN source_type ENUM(
            'opening',
            'sale',
            'purchase',
            'purchase_payment',
            'customer_payment',
            'supplier_payment',
            'customer_opening',
            'supplier_opening',
            'expense',
            'transfer',
            'adjustment'
        ) NOT NULL");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::getConnection()->getDriverName() !== 'mysql') {
            return;
        }

        $customerOpening = DB::table('account_transactions')->where('source_type', 'customer_opening')->count();
        $supplierOpening = DB::table('account_transactions')->where('source_type', 'supplier_opening')->count();
        if ($customerOpening > 0 || $supplierOpening > 0) {
            throw new \RuntimeException("Cannot down migration: {$customerOpening} row(s) use 'customer_opening', {$supplierOpening} row(s) use 'supplier_opening'. Remove or change them first.");
        }

        DB::statement("ALTER TABLE account_transactions MODIFY COLUMN source_type ENUM(
            'opening',
            'sale',
            'purchase',
            'purchase_payment',
            'customer_payment',
            'supplier_payment',
            'expense',
            'transfer',
            'adjustment'
        ) NOT NULL");
    }
};
