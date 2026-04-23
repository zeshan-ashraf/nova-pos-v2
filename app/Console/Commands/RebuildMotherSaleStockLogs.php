<?php

namespace App\Console\Commands;

use App\Models\Order;
use App\Models\StockLog;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class RebuildMotherSaleStockLogs extends Command
{
    protected $signature = 'stock:rebuild-mother-sale
        {order_id : Order ID to rebuild stock ledger for}
        {--dry-run : Preview only; no DB writes}';

    protected $description = 'Soft-delete corrupted mother_sale IN rows for an order and rebuild correct mother OUT sale rows.';

    public function handle(): int
    {
        $orderId = (int) $this->argument('order_id');
        $dryRun = (bool) $this->option('dry-run');

        if ($orderId <= 0) {
            $this->error('order_id must be a positive integer.');
            return self::FAILURE;
        }

        $order = Order::query()->with('orderDetails')->find($orderId);
        if (!$order) {
            $this->error("Order not found: {$orderId}");
            return self::FAILURE;
        }

        $corruptedQuery = StockLog::withoutGlobalScopes()
            ->where('source_id', (string) $orderId)
            ->where('source_type', 'mother_sale')
            ->where('direction', 'in')
            ->where('shop_id', '<>', 1)
            ->whereNull('deleted_at');

        $corruptedCount = (clone $corruptedQuery)->count();
        if ($corruptedCount === 0) {
            $this->info('No corruption found');
            return self::SUCCESS;
        }

        $orderDetails = $order->orderDetails;
        if ($orderDetails->isEmpty()) {
            $this->warn('Order has no order_details. Nothing to reinsert.');
            if ($dryRun) {
                $this->line("Dry run: would soft-delete {$corruptedCount} corrupted row(s).");
                return self::SUCCESS;
            }

            $deleted = (clone $corruptedQuery)->update(['deleted_at' => now()]);
            $this->info("Deleted rows: {$deleted}");
            $this->info('Inserted rows: 0');
            $this->info('Rebuild completed.');
            return self::SUCCESS;
        }

        $rowsToInsert = $this->buildMissingRows($order);
        $insertCount = count($rowsToInsert);

        if ($dryRun) {
            $this->line("Would soft-delete corrupted rows: {$corruptedCount}");
            $this->line("Would insert rebuilt rows: {$insertCount}");
            $this->info('Dry run completed.');
            return self::SUCCESS;
        }

        DB::transaction(function () use ($corruptedQuery, $rowsToInsert, &$corruptedCount): void {
            $corruptedCount = (clone $corruptedQuery)->update(['deleted_at' => now()]);
            if (!empty($rowsToInsert)) {
                StockLog::insert($rowsToInsert);
            }
        });

        $this->info("Deleted rows: {$corruptedCount}");
        $this->info("Inserted rows: {$insertCount}");
        $this->info('Rebuild completed successfully.');

        return self::SUCCESS;
    }

    /**
     * Build only missing correct OUT rows for mother shop (idempotent).
     *
     * @return array<int, array<string, mixed>>
     */
    private function buildMissingRows(Order $order): array
    {
        $desiredGroups = $order->orderDetails
            ->map(function ($d) use ($order): array {
                return [
                    'k' => $this->groupKey(
                        (int) $d->product_id,
                        (int) $d->quantity,
                        (float) ($d->cost_per_unit ?? 0),
                        (string) $order->order_date
                    ),
                    'product_id' => (int) $d->product_id,
                    'qty' => (int) $d->quantity,
                    'cost_per_unit' => (float) ($d->cost_per_unit ?? 0),
                ];
            })
            ->groupBy('k');

        $existingGroups = StockLog::withoutGlobalScopes()
            ->where('shop_id', 1)
            ->where('source_type', 'sale')
            ->where('source_id', (string) $order->id)
            ->where('direction', 'out')
            ->whereNull('deleted_at')
            ->get(['product_id', 'qty', 'cost_per_unit', 'adjustment_date'])
            ->map(function ($r): string {
                return $this->groupKey(
                    (int) $r->product_id,
                    (int) $r->qty,
                    (float) ($r->cost_per_unit ?? 0),
                    (string) $r->adjustment_date
                );
            })
            ->countBy();

        $rows = [];
        $now = now();
        $createdAt = $order->created_at ?? $now;

        /** @var Collection<int, array<string,mixed>> $groupRows */
        foreach ($desiredGroups as $key => $groupRows) {
            $needed = $groupRows->count();
            $already = (int) ($existingGroups[$key] ?? 0);
            $missing = max(0, $needed - $already);
            if ($missing === 0) {
                continue;
            }

            for ($i = 0; $i < $missing; $i++) {
                $sample = $groupRows[$i];
                $rows[] = [
                    'shop_id' => 1,
                    'product_id' => $sample['product_id'],
                    'qty' => $sample['qty'],
                    'stock_qty' => $sample['qty'],
                    'direction' => 'out',
                    'source_type' => 'sale',
                    'source_id' => (string) $order->id,
                    'adjustment_date' => $order->order_date,
                    'supplier_id' => null,
                    'cost_per_unit' => $sample['cost_per_unit'],
                    'created_at' => $createdAt,
                    'updated_at' => $now,
                ];
            }
        }

        return $rows;
    }

    private function groupKey(int $productId, int $qty, float $costPerUnit, string $adjustmentDate): string
    {
        return implode('|', [
            $productId,
            $qty,
            number_format($costPerUnit, 6, '.', ''),
            substr($adjustmentDate, 0, 10),
        ]);
    }
}

