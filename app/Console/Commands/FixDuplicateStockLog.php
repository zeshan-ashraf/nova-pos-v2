<?php

namespace App\Console\Commands;

use App\Models\Product;
use App\Models\StockLog;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Reverses the stock impact of a single stock_log row and soft-deletes it.
 * Used to correct duplicate/erroneous stock_logs without hard-deleting audit history.
 */
class FixDuplicateStockLog extends Command
{
    protected $signature = 'stock:fix-duplicate
                            {stock_log_id : Primary key of the stock_logs row to reverse and soft-delete}
                            {--dry-run : Preview reversal without changing the database}';

    protected $description = 'Reverse one stock_log\'s effect on product_store, then soft-delete that log (use --dry-run to preview).';

    public function handle(): int
    {
        $id = (int) $this->argument('stock_log_id');
        if ($id < 1) {
            $this->error('stock_log_id must be a positive integer.');

            return Command::FAILURE;
        }

        $dryRun = (bool) $this->option('dry-run');

        /** @var StockLog|null $log */
        $log = StockLog::query()->find($id);
        if ($log === null) {
            $this->error("Stock log id {$id} not found or already soft-deleted.");

            return Command::FAILURE;
        }

        $product = Product::query()->find($log->product_id);
        if ($product === null) {
            $this->error("Product id {$log->product_id} not found for stock_log {$id}.");

            return Command::FAILURE;
        }

        $qty = (int) $log->qty;
        if ($qty < 0) {
            $this->error("Invalid stock_log.qty ({$log->qty}): expected non-negative integer.");

            return Command::FAILURE;
        }

        $direction = strtolower((string) ($log->direction ?? ''));
        if (! in_array($direction, ['in', 'out'], true)) {
            $this->error("Invalid stock_log.direction \"{$log->direction}\": expected \"in\" or \"out\".");

            return Command::FAILURE;
        }

        $currentStore = (int) ($product->product_store ?? 0);
        $newStore = $this->computeStockAfterReversal($currentStore, $qty, $direction);

        if ($newStore < 0) {
            $this->error(
                "Reversal would make product_store negative (current={$currentStore}, qty={$qty}, direction={$direction}). Aborting."
            );

            return Command::FAILURE;
        }

        $this->line('');
        $this->info('--- Stock log reversal preview ---');
        $this->table(
            ['Field', 'Value'],
            [
                ['stock_log_id', (string) $id],
                ['product_id', (string) $product->id],
                ['direction', $direction],
                ['qty', (string) $qty],
                ['current product_store', (string) $currentStore],
                ['after reversal (product_store)', (string) $newStore],
                ['Status', $dryRun ? 'Dry Run (no DB changes)' : 'Will execute in transaction'],
            ]
        );

        if ($dryRun) {
            $this->info('Dry run complete — no changes were made.');

            return Command::SUCCESS;
        }

        try {
            DB::transaction(function () use ($id, $newStore) {
                // Re-fetch with row lock inside the transaction.
                /** @var StockLog $lockedLog */
                $lockedLog = StockLog::query()->whereKey($id)->lockForUpdate()->firstOrFail();

                if ($lockedLog->trashed()) {
                    throw new \RuntimeException('Stock log was deleted before update could complete.');
                }

                /** @var Product $lockedProduct */
                $lockedProduct = Product::query()
                    ->whereKey($lockedLog->product_id)
                    ->lockForUpdate()
                    ->firstOrFail();

                $q = (int) $lockedLog->qty;
                $dir = strtolower((string) ($lockedLog->direction ?? ''));
                $store = (int) ($lockedProduct->product_store ?? 0);
                $reversed = $this->computeStockAfterReversal($store, $q, $dir);

                if ($reversed < 0) {
                    throw new \RuntimeException(
                        "Reversal would make product_store negative (current={$store}, qty={$q}, direction={$dir})."
                    );
                }

                $lockedProduct->product_store = $reversed;
                $lockedProduct->save();

                $lockedLog->delete();
            });
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return Command::FAILURE;
        }

        $this->info('Executed successfully: product_store updated and stock_log soft-deleted.');

        return Command::SUCCESS;
    }

    /**
     * Reverse the effect this log originally had on stock.
     * "in" increased stock → reversal subtracts; "out" decreased stock → reversal adds.
     */
    private function computeStockAfterReversal(int $currentStore, int $qty, string $direction): int
    {
        if ($direction === 'in') {
            return $currentStore - $qty;
        }

        return $currentStore + $qty;
    }
}
