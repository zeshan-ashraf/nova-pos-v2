<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class BackfillOrderDetailCostPerUnitCommand extends Command
{
    protected $signature = 'orders:backfill-cost-per-unit
                            {--dry-run : Show count only, do not update}';

    protected $description = 'Set cost_per_unit from product buying_price for order_details where cost_per_unit is NULL (old records). Run once if you want past P&L to stop changing when you edit product prices.';

    public function handle(): int
    {
        $dryRun = $this->option('dry-run');

        $count = DB::table('order_details')
            ->join('products', 'order_details.product_id', '=', 'products.id')
            ->whereNull('order_details.cost_per_unit')
            ->whereNull('order_details.deleted_at')
            ->count();

        if ($count === 0) {
            $this->info('No order_details with NULL cost_per_unit. Nothing to do.');
            return 0;
        }

        if ($dryRun) {
            $this->info("[Dry run] Would update {$count} order_detail(s) with product buying_price.");
            return 0;
        }

        $updated = DB::update(
            'UPDATE order_details od
             INNER JOIN products p ON od.product_id = p.id
             SET od.cost_per_unit = COALESCE(p.buying_price, 0)
             WHERE od.cost_per_unit IS NULL AND od.deleted_at IS NULL'
        );

        $this->info("Updated {$updated} order_detail(s) with cost_per_unit from product buying_price.");
        return 0;
    }
}
