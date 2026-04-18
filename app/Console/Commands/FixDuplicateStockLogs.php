<?php

namespace App\Console\Commands;

use App\Models\Product;
use App\Models\StockLog;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Finds duplicate stock_logs for the same (shop, source_type, source_id, product_id),
 * keeps the lowest id, reverses the duplicate rows' effect on product_store, then soft-deletes them.
 *
 * Does not use raw SQL for mutations; uses Eloquent + query builder inside transactions.
 */
class FixDuplicateStockLogs extends Command
{
    private const STOCK_COLUMN = 'product_store';

    /**
     * @var string
     */
    protected $signature = 'stock:fix-duplicates
                            {--shop_id= : Shop ID (required)}
                            {--source_id= : Source ID e.g. purchase id (required)}
                            {--source_type= : Source type e.g. purchase (required)}
                            {--dry-run : Show planned actions without changing the database}';

    /**
     * @var string
     */
    protected $description = 'Detect duplicate stock logs for a source, reverse extra stock impact, soft-delete duplicates';

    public function handle(): int
    {
        $shopId = $this->option('shop_id');
        $sourceId = $this->option('source_id');
        $sourceType = $this->option('source_type');
        $dryRun = (bool) $this->option('dry-run');

        if ($shopId === null || $shopId === '' || $sourceId === null || $sourceId === '' || $sourceType === null || $sourceType === '') {
            $this->error('All of --shop_id, --source_id, and --source_type are required for a scoped, safe fix.');

            return self::FAILURE;
        }

        $shopId = (int) $shopId;
        $sourceIdStr = (string) $sourceId;
        $sourceTypeStr = (string) $sourceType;

        $logs = StockLog::query()
            ->where('shop_id', $shopId)
            ->where('source_type', $sourceTypeStr)
            ->where('source_id', $sourceIdStr)
            ->orderBy('id')
            ->get();

        if ($logs->isEmpty()) {
            $this->info('No stock_logs found for the given filters (non-deleted rows only).');

            return self::SUCCESS;
        }

        /** @var \Illuminate\Support\Collection<string, \Illuminate\Support\Collection<int, StockLog>> $byProduct */
        $byProduct = $logs->groupBy('product_id');
        $duplicateGroups = $byProduct->filter(fn ($group) => $group->count() > 1);

        if ($duplicateGroups->isEmpty()) {
            $this->info('No duplicate stock_logs (per product_id) found for the given filters.');

            return self::SUCCESS;
        }

        if ($dryRun) {
            return $this->runDryRun($duplicateGroups);
        }

        return $this->runFix($duplicateGroups);
    }

    /**
     * @param \Illuminate\Support\Collection<int, \Illuminate\Support\Collection<int, StockLog>> $duplicateGroups
     */
    private function runDryRun($duplicateGroups): int
    {
        $this->warn('[DRY RUN] No database changes will be made.');
        foreach ($duplicateGroups as $productId => $rows) {
            $sorted = $rows->sortBy('id')->values();
            $keep = $sorted->first();
            $duplicates = $sorted->slice(1)->values();
            foreach ($duplicates as $log) {
                $qty = (int) ($log->qty ?? 0);
                $direction = $this->resolveDirection($log);
                if ($direction === null) {
                    $this->line(sprintf(
                        '[DRY RUN] Product %s | Log %s | SKIP (cannot resolve direction)',
                        $productId,
                        $log->id
                    ));

                    continue;
                }
                $delta = $this->reversalDeltaForProductStore($direction, $qty);
                $this->line(sprintf(
                    '[DRY RUN] Product %s | Log %s | Qty %s | Direction %s | Stock impact: %+d',
                    $productId,
                    $log->id,
                    $qty,
                    $direction,
                    $delta
                ));
            }
            $this->comment(sprintf('  (keeping log id %s for product %s)', $keep->id, $productId));
        }

        return self::SUCCESS;
    }

    /**
     * @param \Illuminate\Support\Collection<int, \Illuminate\Support\Collection<int, StockLog>> $duplicateGroups
     */
    private function runFix($duplicateGroups): int
    {
        $totalDuplicates = 0;
        $totalStockRowsAdjusted = 0;

        try {
            DB::transaction(function () use ($duplicateGroups, &$totalDuplicates, &$totalStockRowsAdjusted) {
                foreach ($duplicateGroups as $productId => $rows) {
                    $sorted = $rows->sortBy('id')->values();
                    $duplicates = $sorted->slice(1)->values();

                    foreach ($duplicates as $log) {
                        $logId = (int) $log->id;
                        $log = StockLog::query()->lockForUpdate()->find($logId);
                        if ($log === null) {
                            $this->warn("Log id {$logId} disappeared during lock; skipping.");

                            continue;
                        }

                        $qty = (int) ($log->qty ?? 0);
                        if ($qty <= 0) {
                            $this->warn("Log {$log->id}: qty is 0 or missing; soft-deleting without stock change.");
                            $log->delete();
                            $totalDuplicates++;

                            continue;
                        }

                        $direction = $this->resolveDirection($log);
                        if ($direction === null) {
                            throw new \RuntimeException(
                                "Cannot resolve direction for stock_log id {$log->id}. Fix data or handle manually."
                            );
                        }

                        $product = Product::query()->lockForUpdate()->find((int) $productId);
                        if ($product === null) {
                            $this->warn("Product id {$productId} not found; skipping duplicate log {$log->id}.");

                            continue;
                        }

                        $current = (int) ($product->{self::STOCK_COLUMN} ?? 0);
                        $delta = $this->reversalDeltaForProductStore($direction, $qty);
                        $newStock = $current + $delta;

                        if ($newStock < 0) {
                            throw new \RuntimeException(
                                "Reversing duplicate log {$log->id} would make product {$productId} stock negative (current {$current}, delta {$delta}). Aborting transaction."
                            );
                        }

                        $product->update([self::STOCK_COLUMN => $newStock]);
                        $totalStockRowsAdjusted++;

                        $log->delete();
                        $totalDuplicates++;

                        $this->line("Fixed duplicate log {$log->id} (product {$productId}): stock {$current} → {$newStock}.");
                    }
                }
            });
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info("Total duplicates soft-deleted: {$totalDuplicates}");
        $this->info("Products with stock adjusted: {$totalStockRowsAdjusted}");

        return self::SUCCESS;
    }

    /**
     * Reversing an 'in' log removes qty from store; reversing 'out' adds qty back.
     */
    private function reversalDeltaForProductStore(string $direction, int $qty): int
    {
        if ($direction === 'in') {
            return -$qty;
        }
        if ($direction === 'out') {
            return $qty;
        }

        throw new \InvalidArgumentException('Invalid direction: ' . $direction);
    }

    private function resolveDirection(StockLog $log): ?string
    {
        $d = strtolower(trim((string) ($log->direction ?? '')));
        if ($d === 'in' || $d === 'out') {
            return $d;
        }

        // Legacy rows may only have stock_qty sign.
        $stockQty = $log->stock_qty;
        if ($stockQty !== null) {
            if ((int) $stockQty > 0) {
                return 'in';
            }
            if ((int) $stockQty < 0) {
                return 'out';
            }
        }

        return null;
    }
}
