<?php

namespace App\Console\Commands;

use App\Models\Product;
use App\Models\Purchase;
use App\Models\PurchaseDetail;
use App\Models\StockLog;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Safely aligns product buying_price and related cost snapshots on stock_logs / purchase_details
 * for a specific product + purchase + shop. Does not change quantities or delete rows.
 */
class FixProductCost extends Command
{
    protected $signature = 'product:fix-cost
                            {product_code : Product code (exact match)}
                            {source_id : Purchase ID}
                            {shop_id : Shop ID}
                            {buying_price : New buying price (decimal)}
                            {--dry-run : Preview changes without updating the database}
                            {--force : Skip the confirmation prompt (non-interactive / CI)}';

    protected $description = 'Update product buying_price and matching purchase stock_logs / purchase_details cost fields for one product line.';

    public function handle(): int
    {
        $productCode = trim((string) $this->argument('product_code'));
        $purchaseId = (int) $this->argument('source_id');
        $shopId = (int) $this->argument('shop_id');
        $newPrice = (float) $this->argument('buying_price');
        $dryRun = (bool) $this->option('dry-run');
        $force = (bool) $this->option('force');

        if ($productCode === '') {
            $this->error('product_code cannot be empty.');

            return Command::FAILURE;
        }
        if ($purchaseId < 1 || $shopId < 1) {
            $this->error('source_id and shop_id must be positive integers.');

            return Command::FAILURE;
        }
        if ($newPrice < 0) {
            $this->error('buying_price must be zero or positive.');

            return Command::FAILURE;
        }

        $product = Product::query()
            ->withoutGlobalScope('shop')
            ->where('product_code', $productCode)
            ->where('shop_id', $shopId)
            ->first();

        if ($product === null) {
            $this->error("No product found for product_code=\"{$productCode}\" and shop_id={$shopId}.");

            return Command::FAILURE;
        }

        $purchase = Purchase::query()
            ->whereKey($purchaseId)
            ->where('shop_id', $shopId)
            ->first();

        if ($purchase === null) {
            $this->error("No purchase found for id={$purchaseId} and shop_id={$shopId}.");

            return Command::FAILURE;
        }

        $detailExists = PurchaseDetail::query()
            ->where('purchase_id', $purchaseId)
            ->where('product_id', $product->id)
            ->exists();

        if (! $detailExists) {
            $this->error("Purchase {$purchaseId} has no line for product_id {$product->id}.");

            return Command::FAILURE;
        }

        $stockLogQuery = StockLog::query()
            ->where('source_type', 'purchase')
            ->where('shop_id', $shopId)
            ->where('product_id', $product->id)
            ->where(function ($q) use ($purchaseId) {
                $q->where('source_id', (string) $purchaseId)
                    ->orWhere('source_id', $purchaseId);
            });

        $stockLogsCount = (clone $stockLogQuery)->count();

        $detailsQuery = PurchaseDetail::query()
            ->where('purchase_id', $purchaseId)
            ->where('product_id', $product->id);

        $detailsCount = (clone $detailsQuery)->count();

        if ($stockLogsCount === 0) {
            $this->warn('No stock_logs matched (source_type=purchase, this product, shop, purchase). Continuing to update product + purchase_details only.');
        }

        $oldPrice = (float) ($product->buying_price ?? 0);

        if ($dryRun) {
            $this->info('DRY RUN — no database changes.');
            $this->table(
                ['Field', 'Value'],
                [
                    ['product_id', (string) $product->id],
                    ['old buying_price', number_format($oldPrice, 4, '.', '')],
                    ['new buying_price', number_format($newPrice, 4, '.', '')],
                    ['affected stock_logs', (string) $stockLogsCount],
                    ['affected purchase_details', (string) $detailsCount],
                ]
            );

            return Command::SUCCESS;
        }

        if (! $force && ! $this->confirm('Are you sure you want to override cost for this product / purchase?')) {
            $this->warn('Aborted.');

            return Command::SUCCESS;
        }

        $updatedStockLogs = 0;
        $updatedDetails = 0;

        try {
            DB::transaction(function () use ($product, $purchaseId, $shopId, $newPrice, &$updatedStockLogs, &$updatedDetails) {
                /** @var Product $lockedProduct */
                $lockedProduct = Product::query()
                    ->withoutGlobalScope('shop')
                    ->whereKey($product->id)
                    ->lockForUpdate()
                    ->firstOrFail();

                $lockedProduct->buying_price = $newPrice;
                $lockedProduct->save();

                $updatedStockLogs = StockLog::query()
                    ->where('source_type', 'purchase')
                    ->where('shop_id', $shopId)
                    ->where('product_id', $product->id)
                    ->where(function ($q) use ($purchaseId) {
                        $q->where('source_id', (string) $purchaseId)
                            ->orWhere('source_id', $purchaseId);
                    })
                    ->update([
                        'price' => $newPrice,
                        'cost_per_unit' => $newPrice,
                    ]);

                $details = PurchaseDetail::query()
                    ->where('purchase_id', $purchaseId)
                    ->where('product_id', $product->id)
                    ->lockForUpdate()
                    ->get();

                foreach ($details as $detail) {
                    $qty = (int) ($detail->quantity ?? 0);
                    $unitcost = $newPrice;
                    $landedUnit = $newPrice;
                    $itemDiscount = (float) ($detail->item_discount ?? 0);
                    $total = max(0, ($qty * $unitcost) - $itemDiscount);
                    $landedTotal = $qty * $landedUnit;
                    $allocated = $landedTotal - ($qty * $unitcost);

                    $detail->update([
                        'unitcost' => $unitcost,
                        'landed_unit_cost' => $landedUnit,
                        'total' => round($total, 4),
                        'landed_total' => round($landedTotal, 4),
                        'allocated_expense' => round($allocated, 4),
                    ]);
                    $updatedDetails++;
                }
            });
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return Command::FAILURE;
        }

        $this->info('Product updated: buying_price set.');
        $this->line("Stock logs updated: {$updatedStockLogs} row(s).");
        $this->line("Purchase details updated: {$updatedDetails} row(s).");
        $this->info('Status: Executed');

        return Command::SUCCESS;
    }
}
