<?php

namespace App\Console\Commands;

use App\Models\Product;
use App\Models\Shop;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class BackfillParentProductId extends Command
{
    protected $signature = 'products:backfill-parent {--dry-run : Do not update database; only print planned updates}';
    protected $description = 'Backfill products.parent_product_id by matching product_code across shops (preferring parent shops).';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');

        $this->info('Starting backfill for products.parent_product_id' . ($dryRun ? ' (DRY RUN)' : ''));

        $updated = 0;
        $skipped = 0;

        Product::query()
            ->whereNull('parent_product_id')
            ->whereNotNull('product_code')
            ->orderBy('id')
            ->chunkById(500, function ($products) use ($dryRun, &$updated, &$skipped) {
                foreach ($products as $child) {
                    $childShop = $child->shop_id ? Shop::find($child->shop_id) : null;

                    // Safety: never assign parent_product_id to products that already belong to a parent shop.
                    if ($childShop && $childShop->is_parent) {
                        $skipped++;
                        Log::info('Backfill parent_product_id: skipped parent-shop product', [
                            'product_id' => $child->id,
                            'shop_id' => $child->shop_id,
                            'product_code' => $child->product_code,
                        ]);
                        continue;
                    }

                    $match = $this->findUniqueParentMatch($child->product_code, (int) $child->shop_id, (int) $child->id);

                    if ($match === null) {
                        $skipped++;
                        continue;
                    }

                    // Prevent self or circular mapping and enforce parent shop.
                    if ($match->id === $child->id) {
                        Log::warning('Backfill parent_product_id: self-mapping prevented', [
                            'product_id' => $child->id,
                            'shop_id' => $child->shop_id,
                        ]);
                        $skipped++;
                        continue;
                    }

                    $parentShop = $match->shop_id ? Shop::find($match->shop_id) : null;
                    if (!$parentShop || !$parentShop->is_parent || $parentShop->id === $child->shop_id) {
                        Log::warning('Backfill parent_product_id: invalid parent candidate (same or non-parent shop)', [
                            'child_product_id' => $child->id,
                            'child_shop_id' => $child->shop_id,
                            'parent_product_id' => $match->id,
                            'parent_shop_id' => $match->shop_id,
                        ]);
                        $skipped++;
                        continue;
                    }

                    if ($match->parent_product_id === $child->id) {
                        Log::warning('Backfill parent_product_id: circular mapping prevented', [
                            'child_product_id' => $child->id,
                            'parent_product_id' => $match->id,
                        ]);
                        $skipped++;
                        continue;
                    }

                    if ($dryRun) {
                        $this->line(sprintf(
                            'WOULD UPDATE: child_product_id=%d, name="%s", code=%s, child_shop=%s -> parent_product_id=%d, parent_shop=%s',
                            $child->id,
                            (string) ($child->product_name ?? ''),
                            (string) ($child->product_code ?? ''),
                            (string) ($child->shop_id ?? 'null'),
                            $match->id,
                            (string) ($match->shop_id ?? 'null')
                        ));
                        $updated++;
                        continue;
                    }

                    $child->parent_product_id = $match->id;
                    $child->save();
                    $updated++;
                }
            });

        $this->info("Done. matched={$updated}, skipped={$skipped}" . ($dryRun ? ' (dry-run)' : ''));
        return self::SUCCESS;
    }

    /**
     * Find a unique parent/master product match for a child product.
     * Rules:
     * - Only match against products in parent shops (shops.is_parent = true).
     * - product_code must match exactly.
     * - child shop itself must NOT be a parent shop.
     * - If 0 or >1 candidates → return null.
     */
    private function findUniqueParentMatch(?string $productCode, int $childShopId, int $childProductId): ?Product
    {
        $code = trim((string) $productCode);
        if ($code === '') {
            return null;
        }

        $childShop = $childShopId ? Shop::find($childShopId) : null;
        if ($childShop && $childShop->is_parent) {
            Log::info('Backfill parent_product_id: skipped parent shop product in matcher', [
                'child_product_id' => $childProductId,
                'child_shop_id' => $childShopId,
                'product_code' => $code,
            ]);
            return null;
        }

        // Only match against products in parent shops.
        $parentShopIds = Shop::query()
            ->where('is_parent', true)
            ->pluck('id')
            ->all();

        if (empty($parentShopIds)) {
            Log::warning('Backfill parent_product_id: no parent shops defined', []);
            return null;
        }

        $candidates = Product::query()
            ->where('product_code', $code)
            ->whereIn('shop_id', $parentShopIds)
            ->get(['id', 'shop_id', 'product_code', 'product_name', 'parent_product_id']);

        if ($candidates->isEmpty()) {
            Log::info('Backfill parent_product_id: no parent match', [
                'child_product_id' => $childProductId,
                'child_shop_id' => $childShopId,
                'product_code' => $code,
            ]);
            return null;
        }

        if ($candidates->count() !== 1) {
            Log::warning('Backfill parent_product_id: multiple parent matches; skipping', [
                'child_product_id' => $childProductId,
                'child_shop_id' => $childShopId,
                'product_code' => $code,
                'match_count' => $candidates->count(),
                'matches' => $candidates->map(fn ($p) => ['id' => $p->id, 'shop_id' => $p->shop_id])->all(),
            ]);
            return null;
        }

        return $candidates->first();
    }
}

