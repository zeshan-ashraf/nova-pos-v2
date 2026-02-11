<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Product code is unique per shop: (shop_id, product_code). Same code (e.g. MHB-101) can exist in different shops.
     */
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropUnique(['product_code']);
        });

        Schema::table('products', function (Blueprint $table) {
            $table->unique(['shop_id', 'product_code']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropUnique(['shop_id', 'product_code']);
        });

        Schema::table('products', function (Blueprint $table) {
            $table->unique('product_code');
        });
    }
};
