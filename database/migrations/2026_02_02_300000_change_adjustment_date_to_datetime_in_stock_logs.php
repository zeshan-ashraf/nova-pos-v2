<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Store adjustment date and time in stock_logs (was date only).
 * Uses raw SQL to avoid doctrine/dbal dependency.
 */
class ChangeAdjustmentDateToDatetimeInStockLogs extends Migration
{
    public function up(): void
    {
        if (Schema::getConnection()->getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE stock_logs MODIFY adjustment_date DATETIME NULL');
        }
    }

    public function down(): void
    {
        if (Schema::getConnection()->getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE stock_logs MODIFY adjustment_date DATE NULL');
        }
    }
}
