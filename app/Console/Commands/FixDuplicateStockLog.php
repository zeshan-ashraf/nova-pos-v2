<?php

namespace App\Console\Commands;

use App\Models\Product;
use App\Models\StockLog;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Reverses the stock impact of one or more stock_log rows and soft-deletes them.
 * Used to correct duplicate/erroneous stock_logs without hard-deleting audit history.
 */
class FixDuplicateStockLog extends Command
{
    protected $signature = 'stock:fix-duplicate
                            {stock_log_ids : One or more stock_logs IDs, comma-separated (e.g. 705 or 705,706)}
                            {--dry-run : Preview reversal without changing the database}';

    protected $description = 'Reverse stock_log effect(s) on product_store, then soft-delete those logs (use --dry-run to preview).';

    public function handle(): int
    {
        $raw = (string) $this->argument('stock_log_ids');
        $ids = $this->parseIds($raw);
        if ($ids === []) {
            $this->error('Provide at least one positive integer stock_log_id (e.g. 705 or 705,706).');

            return Command::FAILURE;
        }

        $dryRun = (bool) $this->option('dry-run');

        if (count($ids) > 1) {
            $this->info('Processing '.count($ids).' stock log id(s): '.implode(', ', $ids));
        }

        $hadFailure = false;

        foreach ($ids as $id) {
            $result = $this->processStockLog($id, $dryRun);
            if (! $result) {
                $hadFailure = true;
                if (! $dryRun) {
                    $this->error("Stopped after failure on stock_log_id {$id} (earlier IDs may already be committed).");

                    return Command::FAILURE;
                }
            }
        }

        if ($hadFailure && $dryRun) {
            $this->error('Dry run completed with one or more failures (see above).');

            return Command::FAILURE;
        }

        if ($dryRun) {
            $this->info('Dry run complete — no changes were made.');
        } else {
            $this->info('Done: all requested stock_logs processed.');
        }

        return Command::SUCCESS;
    }

    /**
     * @return int[] sorted unique positive integers
     */
    private function parseIds(string $raw): array
    {
        $parts = preg_split('/[\s,]+/', $raw, -1, PREG_SPLIT_NO_EMPTY);
        $ids = [];
        foreach ($parts as $part) {
            $n = (int) trim($part);
            if ($n > 0) {
                $ids[$n] = $n;
            }
        }

        return array_values($ids);
    }

    private function processStockLog(int $id, bool $dryRun): bool
    {
        /** @var StockLog|null $log */
        $log = StockLog::query()->find($id);
        if ($log === null) {
            $this->error("Stock log id {$id} not found or already soft-deleted.");

            return false;
        }

        $product = Product::query()->find($log->product_id);
        if ($product === null) {
            $this->error("Product id {$log->product_id} not found for stock_log {$id}.");

            return false;
        }

        $qty = (int) $log->qty;
        if ($qty < 0) {
            $this->error("Invalid stock_log.qty ({$log->qty}) for id {$id}: expected non-negative integer.");

            return false;
        }

        $direction = strtolower((string) ($log->direction ?? ''));
        if (! in_array($direction, ['in', 'out'], true)) {
            $this->error("Invalid stock_log.direction \"{$log->direction}\" for id {$id}: expected \"in\" or \"out\".");

            return false;
        }

        $currentStore = (int) ($product->product_store ?? 0);
        $newStore = $this->computeStockAfterReversal($currentStore, $qty, $direction);

        if ($newStore < 0) {
            $this->error(
                "[{$id}] Reversal would make product_store negative (current={$currentStore}, qty={$qty}, direction={$direction}). Aborting."
            );

            return false;
        }

        $this->line('');
        $this->info("--- Stock log id {$id} ---");
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
            return true;
        }

        try {
            DB::transaction(function () use ($id) {
                /** @var StockLog $lockedLog */
                $lockedLog = StockLog::query()->whereKey($id)->lockForUpdate()->firstOrFail();

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
            $this->error("[{$id}] {$e->getMessage()}");

            return false;
        }

        $this->info("[{$id}] Executed: product_store updated and stock_log soft-deleted.");

        return true;
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
