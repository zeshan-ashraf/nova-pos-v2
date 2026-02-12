<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Links system customers (transfer flow) to the child shop they represent.
     * Unique (shop_id, child_shop_id) so one system customer per mother->child pair.
     */
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->unsignedBigInteger('child_shop_id')->nullable()->after('shop_id');
            $table->foreign('child_shop_id')->references('id')->on('shops')->nullOnDelete();
            $table->unique(['shop_id', 'child_shop_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropUnique(['shop_id', 'child_shop_id']);
            $table->dropForeign(['child_shop_id']);
            $table->dropColumn('child_shop_id');
        });
    }
};
