<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('payment_logs', function (Blueprint $table) {
            $table->string('payment_method')->default('cash')->after('type'); // 'cash', 'bank', 'cheque', 'credit'
        });

        // Update existing records to set payment_method to 'cash'
        DB::table('payment_logs')->whereNull('payment_method')->update(['payment_method' => 'cash']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('payment_logs', function (Blueprint $table) {
            $table->dropColumn('payment_method');
        });
    }
};

