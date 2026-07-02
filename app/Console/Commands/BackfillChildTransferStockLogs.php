<?php

namespace App\Console\Commands;

use App\Models\Purchase;
use App\Models\StockLog;
use App\Support\InterShopTransferStatus;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class BackfillChildTransferStockLogs extends Command
{
    protected $signature = 'stock:backfill-child-transfer-logs
        {order_id? : Mother sale order ID to backfill; omit to process all completed inter-shop transfers}
        {--dry-run : Preview only; no DB writes}';

    protected $description = 'Backfill missing child-shop "in" stock_logs for completed mother→child inter-shop transfers (the purchase-side stock log that was skipped by the shop global scope).';

    public function handle(): int
    {
        $orderId = $this->argument('order_id') !== null ? (int) $this->argument('order_id') : null;
        $dryRun = (bool) $this->option('dry-run');

        // System-generated child purchases hold the child-side of each transfer.
        // Running in console, so the shop global scope is already skipped.
        $query = Purchase::query()
            ->with(['purchaseDetails'])
            ->where('is_system_generated', true)
            ->whereNotNull('source_sale_id')
            ->where('purchase_status', InterShopTransferStatus::COMPLETED);

        if ($orderId !== null) {
            $query->where('source_sale_id', $orderId);
        }

        $purchases = $query->get();

        if ($purchases->isEmpty()) {
            $this->info($orderId !== null
                ? "No completed inter-shop transfer purchase found for order {$orderId}."
                : 'No completed inter-shop transfer purchases found.');

            return self::SUCCESS;
        }

        $totalInserted = 0;
        $totalRowsToInsert = [];

        foreach ($purchases as $purchase) {
            $rows = $this->buildMissingRows($purchase);
            if (empty($rows)) {
                continue;
            }

            $this->line(sprintf(
                'Purchase #%d (order %s, child shop %d): %d missing stock_log row(s).',
                $purchase->id,
                (string) $purchase->source_sale_id,
                (int) $purchase->shop_id,
                count($rows)
            ));

            $totalRowsToInsert = array_merge($totalRowsToInsert, $rows);
            $totalInserted += count($rows);
        }

        if ($totalInserted === 0) {
            $this->info('All child-shop stock logs are already present. Nothing to backfill.');

            return self::SUCCESS;
        }

        if ($dryRun) {
            $this->info("Dry run: would insert {$totalInserted} child-shop stock_log row(s).");

            return self::SUCCESS;
        }

        DB::transaction(function () use ($totalRowsToInsert): void {
            StockLog::insert($totalRowsToInsert);
        });

        $this->info("Inserted {$totalInserted} child-shop stock_log row(s). Backfill completed.");

        return self::SUCCESS;
    }

    /**
     * Build only the missing child-shop "in" purchase stock_log rows for a transfer purchase (idempotent).
     *
     * @return array<int, array<string, mixed>>
     */
    private function buildMissingRows(Purchase $purchase): array
    {
        $now = now();
        $createdAt = $purchase->created_at ?? $now;

        $existingCounts = StockLog::withoutGlobalScopes()
            ->where('shop_id', (int) $purchase->shop_id)
            ->where('source_type', 'purchase')
            ->where('source_id', (string) $purchase->id)
            ->where('direction', 'in')
            ->whereNull('deleted_at')
            ->get(['product_id', 'qty'])
            ->groupBy(fn ($r): string => $this->groupKey((int) $r->product_id, (int) $r->qty))
            ->map->count();

        $desiredGroups = $purchase->purchaseDetails
            ->filter(fn ($pd): bool => (int) $pd->quantity > 0)
            ->map(function ($pd): array {
                return [
                    'k' => $this->groupKey((int) $pd->product_id, (int) $pd->quantity),
                    'product_id' => (int) $pd->product_id,
                    'qty' => (int) $pd->quantity,
                    'price' => (float) ($pd->unitcost ?? 0),
                ];
            })
            ->groupBy('k');

        $rows = [];
        foreach ($desiredGroups as $key => $groupRows) {
            $needed = $groupRows->count();
            $already = (int) ($existingCounts[$key] ?? 0);
            $missing = max(0, $needed - $already);
            if ($missing === 0) {
                continue;
            }

            for ($i = 0; $i < $missing; $i++) {
                $sample = $groupRows[$i];
                $rows[] = [
                    'shop_id' => (int) $purchase->shop_id,
                    'product_id' => $sample['product_id'],
                    'supplier_id' => (int) $purchase->supplier_id,
                    'qty' => $sample['qty'],
                    'stock_qty' => $sample['qty'],
                    'direction' => 'in',
                    'source_type' => 'purchase',
                    'source_id' => (string) $purchase->id,
                    'price' => $sample['price'],
                    'created_at' => $createdAt,
                    'updated_at' => $now,
                ];
            }
        }

        return $rows;
    }

    private function groupKey(int $productId, int $qty): string
    {
        return $productId.'|'.$qty;
    }
}
