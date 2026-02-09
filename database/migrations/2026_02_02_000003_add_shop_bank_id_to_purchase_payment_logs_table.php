<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Add nullable shop_bank_id to purchase_payment_logs for bank tracking.
     * Safe additive only: no existing columns modified; existing rows remain valid.
     */
    public function up(): void
    {
        Schema::table('purchase_payment_logs', function (Blueprint $table) {
            $table->unsignedBigInteger('shop_bank_id')->nullable()->after('purchase_id');
            $table->foreign('shop_bank_id')->references('id')->on('bank_shop')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('purchase_payment_logs', function (Blueprint $table) {
            $table->dropForeign(['shop_bank_id']);
            $table->dropColumn('shop_bank_id');
        });
    }
};
