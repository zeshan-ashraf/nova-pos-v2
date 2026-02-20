<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Stores cost per unit (buying price) at time of sale for accurate COGS in P&L.
     * Old records leave this NULL; report uses products.buying_price as fallback.
     */
    public function up(): void
    {
        Schema::table('order_details', function (Blueprint $table) {
            $table->decimal('cost_per_unit', 12, 2)->nullable()->after('unitcost');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('order_details', function (Blueprint $table) {
            $table->dropColumn('cost_per_unit');
        });
    }
};
