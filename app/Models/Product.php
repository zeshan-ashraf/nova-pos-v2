<?php

namespace App\Models;

use Kyslik\ColumnSortable\Sortable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Collection;

class Product extends Model
{
    use HasFactory, Sortable;

    protected $fillable = [
        'product_name',
        'category_id',
        'supplier_id',
        'shop_id',
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
     */
    public static function eagerLoadSameShopCategory(Collection $products): void
    {
        $pairs = $products
            ->filter(fn (Product $p) => $p->category_id !== null)
            ->map(fn (Product $p) => ['id' => $p->category_id, 'shop_id' => $p->shop_id])
            ->unique(fn ($p) => $p['id'] . '-' . ($p['shop_id'] ?? 'null'))
            ->values();

        if ($pairs->isEmpty()) {
            $products->each->setRelation('sameShopCategory', null);
            return;
        }

        $categories = Category::query()
            ->where(function ($query) use ($pairs) {
                foreach ($pairs as $pair) {
                    $query->orWhere(function ($q) use ($pair) {
                        $q->where('id', $pair['id']);
                        $pair['shop_id'] === null
                            ? $q->whereNull('shop_id')
                            : $q->where('shop_id', $pair['shop_id']);
                    });
                }
            })
            ->get()
            ->keyBy(fn (Category $c) => $c->id . '-' . ($c->shop_id ?? 'null'));

        foreach ($products as $product) {
            $key = $product->category_id . '-' . ($product->shop_id ?? 'null');
            $product->setRelation('sameShopCategory', $categories->get($key));
        }
    }

    public function supplier(){
        return $this->belongsTo(Supplier::class, 'supplier_id');
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
