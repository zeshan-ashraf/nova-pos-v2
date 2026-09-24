<?php

namespace App\Models;

use App\Support\ProductUnitValidator;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class PurchaseReturnDetail extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'purchase_return_id',
        'product_id',
        'quantity',
        'unit',
        'price',
        'total',
    ];

    protected $attributes = [
        'unit' => Product::UNIT_PIECE,
    ];

    protected $casts = [
        'quantity' => 'decimal:3',
        'price' => 'decimal:2',
        'total' => 'decimal:2',
    ];

    public function purchaseReturn()
    {
        return $this->belongsTo(PurchaseReturn::class);
    }

    public function product()
    {
        // Cross-shop transfer returns are reviewed by mother shop users, so
        // this relation must bypass shop global scope to resolve child products.
        return $this->belongsTo(Product::class)->withoutGlobalScope('shop');
    }

    public function snapshotUnit(): string
    {
        return in_array($this->unit, Product::allowedUnits(), true)
            ? $this->unit
            : Product::UNIT_PIECE;
    }

    public function quantityWithUnit(): string
    {
        $formatted = app(ProductUnitValidator::class)->formatQuantity($this->quantity ?? 0);
        if ($this->snapshotUnit() === Product::UNIT_PIECE) {
            $trimmed = preg_replace('/\.0+$/', '', $formatted);

            return (($trimmed === null || $trimmed === '') ? '0' : $trimmed).' pieces';
        }

        return $formatted.' kg';
    }
}

