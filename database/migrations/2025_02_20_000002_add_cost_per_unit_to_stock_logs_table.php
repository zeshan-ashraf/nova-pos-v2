<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Stores cost per unit at time of sale for stock_logs (e.g. for COGS/reports).
     */
    public function up(): void
    {
        Schema::table('stock_logs', function (Blueprint $table) {
            $table->decimal('cost_per_unit', 12, 2)->nullable()->after('price');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('stock_logs', function (Blueprint $table) {
            $table->dropColumn('cost_per_unit');
        });
    }
};
