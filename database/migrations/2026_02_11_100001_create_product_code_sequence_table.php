<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Single-row table for global MHB-XXXX product code sequence (short lock).
     */
    public function up(): void
    {
        Schema::create('product_code_sequence', function (Blueprint $table) {
            $table->unsignedBigInteger('id')->primary();
            $table->unsignedBigInteger('last_number')->default(1000); // next code = MHB-(last_number+1)
        });

        DB::table('product_code_sequence')->insert(['id' => 1, 'last_number' => 1000]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('product_code_sequence');
    }
};
