<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('products') && !Schema::hasColumn('products', 'reserved_stock')) {
            Schema::table('products', function (Blueprint $table) {
                $table->decimal('reserved_stock', 10, 3)->default(0);
            });
        }

        if (Schema::hasTable('purchases')) {
            Schema::table('purchases', function (Blueprint $table) {
                if (!Schema::hasColumn('purchases', 'approved_at')) {
                    $table->timestamp('approved_at')->nullable();
                }
                if (!Schema::hasColumn('purchases', 'approved_by')) {
                    $table->unsignedBigInteger('approved_by')->nullable();
                }
            });
        }

        if (!Schema::hasTable('shop_notifications')) {
            Schema::create('shop_notifications', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('shop_id')->index();
                $table->string('type');
                $table->json('data')->nullable();
                $table->boolean('is_read')->default(false);
                $table->timestamp('created_at')->useCurrent();
            });
        }

        // Normalize existing mother→child transfer rows: ledger was applied at create time.
        if (Schema::hasTable('purchases') && Schema::hasTable('orders')) {
            DB::statement("
                UPDATE purchases p
                INNER JOIN orders o ON o.id = p.source_sale_id
                SET p.purchase_status = 'COMPLETED'
                WHERE p.is_system_generated = 1
                  AND p.source_sale_id IS NOT NULL
                  AND o.order_status = 'complete'
            ");
            DB::statement("
                UPDATE orders o
                INNER JOIN purchases p ON p.source_sale_id = o.id AND p.is_system_generated = 1
                SET o.order_status = 'COMPLETED'
                WHERE o.order_status = 'complete'
            ");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('shop_notifications');

        if (Schema::hasTable('purchases')) {
            Schema::table('purchases', function (Blueprint $table) {
                if (Schema::hasColumn('purchases', 'approved_at')) {
                    $table->dropColumn('approved_at');
                }
                if (Schema::hasColumn('purchases', 'approved_by')) {
                    $table->dropColumn('approved_by');
                }
            });
        }

        if (Schema::hasTable('products') && Schema::hasColumn('products', 'reserved_stock')) {
            Schema::table('products', function (Blueprint $table) {
                $table->dropColumn('reserved_stock');
            });
        }
    }
};
