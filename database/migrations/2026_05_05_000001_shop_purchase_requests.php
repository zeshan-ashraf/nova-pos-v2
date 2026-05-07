<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('shop_purchase_requests')) {
            Schema::create('shop_purchase_requests', function (Blueprint $table) {
                $table->id();
                $table->string('type')->default('inter_shop_transfer')->index();
                $table->unsignedBigInteger('mother_shop_sale_id')->unique();
                $table->unsignedBigInteger('child_shop_id')->index();
                $table->unsignedBigInteger('mapped_purchase_id')->nullable()->index();
                $table->string('status', 32)->default('PENDING');
                $table->json('payload')->nullable();
                $table->timestamp('approved_at')->nullable();
                $table->unsignedBigInteger('approved_by')->nullable();
                $table->timestamps();
            });
        }

        if (Schema::hasTable('shop_notifications') && !Schema::hasColumn('shop_notifications', 'shop_purchase_request_id')) {
            Schema::table('shop_notifications', function (Blueprint $table) {
                $table->unsignedBigInteger('shop_purchase_request_id')->nullable()->after('shop_id')->index();
            });
        }

        if (!Schema::hasTable('purchases') || !Schema::hasTable('shop_purchase_requests')) {
            return;
        }

        // Backfill one request per mother sale that already has a system-generated child purchase.
        $rows = DB::table('purchases')
            ->where('is_system_generated', 1)
            ->whereNotNull('source_sale_id')
            ->orderBy('id')
            ->get(['id', 'source_sale_id', 'shop_id', 'purchase_status']);

        foreach ($rows as $p) {
            $exists = DB::table('shop_purchase_requests')
                ->where('mother_shop_sale_id', $p->source_sale_id)
                ->exists();
            if ($exists) {
                continue;
            }
            $now = now();
            DB::table('shop_purchase_requests')->insert([
                'type' => 'inter_shop_transfer',
                'mother_shop_sale_id' => $p->source_sale_id,
                'child_shop_id' => $p->shop_id,
                'mapped_purchase_id' => $p->id,
                'status' => (string) ($p->purchase_status ?? 'PENDING'),
                'payload' => null,
                'approved_at' => null,
                'approved_by' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        // Link existing child-shop notifications (JSON order_id) to the new request row.
        if (Schema::hasTable('shop_notifications') && Schema::hasColumn('shop_notifications', 'shop_purchase_request_id')) {
            $notifs = DB::table('shop_notifications')
                ->whereNull('shop_purchase_request_id')
                ->whereNotNull('data')
                ->get(['id', 'shop_id', 'data']);

            foreach ($notifs as $n) {
                $data = json_decode($n->data, true);
                if (!is_array($data) || empty($data['order_id'])) {
                    continue;
                }
                $orderId = (int) $data['order_id'];
                $req = DB::table('shop_purchase_requests')
                    ->where('mother_shop_sale_id', $orderId)
                    ->where('child_shop_id', $n->shop_id)
                    ->first();
                if ($req) {
                    DB::table('shop_notifications')
                        ->where('id', $n->id)
                        ->update(['shop_purchase_request_id' => $req->id]);
                }
            }
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('shop_notifications') && Schema::hasColumn('shop_notifications', 'shop_purchase_request_id')) {
            Schema::table('shop_notifications', function (Blueprint $table) {
                $table->dropColumn('shop_purchase_request_id');
            });
        }

        Schema::dropIfExists('shop_purchase_requests');
    }
};
