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
        Schema::table('activities', function (Blueprint $table) {
            $table->unsignedBigInteger('purchase_id')->nullable()->after('shop_id');
            $table->tinyInteger('allocation_locked')->default(0)->after('purchase_id');

            $table->foreign('purchase_id')->references('id')->on('purchases')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('activities', function (Blueprint $table) {
            $table->dropForeign(['purchase_id']);
            $table->dropColumn(['purchase_id', 'allocation_locked']);
        });
    }
};

