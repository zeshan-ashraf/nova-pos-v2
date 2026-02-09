<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Extend stock_logs for ledger-safe stock: shop_id, qty (positive), source_id.
 * qty is always positive; direction (in/out) controls stock math.
 * source_type: opening, purchase, sale, purchase_return, sale_return, adjustment, loss.
 */
class ExtendStockLogsLedgerSchema extends Migration
{
    public function up(): void
    {
        Schema::table('stock_logs', function (Blueprint $table) {
            $table->unsignedBigInteger('shop_id')->nullable()->after('id');
            // Positive quantity only; direction controls in/out (avoids signed confusion)
            $table->unsignedInteger('qty')->nullable()->after('product_id');
            $table->string('source_id', 64)->nullable()->after('source_type'); // e.g. order_id, purchase_id
        });

        // Backfill shop_id from product so existing rows are FK-consistent (avoids JOIN update driver quirks)
        $logs = DB::table('stock_logs')->select('stock_logs.id', 'products.shop_id')
            ->join('products', 'stock_logs.product_id', '=', 'products.id')
            ->whereNotNull('products.shop_id')
            ->get();
        foreach ($logs as $row) {
            DB::table('stock_logs')->where('id', $row->id)->update(['shop_id' => $row->shop_id]);
        }

        // Optional: add FK after backfill (nullable shop_id allowed for legacy rows)
        Schema::table('stock_logs', function (Blueprint $table) {
            $table->foreign('shop_id')->references('id')->on('shops')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::table('stock_logs', function (Blueprint $table) {
            $table->dropForeign(['shop_id']);
            $table->dropColumn(['shop_id', 'qty', 'source_id']);
        });
    }
}
