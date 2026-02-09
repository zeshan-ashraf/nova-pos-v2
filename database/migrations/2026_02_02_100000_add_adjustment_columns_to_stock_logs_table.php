<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Extend stock_logs for stock adjustments (damaged, expired, lost, stock found, manual).
 * Purchase entries keep supplier_id and price; adjustment entries use direction/source_type/reason.
 * Uses raw SQL for nullable change to avoid doctrine/dbal dependency.
 */
class AddAdjustmentColumnsToStockLogsTable extends Migration
{
    public function up(): void
    {
        $driver = Schema::getConnection()->getDriverName();

        // Nullable so adjustment entries don't require supplier/price (raw to avoid dbal)
        if ($driver === 'mysql') {
            DB::statement('ALTER TABLE stock_logs MODIFY supplier_id BIGINT UNSIGNED NULL');
            DB::statement('ALTER TABLE stock_logs MODIFY price DECIMAL(8,2) NULL');
        }

        Schema::table('stock_logs', function (Blueprint $table) {
            $table->string('direction', 10)->nullable()->after('stock_qty'); // 'in' | 'out'
            $table->string('source_type', 50)->nullable()->after('direction'); // 'loss' | 'adjustment'
            $table->text('reason')->nullable()->after('source_type');
            $table->date('adjustment_date')->nullable()->after('reason');
        });
    }

    public function down(): void
    {
        Schema::table('stock_logs', function (Blueprint $table) {
            $table->dropColumn(['direction', 'source_type', 'reason', 'adjustment_date']);
        });

        $driver = Schema::getConnection()->getDriverName();
        if ($driver === 'mysql') {
            DB::statement('ALTER TABLE stock_logs MODIFY supplier_id BIGINT UNSIGNED NOT NULL');
            DB::statement('ALTER TABLE stock_logs MODIFY price DECIMAL(8,2) NOT NULL');
        }
    }
}
