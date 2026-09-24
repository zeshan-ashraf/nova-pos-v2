<?php

namespace App\Models;

use App\Support\ProductUnitValidator;
use App\Traits\BelongsToShop;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Kyslik\ColumnSortable\Sortable;

class Product extends Model
{
    use BelongsToShop, HasFactory, Sortable;

    public const UNIT_PIECE = 'piece';

    public const UNIT_KG = 'kg';

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
        'reserved_stock',
        'low_stock_warning',
        'buying_date',
        'expire_date',
        'buying_price',
        'selling_price',
        'status',
        'unit',
    ];

    public $sortable = [
        'product_name',
        'product_code',
        'selling_price',
    ];

    protected $guarded = [
        'id',
    ];

    protected $attributes = [
        'unit' => self::UNIT_PIECE,
    ];

    /**
     * buying_price: moving weighted average cost (updated on purchase only).
     * Quantities use decimal:3 (DECIMAL(12,3)). Money uses decimal:2 / decimal:4.
     */
    protected $casts = [
        'buying_price' => 'decimal:4',
        'selling_price' => 'decimal:2',
        'product_store' => 'decimal:3',
        'reserved_stock' => 'decimal:3',
        'low_stock_warning' => 'decimal:3',
    ];

    protected $with = [
        'shop'
    ];

    protected static function booted(): void
    {
        static::saving(function (Product $product) {
            if ($product->unit === null || $product->unit === '') {
                $product->unit = self::UNIT_PIECE;
            }

            if (! in_array($product->unit, self::allowedUnits(), true)) {
                throw new \InvalidArgumentException(
                    'Invalid product unit ['.$product->unit.']. Allowed: '.implode(', ', self::allowedUnits()).'.'
                );
            }
        });
    }

    /**
     * @return list<string>
     */
    public static function allowedUnits(): array
    {
        return [self::UNIT_PIECE, self::UNIT_KG];
    }

    public function isPiece(): bool
    {
        return $this->unit === self::UNIT_PIECE;
    }

    public function isKg(): bool
    {
        return $this->unit === self::UNIT_KG;
    }

    /**
     * Display quantity for the product's unit: piece as a whole number, kg at 3 decimal places.
     */
    public function formattedQuantity(mixed $quantity = null): string
    {
        $qty = $quantity ?? $this->product_store ?? 0;
        if ($qty === null || $qty === '') {
            $qty = 0;
        }

        return self::formatQuantityForUnit($qty, $this->unit);
    }

    /**
     * Piece stays a whole number. Kg stays three decimal places. Used by reports.
     */
    public static function formatQuantityForUnit(mixed $quantity, ?string $unit): string
    {
        $formatted = app(ProductUnitValidator::class)->formatQuantity($quantity ?? 0);
        $resolved = in_array($unit, self::allowedUnits(), true) ? $unit : self::UNIT_PIECE;

        if ($resolved === self::UNIT_PIECE) {
            $trimmed = preg_replace('/\.0+$/', '', $formatted);

            return ($trimmed === null || $trimmed === '') ? '0' : $trimmed;
        }

        return $formatted;
    }

    public static function quantityUnitLabel(?string $unit): string
    {
        return $unit === self::UNIT_KG ? 'kg' : 'pieces';
    }

    public static function displayQuantityWithUnit(mixed $quantity, ?string $unit): string
    {
        return self::formatQuantityForUnit($quantity, $unit).' '.self::quantityUnitLabel($unit);
    }

    /**
     * Stock value = quantity × unit price. Quantity stays decimal; money is rounded to 2 places.
     */
    public static function moneyFromQuantity(mixed $quantity, mixed $unitPrice): string
    {
        $qty = app(ProductUnitValidator::class)->formatQuantity($quantity ?? '0');
        $price = number_format(is_numeric($unitPrice) ? (float) $unitPrice : 0, 4, '.', '');
        $raw = bcmul($qty, $price, 6);
        $negative = str_starts_with($raw, '-');
        $absolute = ltrim($raw, '+-');
        $rounded = bcadd($absolute, '0.005', 2);

        return ($negative ? '-' : '').$rounded;
    }

    public function unitLabel(): string
    {
        return $this->isKg() ? 'kg' : 'piece';
    }

    public function stockUnitLabel(): string
    {
        return $this->isKg() ? 'kg' : 'pieces';
    }

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
            // Search by both product name and product code.
            $search = trim((string) $search);
            return $query->where(function ($q) use ($search) {
                $q->where('product_name', 'like', '%' . $search . '%')
                    ->orWhere('product_code', 'like', '%' . $search . '%');
            });
        });
    }
}
