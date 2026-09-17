<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 1: integer money fields that participate in qty × price must store DECIMAL.
 * Normal amounts: DECIMAL(12,2). Cost/WAC snapshots: DECIMAL(16,4) to match products.buying_price.
 *
 * Already-correct DECIMAL columns (buying_price, discounts, landed cost, return money,
 * payment logs) are left unchanged.
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

        DB::statement('ALTER TABLE products MODIFY selling_price DECIMAL(12,2) NULL');

        DB::statement('ALTER TABLE order_details MODIFY unitcost DECIMAL(12,2) NULL');
        DB::statement('ALTER TABLE order_details MODIFY total DECIMAL(12,2) NULL');
        DB::statement('ALTER TABLE order_details MODIFY cost_per_unit DECIMAL(16,4) NULL');

        DB::statement('ALTER TABLE purchase_details MODIFY unitcost DECIMAL(12,2) NULL');
        DB::statement('ALTER TABLE purchase_details MODIFY total DECIMAL(12,2) NULL');

        DB::statement('ALTER TABLE stock_logs MODIFY cost_per_unit DECIMAL(16,4) NULL');

        DB::statement('ALTER TABLE orders MODIFY sub_total DECIMAL(12,2) NULL');
        DB::statement('ALTER TABLE orders MODIFY vat DECIMAL(12,2) NULL');
        DB::statement('ALTER TABLE orders MODIFY total DECIMAL(12,2) NULL');
        DB::statement('ALTER TABLE orders MODIFY pay DECIMAL(12,2) NULL');
        DB::statement('ALTER TABLE orders MODIFY due DECIMAL(12,2) NULL');

        DB::statement('ALTER TABLE purchases MODIFY sub_total DECIMAL(12,2) NULL');
        DB::statement('ALTER TABLE purchases MODIFY vat DECIMAL(12,2) NULL');
        DB::statement('ALTER TABLE purchases MODIFY invoice_discount DECIMAL(12,2) NOT NULL DEFAULT 0.00');
        DB::statement('ALTER TABLE purchases MODIFY total DECIMAL(12,2) NULL');
        DB::statement('ALTER TABLE purchases MODIFY pay DECIMAL(12,2) NULL');
        DB::statement('ALTER TABLE purchases MODIFY due DECIMAL(12,2) NULL');
    }

    public function down(): void
    {
        if (Schema::getConnection()->getDriverName() !== 'mysql') {
            return;
        }

        DB::statement('ALTER TABLE products MODIFY selling_price INT NULL');

        DB::statement('ALTER TABLE order_details MODIFY unitcost INT NULL');
        DB::statement('ALTER TABLE order_details MODIFY total INT NULL');
        DB::statement('ALTER TABLE order_details MODIFY cost_per_unit DECIMAL(12,2) NULL');

        DB::statement('ALTER TABLE purchase_details MODIFY unitcost INT NULL');
        DB::statement('ALTER TABLE purchase_details MODIFY total INT NULL');

        DB::statement('ALTER TABLE stock_logs MODIFY cost_per_unit DECIMAL(12,2) NULL');

        DB::statement('ALTER TABLE orders MODIFY sub_total INT NULL');
        DB::statement('ALTER TABLE orders MODIFY vat INT NULL');
        DB::statement('ALTER TABLE orders MODIFY total INT NULL');
        DB::statement('ALTER TABLE orders MODIFY pay INT NULL');
        DB::statement('ALTER TABLE orders MODIFY due INT NULL');

        DB::statement('ALTER TABLE purchases MODIFY sub_total INT NULL');
        DB::statement('ALTER TABLE purchases MODIFY vat INT NULL');
        DB::statement('ALTER TABLE purchases MODIFY invoice_discount INT NOT NULL DEFAULT 0');
        DB::statement('ALTER TABLE purchases MODIFY total INT NULL');
        DB::statement('ALTER TABLE purchases MODIFY pay INT NULL');
        DB::statement('ALTER TABLE purchases MODIFY due INT NULL');
    }
};
