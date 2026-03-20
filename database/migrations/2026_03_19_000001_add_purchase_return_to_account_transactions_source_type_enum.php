<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
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
            'adjustment',
            'purchase_return'
        ) NOT NULL");
    }

    public function down(): void
    {
        if (Schema::getConnection()->getDriverName() !== 'mysql') {
            return;
        }

        $used = DB::table('account_transactions')->where('source_type', 'purchase_return')->count();
        if ($used > 0) {
            throw new \RuntimeException("Cannot down migration: {$used} row(s) use 'purchase_return'.");
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
};

