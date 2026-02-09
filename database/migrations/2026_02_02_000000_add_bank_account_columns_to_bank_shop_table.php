<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Adds bank-account fields to the shop_bank (bank_shop) pivot table.
     * Safe additive only: no renames, no removals, no data changes.
     */
    public function up(): void
    {
        Schema::table('bank_shop', function (Blueprint $table) {
            $table->string('name')->nullable()->after('bank_id');
            $table->boolean('is_active')->default(true)->after('name');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('bank_shop', function (Blueprint $table) {
            $table->dropColumn(['name', 'is_active']);
        });
    }
};
