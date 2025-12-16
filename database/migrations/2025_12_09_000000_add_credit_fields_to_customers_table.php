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
        Schema::table('customers', function (Blueprint $table) {
            $table->decimal('credit_limit', 12, 2)->default(0)->after('shop_id');
            $table->decimal('credit_amount', 12, 2)->default(0)->after('credit_limit');
            $table->unsignedInteger('credit_days')->default(0)->after('credit_amount');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropColumn(['credit_limit', 'credit_amount', 'credit_days']);
        });
    }
};
