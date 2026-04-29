<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class FillStockLogAdjustmentDateFromCreatedAtCommand extends Command
{
    protected $signature = 'stock:fill-adjustment-date-from-created-at {--dry-run : Show affected rows without updating}';

    protected $description = 'Fill stock_logs.adjustment_date from created_at where adjustment_date is NULL (or zero-date legacy value).';

    public function handle(): int
    {
        $baseQuery = DB::table('stock_logs')
            ->where(function ($query) {
                $query->whereNull('adjustment_date')
                    ->orWhere('adjustment_date', '0000-00-00 00:00:00');
            });

        $affected = (clone $baseQuery)->count();

        if ($affected === 0) {
            $this->info('No stock_logs rows need adjustment_date backfill.');

            return Command::SUCCESS;
        }

        if ((bool) $this->option('dry-run')) {
            $this->warn('DRY RUN: no rows updated.');
            $this->info("Rows that would be updated: {$affected}");

            return Command::SUCCESS;
        }

        $updated = (clone $baseQuery)->update([
            'adjustment_date' => DB::raw('created_at'),
            'updated_at' => now(),
        ]);

        $this->info("Rows updated: {$updated}");

        return Command::SUCCESS;
    }
}

