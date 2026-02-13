<?php

namespace App\Traits;

use App\Support\ActiveShop;

/**
 * Multi-tenant shop scope for models that belong to a shop.
 * - Applies a global scope so all queries are filtered by the active shop (session) or user's shop_id.
 * - Automatically sets shop_id on create when not already set.
 * - Skipped when running in console (e.g. Artisan, seeders) so CLI operations are not restricted.
 *
 * Use withoutGlobalScope('shop') when you need to query across shops (e.g. admin or transfer flows).
 */
trait BelongsToShop
{
    protected static function bootBelongsToShop(): void
    {
        static::addGlobalScope('shop', function ($builder) {
            if (! app()->runningInConsole() && auth()->check()) {
                $shopId = ActiveShop::id() ?? auth()->user()->shop_id;
                if ($shopId !== null) {
                    $builder->where('shop_id', $shopId);
                }
            }
        });

        static::creating(function ($model) {
            if (! app()->runningInConsole() && auth()->check() && empty($model->shop_id)) {
                $model->shop_id = ActiveShop::id() ?? auth()->user()->shop_id;
            }
        });
    }
}
