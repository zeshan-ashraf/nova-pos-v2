<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Kyslik\ColumnSortable\Sortable;

class Shop extends Model
{
    use HasFactory, Sortable;

    protected $fillable = [
        'name',
        'logo',
        'address',
        'phone',
        'owner_name',
        'is_parent',
        'parent_shop_id',
        'status',
        'invoice_policy',
    ];

    protected $casts = [
        'is_parent' => 'boolean',
        'status' => 'boolean',
    ];

    protected $sortable = [
        'name',
        'owner_name',
        'phone',
        'status',
        'is_parent',
    ];

    /**
     * URL for the shop logo. Served via route so it works on live without storage symlink.
     */
    public function getLogoUrlAttribute(): string
    {
        return $this->logo
            ? route('shop.logo', ['filename' => $this->logo])
            : asset('assets/images/user/1.png');
    }

    public function scopeFilter($query, array $filters): void
    {
        $query->when($filters['search'] ?? false, function ($query, $search) {
            $query->where(function ($innerQuery) use ($search) {
                $innerQuery->where('name', 'like', '%' . $search . '%')
                    ->orWhere('owner_name', 'like', '%' . $search . '%')
                    ->orWhere('phone', 'like', '%' . $search . '%');
            });
        });

        $query->when(array_key_exists('status', $filters) && $filters['status'] !== null && $filters['status'] !== '', function ($query) use ($filters) {
            $query->where('status', (bool) $filters['status']);
        });
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_shop_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_shop_id');
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class, 'shop_id');
    }

    public function banks(): BelongsToMany
    {
        return $this->belongsToMany(Bank::class)->withPivot('id')->withTimestamps();
    }

    public function suppliers(): HasMany
    {
        return $this->hasMany(Supplier::class, 'shop_id');
    }

    public function suppliersAsMotherShop(): HasMany
    {
        return $this->hasMany(Supplier::class, 'mother_shop_id');
    }

    protected static function boot()
    {
        parent::boot();

        static::created(function ($shop) {
            // Only setup child shops (non-parent shops)
            if (!$shop->is_parent) {
                app(\App\Services\ShopSetupService::class)->setupChildShop($shop);
            }
        });
    }
}


