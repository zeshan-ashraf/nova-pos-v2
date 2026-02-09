<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Expenses are always paid at creation; payment_method and shop_bank_id support ledger recording.
     */
    public function up(): void
    {
        Schema::table('activities', function (Blueprint $table) {
            $table->string('payment_method', 20)->default('cash')->after('activity_cost');
            $table->unsignedBigInteger('shop_bank_id')->nullable()->after('payment_method');
        });

        Schema::table('activities', function (Blueprint $table) {
            $table->foreign('shop_bank_id')->references('id')->on('bank_shop')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('activities', function (Blueprint $table) {
            $table->dropForeign(['shop_bank_id']);
            $table->dropColumn(['payment_method', 'shop_bank_id']);
        });
    }
};
