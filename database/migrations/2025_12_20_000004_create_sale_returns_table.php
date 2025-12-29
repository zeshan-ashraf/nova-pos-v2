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
        Schema::create('sale_returns', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained('orders')->onDelete('cascade');
            $table->string('customer_id');
            $table->foreignId('shop_id')->nullable()->constrained('shops')->nullOnDelete();
            $table->dateTime('return_date');
            $table->string('return_status')->default('completed');
            $table->string('return_no')->unique();
            $table->integer('total_products');
            $table->decimal('sub_total', 10, 2)->nullable();
            $table->decimal('invoice_discount', 10, 2)->default(0);
            $table->decimal('vat', 10, 2)->nullable();
            $table->decimal('total', 10, 2);
            $table->text('reason')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sale_returns');
    }
};
