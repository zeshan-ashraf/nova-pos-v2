<?php

namespace App\Models;

use App\Support\ProductUnitValidator;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class PurchaseDetail extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'purchase_id',
        'product_id',
        'quantity',
        'unit',
        'unitcost',
        'allocated_expense',
        'landed_unit_cost',
        'landed_total',
        'item_discount',
        'total',
    ];

    protected $guarded = [
        'id',
    ];

    protected $attributes = [
        'unit' => Product::UNIT_PIECE,
    ];

    protected $casts = [
        'quantity' => 'decimal:3',
        'unitcost' => 'decimal:2',
        'allocated_expense' => 'decimal:4',
        'landed_unit_cost' => 'decimal:4',
        'landed_total' => 'decimal:4',
        'item_discount' => 'decimal:2',
        'total' => 'decimal:2',
    ];

    protected $with = ['product'];

    public function purchase()
    {
        return $this->belongsTo(Purchase::class, 'purchase_id', 'id');
    }

    public function product()
    {
        return $this->belongsTo(Product::class, 'product_id', 'id');
    }

    public function snapshotUnit(): string
    {
        return in_array($this->unit, Product::allowedUnits(), true)
            ? $this->unit
            : Product::UNIT_PIECE;
    }

    public function formattedPurchaseQuantity(): string
    {
        $formatted = app(ProductUnitValidator::class)->formatQuantity($this->quantity ?? 0);
        if ($this->snapshotUnit() === Product::UNIT_PIECE) {
            $trimmed = preg_replace('/\.0+$/', '', $formatted);

            return ($trimmed === null || $trimmed === '') ? '0' : $trimmed;
        }

        return $formatted;
    }

    public function purchaseUnitLabel(): string
    {
        return $this->snapshotUnit() === Product::UNIT_KG ? 'kg' : 'piece';
    }

    public function purchaseQtyUnitLabel(): string
    {
        return $this->snapshotUnit() === Product::UNIT_KG ? 'kg' : 'pieces';
    }

    public function quantityWithUnit(): string
    {
        return $this->formattedPurchaseQuantity().' '.$this->purchaseQtyUnitLabel();
    }

    public function unitPriceWithUnit(): string
    {
        return number_format((float) $this->unitcost, 2).' / '.$this->purchaseUnitLabel();
    }
}
