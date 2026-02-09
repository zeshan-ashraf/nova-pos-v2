<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * System expenses (e.g. inventory loss) are non-cash; linked_stock_log_id for duplicate protection; reversal_of for reversals.
     */
    public function up(): void
    {
        Schema::table('activities', function (Blueprint $table) {
            $table->string('category', 100)->nullable()->after('shop_bank_id');
            $table->boolean('is_system')->default(false)->after('category');
            $table->unsignedBigInteger('linked_stock_log_id')->nullable()->after('is_system');
            $table->unsignedBigInteger('reversal_of_expense_id')->nullable()->after('linked_stock_log_id');
        });

        Schema::table('activities', function (Blueprint $table) {
            $table->foreign('linked_stock_log_id')->references('id')->on('stock_logs')->nullOnDelete();
            $table->foreign('reversal_of_expense_id')->references('id')->on('activities')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('activities', function (Blueprint $table) {
            $table->dropForeign(['linked_stock_log_id']);
            $table->dropForeign(['reversal_of_expense_id']);
            $table->dropColumn(['category', 'is_system', 'linked_stock_log_id', 'reversal_of_expense_id']);
        });
    }
};
