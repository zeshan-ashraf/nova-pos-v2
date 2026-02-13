<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Safe soft delete: account_transactions and stock_logs get deleted_at so balances/reports ignore them when soft deleted.
     */
    public function up(): void
    {
        Schema::table('account_transactions', function (Blueprint $table) {
            $table->softDeletes();
        });

        Schema::table('stock_logs', function (Blueprint $table) {
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('account_transactions', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });

        Schema::table('stock_logs', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });
    }
};
