<?php

namespace App\Console\Commands;

use App\Models\Product;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class BackfillProductBuyingPriceCommand extends Command
{
    protected $signature = 'products:backfill-buying-price
                            {--dry-run : List products and weighted average without updating}';

    protected $description = 'Set buying_price (weighted average from purchase_details) for active products that have null buying_price but have purchase history.';

    public function handle(): int
    {
        $dryRun = $this->option('dry-run');

        $rows = DB::table('products')
            ->select([
                'products.id',
                'products.product_name',
                'products.product_code',
                'products.shop_id',
                'pd.weighted_avg',
            ])
            ->joinSub(
                DB::table('purchase_details')
                    ->selectRaw('product_id, SUM(quantity * unitcost) / NULLIF(SUM(quantity), 0) AS weighted_avg')
                    ->whereNull('deleted_at')
                    ->groupBy('product_id'),
                'pd',
                'products.id',
                '=',
                'pd.product_id'
            )
            ->where('products.status', 'active')
            ->whereNull('products.buying_price')
            ->get();

        if ($rows->isEmpty()) {
            $this->info('No active products with null buying_price and purchase history. Nothing to do.');
            return 0;
        }

        $weightedAvgPrecision = 4;
        $count = $rows->count();

        if ($dryRun) {
            $this->info("[DRY RUN] Would update {$count} product(s) with weighted average from purchase_details.");
            $this->newLine();
            $this->table(
                ['ID', 'Product', 'Code', 'Shop ID', 'New buying_price'],
                $rows->map(fn ($r) => [
                    $r->id,
                    $r->product_name,
                    $r->product_code ?? '–',
                    $r->shop_id ?? '–',
                    round((float) $r->weighted_avg, $weightedAvgPrecision),
                ])
            );
            $this->info('[DRY RUN] No changes written.');
            return 0;
        }

        $updated = 0;
        foreach ($rows as $row) {
            $newPrice = round((float) $row->weighted_avg, $weightedAvgPrecision);
            Product::where('id', $row->id)->update(['buying_price' => $newPrice]);
            $updated++;
        }

        $this->info("Updated buying_price for {$updated} product(s) using weighted average from purchase_details.");

        return 0;
    }
}
