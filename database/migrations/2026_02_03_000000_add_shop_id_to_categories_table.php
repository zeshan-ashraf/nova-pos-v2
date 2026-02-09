<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Categories belong to shop. Add shop_id (nullable for existing rows).
 * Uniqueness: name and slug are unique per shop, not globally.
 */
class AddShopIdToCategoriesTable extends Migration
{
    public function up(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->unsignedBigInteger('shop_id')->nullable()->after('id');
        });

        $driver = Schema::getConnection()->getDriverName();
        if ($driver === 'mysql') {
            Schema::table('categories', function (Blueprint $table) {
                $table->dropUnique(['name']);
                $table->dropUnique(['slug']);
            });
            Schema::table('categories', function (Blueprint $table) {
                $table->unique(['shop_id', 'name']);
                $table->unique(['shop_id', 'slug']);
                $table->foreign('shop_id')->references('id')->on('shops')->onDelete('set null');
            });
        }
    }

    public function down(): void
    {
        $driver = Schema::getConnection()->getDriverName();
        if ($driver === 'mysql') {
            Schema::table('categories', function (Blueprint $table) {
                $table->dropForeign(['shop_id']);
                $table->dropUnique(['shop_id', 'name']);
                $table->dropUnique(['shop_id', 'slug']);
            });
            Schema::table('categories', function (Blueprint $table) {
                $table->unique('name');
                $table->unique('slug');
            });
        }
        Schema::table('categories', function (Blueprint $table) {
            $table->dropColumn('shop_id');
        });
    }
}
