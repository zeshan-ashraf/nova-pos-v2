<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Product code must be unique system-wide. Run products:assign-codes first if you have existing products with duplicate/null codes.
     */
    public function up(): void
    {
        $hasDuplicates = DB::table('products')
            ->whereNotNull('product_code')
            ->where('product_code', '!=', '')
            ->selectRaw('product_code, COUNT(*) as cnt')
            ->groupBy('product_code')
            ->having('cnt', '>', 1)
            ->exists();

        if ($hasDuplicates) {
            throw new \RuntimeException(
                'Duplicate product codes found. Run: php artisan products:assign-codes'
            );
        }

        Schema::table('products', function (Blueprint $table) {
            $table->unique('product_code');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropUnique(['product_code']);
        });
    }
};
