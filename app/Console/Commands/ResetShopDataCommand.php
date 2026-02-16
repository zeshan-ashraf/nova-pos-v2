<?php

namespace App\Console\Commands;

use App\Models\Shop;
use App\Services\ShopDataResetService;
use Illuminate\Console\Command;

class ResetShopDataCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'shop:reset-data
        {shop_id : The ID of the child shop to reset}
        {--dry-run : Show what would be deleted without making changes}
        {--force : Skip confirmation prompt}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Reset all data for a child shop (orders, customers except walk-in, stock logs, etc.). Stock is reversed to mother shop. Excludes users and walk-in customers.';

    /**
     * Execute the console command.
     */
    public function handle(ShopDataResetService $service): int
    {
        $shopId = (int) $this->argument('shop_id');
        $dryRun = (bool) $this->option('dry-run');
        $force = (bool) $this->option('force');

        $shop = Shop::find($shopId);
        if (!$shop) {
            $this->error("Shop with ID {$shopId} not found.");
            return 1;
        }

        if ($shop->is_parent || !$shop->parent_shop_id) {
            $this->error('This command only supports child shops. The specified shop must have parent_shop_id.');
            return 1;
        }

        if ($dryRun) {
            $result = $service->resetShopData($shopId, true);
            if (!$result['success']) {
                $this->error($result['message']);
                return 1;
            }
            $this->info("Dry run for shop: {$shop->name} (ID: {$shopId})");
            $this->info('Mother shop ID: ' . $shop->parent_shop_id);
            $this->newLine();
            $this->table(
                ['Entity', 'Count'],
                collect($result['dry_run'] ?? [])->map(fn ($v, $k) => [str_replace('_', ' ', ucfirst($k)), $v])->values()->all()
            );
            $this->newLine();
            $this->info('No changes were made. Run without --dry-run to perform the reset.');
            return 0;
        }

        if (!$force && !$this->confirm('This will permanently delete all data for this shop (except users and walk-in customers). Stock will be reversed to the mother shop. Continue?', false)) {
            $this->info('Aborted.');
            return 0;
        }

        $result = $service->resetShopData($shopId, false);
        if (!$result['success']) {
            $this->error($result['message']);
            if (!empty($result['trace'])) {
                $this->line($result['trace']);
            }
            return 1;
        }

        $this->info($result['message']);
        return 0;
    }
}
