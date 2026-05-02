<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Table may already exist if a previous run created it but failed before the batch was recorded
        // (e.g. CHECK constraint error). Skip create so migrate can complete and register this migration.
        if (Schema::hasTable('shop_expenses')) {
            return;
        }

        Schema::create('shop_expenses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('expense_id')->nullable()->constrained('expenses')->nullOnDelete();
            $table->foreignId('shop_id')->constrained('shops')->cascadeOnDelete();
            $table->date('expense_date');
            $table->decimal('amount', 12, 2);
            $table->text('description')->nullable();
            $table->enum('payment_type', ['cash', 'bank']);
            $table->foreignId('bank_id')->nullable()->constrained('banks')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['shop_id', 'expense_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shop_expenses');
    }
};
