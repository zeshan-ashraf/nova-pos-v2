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
        Schema::create('purchases', function (Blueprint $table) {
            $table->id();
            $table->string('supplier_id');
            $table->foreignId('shop_id')->nullable()->constrained('shops')->nullOnDelete();
            $table->string('purchase_date');
            $table->string('purchase_status');
            $table->integer('total_products');
            $table->integer('sub_total')->nullable();
            $table->integer('vat')->nullable();
            $table->integer('invoice_discount')->default(0);
            $table->string('purchase_no')->nullable();
            $table->integer('total')->nullable();
            $table->string('payment_status')->nullable();
            $table->integer('pay')->nullable();
            $table->integer('due')->nullable();
            $table->text('comment')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('purchases');
    }
};
