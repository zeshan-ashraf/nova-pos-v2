<?php

namespace App\Console\Commands;

use App\Models\Product;
use App\Models\Shop;
use Illuminate\Console\Command;

class CheckParentProductIntegrity extends Command
{
    protected $signature = 'products:check-parent-integrity';
    protected $description = 'Check parent_product_id mappings for obvious integrity issues (report only, no changes).';

    public function handle(): int
    {
        $this->info('Checking parent_product_id integrity (report only, no changes)...');

        $sameShopIssues = 0;
        $nonParentIssues = 0;
        $circularIssues = 0;

        Product::query()
            ->whereNotNull('parent_product_id')
            ->orderBy('id')
            ->chunkById(500, function ($products) use (&$sameShopIssues, &$nonParentIssues, &$circularIssues) {
                foreach ($products as $child) {
                    $parent = Product::find($child->parent_product_id);
                    if (!$parent) {
                        $this->warn(sprintf(
                            'Parent missing: child_id=%d, parent_id=%d',
                            $child->id,
                            $child->parent_product_id
                        ));
                        continue;
                    }

                    // 1. Same-shop mapping
                    if ($child->shop_id && $parent->shop_id && $child->shop_id === $parent->shop_id) {
                        $sameShopIssues++;
                        $this->error(sprintf(
                            'Same-shop mapping: child_id=%d (shop=%s) -> parent_id=%d (shop=%s)',
                            $child->id,
                            $child->shop_id,
                            $parent->id,
                            $parent->shop_id
                        ));
                    }

                    // 2. Parent is not in a parent shop
                    $parentShop = $parent->shop_id ? Shop::find($parent->shop_id) : null;
                    if (!$parentShop || !$parentShop->is_parent) {
                        $nonParentIssues++;
                        $this->error(sprintf(
                            'Parent not in parent shop: child_id=%d (shop=%s) -> parent_id=%d (shop=%s)',
                            $child->id,
                            $child->shop_id,
                            $parent->id,
                            $parent->shop_id
                        ));
                    }

                    // 3. Circular reference (parent points back to child)
                    if ((int) $parent->parent_product_id === (int) $child->id) {
                        $circularIssues++;
                        $this->error(sprintf(
                            'Circular mapping: child_id=%d <-> parent_id=%d',
                            $child->id,
                            $parent->id
                        ));
                    }
                }
            });

        $this->info('Integrity check finished.');
        $this->line(sprintf('Same-shop mappings: %d', $sameShopIssues));
        $this->line(sprintf('Parent in non-parent shop: %d', $nonParentIssues));
        $this->line(sprintf('Circular mappings: %d', $circularIssues));

        return self::SUCCESS;
    }
}

