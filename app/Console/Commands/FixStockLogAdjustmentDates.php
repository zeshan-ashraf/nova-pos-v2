<?php

namespace App\Console\Commands;

use App\Models\Order;
use App\Models\Purchase;
use App\Models\StockLog;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class FixStockLogAdjustmentDates extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'stock:fix-adjustment-dates';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Backfill stock_logs.adjustment_date for legacy sale and purchase records where it is currently NULL.';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('Starting stock_logs.adjustment_date backfill...');

        $saleUpdated = 0;
        $purchaseUpdated = 0;
        $skipped = 0;

        StockLog::query()
            ->whereNull('adjustment_date')
            ->whereIn('source_type', ['sale', 'purchase'])
            ->orderBy('id')
            ->chunkById(500, function ($logs) use (&$saleUpdated, &$purchaseUpdated, &$skipped) {
                DB::transaction(function () use ($logs, &$saleUpdated, &$purchaseUpdated, &$skipped) {
                    /** @var \App\Models\StockLog $log */
                    foreach ($logs as $log) {
                        // Extra guard: only touch rows that still have NULL adjustment_date
                        if (!is_null($log->adjustment_date)) {
                            $skipped++;
                            continue;
                        }

                        if ($log->source_type === 'sale') {
                            if (empty($log->source_id)) {
                                $skipped++;
                                continue;
                            }

                            $order = Order::find($log->source_id);
                            if (!$order || empty($order->order_date)) {
                                $skipped++;
                                continue;
                            }

                            $updated = StockLog::where('id', $log->id)
                                ->whereNull('adjustment_date')
                                ->update(['adjustment_date' => $order->order_date]);

                            if ($updated) {
                                $saleUpdated++;
                            } else {
                                $skipped++;
                            }
                        } elseif ($log->source_type === 'purchase') {
                            if (empty($log->source_id)) {
                                $skipped++;
                                continue;
                            }

                            $purchase = Purchase::find($log->source_id);
                            if (!$purchase || empty($purchase->purchase_date)) {
                                $skipped++;
                                continue;
                            }

                            $updated = StockLog::where('id', $log->id)
                                ->whereNull('adjustment_date')
                                ->update(['adjustment_date' => $purchase->purchase_date]);

                            if ($updated) {
                                $purchaseUpdated++;
                            } else {
                                $skipped++;
                            }
                        } else {
                            // Should not reach here due to whereIn filter, but keep safe
                            $skipped++;
                        }
                    }
                });
            });

        $this->info("Sale stock_logs updated: {$saleUpdated}");
        $this->info("Purchase stock_logs updated: {$purchaseUpdated}");
        $this->info("Skipped records: {$skipped}");
        $this->info('Stock log adjustment dates backfilled successfully.');

        return Command::SUCCESS;
    }
}

