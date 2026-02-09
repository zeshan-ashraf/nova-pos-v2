<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Ledger table for all money movement per shop (cash and bank).
     */
    public function up(): void
    {
        Schema::create('account_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shop_id')->constrained()->cascadeOnDelete();
            $table->enum('account_type', ['cash', 'bank']);
            $table->unsignedBigInteger('account_ref_id')->nullable(); // bank_shop.id when bank, NULL for cash
            $table->enum('direction', ['debit', 'credit']);
            $table->decimal('amount', 15, 2);
            $table->enum('source_type', ['opening', 'sale', 'purchase', 'expense', 'transfer', 'adjustment']);
            $table->unsignedBigInteger('source_id')->nullable();
            $table->string('description')->nullable();
            $table->date('transaction_date');
            $table->timestamps();

            $table->foreign('account_ref_id')->references('id')->on('bank_shop')->nullOnDelete();
            $table->index(['account_type', 'account_ref_id']);
            $table->index(['source_type', 'source_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('account_transactions');
    }
};
