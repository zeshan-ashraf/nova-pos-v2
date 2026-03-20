<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            if (!Schema::hasColumn('products', 'parent_product_id')) {
                $table->unsignedBigInteger('parent_product_id')->nullable()->after('shop_id');
                $table->index('parent_product_id');

                // Self-referencing FK: safe because it's nullable; if parent is deleted mapping is cleared.
                $table->foreign('parent_product_id')
                    ->references('id')
                    ->on('products')
                    ->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            if (Schema::hasColumn('products', 'parent_product_id')) {
                // Drop FK first (name depends on DB; Laravel resolves by column with dropForeign).
                $table->dropForeign(['parent_product_id']);
                $table->dropIndex(['parent_product_id']);
                $table->dropColumn('parent_product_id');
            }
        });
    }
};

