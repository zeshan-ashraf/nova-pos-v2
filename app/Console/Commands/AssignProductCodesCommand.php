<?php

namespace App\Console\Commands;

use App\Models\Product;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class AssignProductCodesCommand extends Command
{
    protected $signature = 'products:assign-codes
                            {--dry-run : Show what would be assigned without updating}';

    protected $description = 'Reassign all existing products to MHB-1001, MHB-1002, ... by product id order. Updates product_code_sequence for next generation.';

    public function handle(): int
    {
        $dryRun = $this->option('dry-run');

        $products = Product::query()->orderBy('id')->get();
        $total = $products->count();

        if ($total === 0) {
            $this->info('No products found. Nothing to do.');
            return 0;
        }

        if ($dryRun) {
            $this->info("[DRY RUN] Would assign " . $total . " product code(s) by id order.");
            $products->each(function ($p, $index) {
                $code = 'MHB-' . (1001 + $index);
                $this->line("  id={$p->id} → {$code}");
            });
            $this->info("[DRY RUN] Would set product_code_sequence.last_number to " . (1000 + $total) . ".");
            return 0;
        }

        $bar = $this->output->createProgressBar($total);
        $bar->start();

        DB::transaction(function () use ($products, $bar) {
            $num = 1001;
            foreach ($products as $product) {
                $product->update(['product_code' => 'MHB-' . $num]);
                $num++;
                $bar->advance();
            }
            DB::table('product_code_sequence')->where('id', 1)->update(['last_number' => $num - 1]);
        });

        $bar->finish();
        $this->newLine();
        $this->info("Assigned " . $total . " product code(s) (MHB-1001 … MHB-" . (1000 + $total) . "). Next generated code will be MHB-" . (1000 + $total + 1) . ".");

        return 0;
    }
}
