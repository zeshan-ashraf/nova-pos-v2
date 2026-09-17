<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 1: snapshot the product unit on each transaction/movement line.
 * Historical rows default to piece. Do not read current products.unit for history display.
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->addUnitColumn('order_details', 'quantity');
        $this->addUnitColumn('purchase_details', 'quantity');
        $this->addUnitColumn('sale_return_details', 'quantity');
        $this->addUnitColumn('purchase_return_details', 'quantity');
        $this->addUnitColumn('stock_logs', 'qty');
    }

    public function down(): void
    {
        foreach (['order_details', 'purchase_details', 'sale_return_details', 'purchase_return_details', 'stock_logs'] as $table) {
            if (Schema::hasColumn($table, 'unit')) {
                Schema::table($table, function (Blueprint $blueprint) {
                    $blueprint->dropColumn('unit');
                });
            }
        }
    }

    private function addUnitColumn(string $table, string $after): void
    {
        if (Schema::hasColumn($table, 'unit')) {
            return;
        }

        Schema::table($table, function (Blueprint $blueprint) use ($after) {
            $blueprint->string('unit', 16)->default('piece')->after($after);
        });
    }
};
