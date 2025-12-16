<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Convert existing date strings to datetime format if needed
        // Handle both 'Y-m-d' and 'Y-m-d H:i:s' formats
        $orders = DB::table('orders')->get();
        foreach ($orders as $order) {
            $dateValue = $order->order_date;
            // If it's just a date, add time
            if (strlen($dateValue) === 10 && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateValue)) {
                DB::table('orders')
                    ->where('id', $order->id)
                    ->update(['order_date' => $dateValue . ' 00:00:00']);
            }
        }

        // Change the column type to datetime
        Schema::table('orders', function (Blueprint $table) {
            $table->dateTime('order_date')->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('order_date')->change();
        });
    }
};
