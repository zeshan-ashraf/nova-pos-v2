<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 1: product/stock quantities use DECIMAL(12,3) so kg values such as 0.750
 * can be stored without truncation. Piece quantities remain whole at validation time.
 *
 * Uses raw MySQL ALTER to avoid a doctrine/dbal dependency.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::getConnection()->getDriverName() !== 'mysql') {
            return;
        }

        DB::statement('ALTER TABLE products MODIFY product_store DECIMAL(12,3) NULL');
        DB::statement('ALTER TABLE products MODIFY low_stock_warning DECIMAL(12,3) NOT NULL DEFAULT 10.000');
        DB::statement('ALTER TABLE products MODIFY reserved_stock DECIMAL(12,3) NOT NULL DEFAULT 0.000');

        DB::statement('ALTER TABLE order_details MODIFY quantity DECIMAL(12,3) NULL');
        DB::statement('ALTER TABLE purchase_details MODIFY quantity DECIMAL(12,3) NULL');
        DB::statement('ALTER TABLE sale_return_details MODIFY quantity DECIMAL(12,3) NOT NULL');
        DB::statement('ALTER TABLE purchase_return_details MODIFY quantity DECIMAL(12,3) NOT NULL');

        DB::statement('ALTER TABLE stock_logs MODIFY qty DECIMAL(12,3) NULL');
        DB::statement('ALTER TABLE stock_logs MODIFY stock_qty DECIMAL(12,3) NOT NULL');
    }

    public function down(): void
    {
        if (Schema::getConnection()->getDriverName() !== 'mysql') {
            return;
        }

        DB::statement('ALTER TABLE products MODIFY product_store INT NULL');
        DB::statement('ALTER TABLE products MODIFY low_stock_warning INT NOT NULL DEFAULT 10');
        DB::statement('ALTER TABLE products MODIFY reserved_stock DECIMAL(10,3) NOT NULL DEFAULT 0.000');

        DB::statement('ALTER TABLE order_details MODIFY quantity INT NULL');
        DB::statement('ALTER TABLE purchase_details MODIFY quantity INT NULL');
        DB::statement('ALTER TABLE sale_return_details MODIFY quantity INT NOT NULL');
        DB::statement('ALTER TABLE purchase_return_details MODIFY quantity INT NOT NULL');

        DB::statement('ALTER TABLE stock_logs MODIFY qty INT UNSIGNED NULL');
        DB::statement('ALTER TABLE stock_logs MODIFY stock_qty INT NOT NULL');
    }
};
