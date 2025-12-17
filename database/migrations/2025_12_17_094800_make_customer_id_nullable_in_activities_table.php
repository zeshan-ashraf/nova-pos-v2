<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

class MakeCustomerIdNullableInActivitiesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('activities', function (Blueprint $table) {
            // Drop the foreign key constraint first
            $table->dropForeign(['customer_id']);
        });
        
        // Make the column nullable using raw SQL
        DB::statement("ALTER TABLE activities MODIFY customer_id BIGINT UNSIGNED NULL");
        
        // Re-add the foreign key constraint
        Schema::table('activities', function (Blueprint $table) {
            $table->foreign('customer_id')->references('id')->on('customers')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('activities', function (Blueprint $table) {
            // Drop the foreign key constraint
            $table->dropForeign(['customer_id']);
        });
        
        // Make the column NOT NULL again using raw SQL
        DB::statement("ALTER TABLE activities MODIFY customer_id BIGINT UNSIGNED NOT NULL");
        
        // Re-add the foreign key constraint
        Schema::table('activities', function (Blueprint $table) {
            $table->foreign('customer_id')->references('id')->on('customers')->onDelete('cascade');
        });
    }
}

