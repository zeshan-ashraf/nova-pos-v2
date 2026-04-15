<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('purchase_details', function (Blueprint $table) {
            $table->decimal('allocated_expense', 12, 4)->default(0)->after('total');
            $table->decimal('landed_unit_cost', 12, 4)->default(0)->after('allocated_expense');
            $table->decimal('landed_total', 14, 4)->default(0)->after('landed_unit_cost');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('purchase_details', function (Blueprint $table) {
            $table->dropColumn(['allocated_expense', 'landed_unit_cost', 'landed_total']);
        });
    }
};

