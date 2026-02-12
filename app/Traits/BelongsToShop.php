<?php

namespace App\Traits;

/**
 * Multi-tenant shop scope for models that belong to a shop.
 * - Applies a global scope so all queries are filtered by the logged-in user's shop_id.
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
                $builder->where('shop_id', auth()->user()->shop_id);
            }
        });

        static::creating(function ($model) {
            if (! app()->runningInConsole() && auth()->check() && empty($model->shop_id)) {
                $model->shop_id = auth()->user()->shop_id;
            }
        });
    }
}
