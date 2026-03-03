<?php

namespace App\Console\Commands;

use App\Models\Order;
use App\Models\OrderDetails;
use App\Models\StockLog;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class FixMissingSaleStockLogs extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'stock:fix-missing-sales {shop_id} {--dry-run}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Detect and backfill missing stock_logs for SALE orders for a specific shop (orders that have zero sale stock_logs).';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $shopId = (int) $this->argument('shop_id');
        $dryRun = (bool) $this->option('dry-run');

        if ($shopId < 1) {
            $this->error('shop_id must be a positive integer.');
            return Command::FAILURE;
        }

        // Efficient: orders that have ZERO sale stock_logs for this shop
        $missingOrderIds = Order::query()
            ->where('shop_id', $shopId)
            ->whereNotExists(function ($query) {
                $query->select(DB::raw(1))
                    ->from('stock_logs')
                    ->whereColumn('stock_logs.source_id', '=', 'orders.id')
                    ->where('stock_logs.source_type', 'sale')
                    ->whereColumn('stock_logs.shop_id', '=', 'orders.shop_id')
                    ->whereNull('stock_logs.deleted_at');
            })
            ->orderBy('id')
            ->pluck('id')
            ->values()
            ->all();

        $totalOrdersChecked = Order::where('shop_id', $shopId)->count();
        $totalMissingOrders = count($missingOrderIds);

        if ($dryRun) {
            $this->info('DRY RUN — no inserts will be performed.');
            $this->info("Total orders checked (shop_id={$shopId}): {$totalOrdersChecked}");
            $this->info("Total missing orders (no sale stock_logs): {$totalMissingOrders}");
            if ($totalMissingOrders > 0) {
                $this->info('Missing order IDs: ' . implode(', ', $missingOrderIds));
                $wouldInsert = OrderDetails::whereIn('order_id', $missingOrderIds)->count();
                $this->info("Total stock_log rows that WOULD be inserted: {$wouldInsert}");
            }
            return Command::SUCCESS;
        }

        if ($totalMissingOrders === 0) {
            $this->info("No missing sale stock_logs for shop_id={$shopId}. Nothing to do.");
            return Command::SUCCESS;
        }

        $ordersFixed = 0;
        $totalInserted = 0;
        $skippedNoDetails = 0;

        DB::transaction(function () use ($missingOrderIds, &$ordersFixed, &$totalInserted, &$skippedNoDetails) {
            $chunks = array_chunk($missingOrderIds, 200);

            foreach ($chunks as $chunkIds) {
                $orders = Order::with('orderDetails')
                    ->whereIn('id', $chunkIds)
                    ->orderBy('id')
                    ->get();

                foreach ($orders as $order) {
                    $details = $order->orderDetails;
                    if ($details->isEmpty()) {
                        $skippedNoDetails++;
                        continue;
                    }

                    $orderCreatedAt = $order->created_at ? $order->created_at->format('Y-m-d H:i:s') : now()->format('Y-m-d H:i:s');
                    $orderDate = $order->order_date;
                    $adjustmentDate = $orderDate ? (Carbon::parse($orderDate)->format('Y-m-d H:i:s')) : null;

                    foreach ($details as $detail) {
                        StockLog::create([
                            'shop_id'         => $order->shop_id,
                            'product_id'      => $detail->product_id,
                            'supplier_id'     => null,
                            'qty'             => (int) $detail->quantity,
                            'stock_qty'       => -(int) $detail->quantity,
                            'direction'       => 'out',
                            'source_type'     => 'sale',
                            'source_id'       => (string) $order->id,
                            'adjustment_date' => $adjustmentDate,
                            'price'           => (float) ($detail->unitcost ?? 0),
                            'cost_per_unit'   => $detail->cost_per_unit !== null ? (float) $detail->cost_per_unit : null,
                            'reason'          => null,
                            'created_at'      => $orderCreatedAt,
                            'updated_at'      => now(),
                        ]);
                        $totalInserted++;
                    }
                    $ordersFixed++;
                }
            }
        });

        $this->info("Orders fixed: {$ordersFixed}");
        $this->info("Total stock_logs inserted: {$totalInserted}");
        $this->info("Skipped orders (no order_details): {$skippedNoDetails}");
        $this->info('Done.');

        return Command::SUCCESS;
    }
}
