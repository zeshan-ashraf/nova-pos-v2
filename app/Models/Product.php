<?php

namespace App\Models;

use App\Traits\BelongsToShop;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Kyslik\ColumnSortable\Sortable;

class Product extends Model
{
    use BelongsToShop, HasFactory, Sortable;

    protected $fillable = [
        'product_name',
        'category_id',
        'supplier_id',
        'shop_id',
        'parent_product_id',
        'product_code',
        'product_garage',
        'product_image',
        'product_store',
        'low_stock_warning',
        'buying_date',
        'expire_date',
        'buying_price',
        'selling_price',
        'status',
    ];

    public $sortable = [
        'product_name',
        'product_code',
        'selling_price',
    ];

    protected $guarded = [
        'id',
    ];

    /**
     * buying_price: moving weighted average cost (updated on purchase only).
     */
    protected $casts = [
        'buying_price' => 'float',
    ];

    protected $with = [
        'shop'
    ];

    /**
     * Category relation (unscoped). Use sameShopCategory for display when category must belong to product's shop.
     */
    public function category()
    {
        return $this->belongsTo(Category::class, 'category_id');
    }

    /**
     * Category scoped to the product's shop. Returns null when category belongs to another shop.
     * Use this when displaying category name so child-shop products don't show mother-shop categories.
     */
    public function sameShopCategory()
    {
        return $this->belongsTo(Category::class, 'category_id')->where(function ($query) {
            if ($this->shop_id === null) {
                $query->whereNull('categories.shop_id');
            } else {
                $query->where('categories.shop_id', $this->shop_id);
            }
        });
    }

    /**
     * Eager load sameShopCategory for a collection of products (avoids N+1 with scoped relation).
     * Loads categories without global shop scope. Matches (category_id, product.shop_id); if not found,
     * falls back to category with same id and shop_id null (global categories).
     */
    public static function eagerLoadSameShopCategory(Collection $products): void
    {
        $withCategory = $products->filter(fn (Product $p) => $p->category_id !== null);
        if ($withCategory->isEmpty()) {
            $products->each->setRelation('sameShopCategory', null);
            return;
        }

        $categoryIds = $withCategory->pluck('category_id')->unique()->values()->all();
        // Load all categories we might need: without global shop scope so we get every shop's + global
        $allCategories = Category::withoutGlobalScope('shop')
            ->whereIn('id', $categoryIds)
            ->get();

        // Key by "id-shop_id" for lookup; also keep by "id-null" for global fallback
        $byKey = $allCategories->keyBy(fn (Category $c) => $c->id . '-' . ($c->shop_id ?? 'null'));
        $byIdGlobal = $allCategories->whereNull('shop_id')->keyBy('id');

        foreach ($products as $product) {
            if ($product->category_id === null) {
                $product->setRelation('sameShopCategory', null);
                continue;
            }
            $key = $product->category_id . '-' . ($product->shop_id ?? 'null');
            $category = $byKey->get($key) ?? $byIdGlobal->get($product->category_id);
            $product->setRelation('sameShopCategory', $category);
        }
    }

    public function supplier(){
        return $this->belongsTo(Supplier::class, 'supplier_id');
    }

    public function parent()
    {
        return $this->belongsTo(Product::class, 'parent_product_id');
    }

    public function children()
    {
        return $this->hasMany(Product::class, 'parent_product_id');
    }

    /**
     * Master product resolver for multi-shop product mapping.
     * If this product is a child-shop clone, parent_product_id points to the mother/master product.
     */
    public function getMasterProduct(): Product
    {
        if ($this->parent_product_id) {
            // Prefer already-loaded relation to avoid extra queries.
            return $this->relationLoaded('parent') && $this->parent ? $this->parent : (Product::find($this->parent_product_id) ?? $this);
        }

        return $this;
    }

    /**
     * Unified product name for UI: always show master (parent) product name when mapped.
     */
    public function getResolvedNameAttribute(): ?string
    {
        return $this->parent?->product_name ?? $this->product_name;
    }

    /**
     * Unified product code for UI: always show master (parent) product code when mapped.
     */
    public function getResolvedCodeAttribute(): ?string
    {
        return $this->parent?->product_code ?? $this->product_code;
    }

    /**
     * Optional helper alias for service/controller layers.
     */
    public function resolveProduct(): Product
    {
        return $this->parent ?? $this;
    }

    public function shop(){
        return $this->belongsTo(Shop::class, 'shop_id');
    }

    public function scopeFilter($query, array $filters)
    {
        $query->when($filters['search'] ?? false, function ($query, $search) {
            return $query->where('product_name', 'like', '%' . $search . '%');
        });
    }
}
