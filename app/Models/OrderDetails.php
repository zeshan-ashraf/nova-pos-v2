<?php

namespace App\Models;

use App\Support\ProductUnitValidator;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class OrderDetails extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'order_id',
        'product_id',
        'quantity',
        'unit',
        'unitcost',
        'cost_per_unit',
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
        'cost_per_unit' => 'decimal:4',
        'item_discount' => 'decimal:2',
        'total' => 'decimal:2',
    ];

    protected $with = ['product'];

    public function product()
    {
        return $this->belongsTo(Product::class, 'product_id', 'id');
    }

    public function order()
    {
        return $this->belongsTo(Order::class, 'order_id', 'id');
    }

    public function returnDetails()
    {
        return $this->hasMany(SaleReturnDetail::class, 'order_detail_id', 'id');
    }

    public function snapshotUnit(): string
    {
        return in_array($this->unit, Product::allowedUnits(), true)
            ? $this->unit
            : Product::UNIT_PIECE;
    }

    public function formattedSaleQuantity(): string
    {
        $formatted = app(ProductUnitValidator::class)->formatQuantity($this->quantity ?? 0);
        if ($this->snapshotUnit() === Product::UNIT_PIECE) {
            $trimmed = preg_replace('/\.0+$/', '', $formatted);

            return ($trimmed === null || $trimmed === '') ? '0' : $trimmed;
        }

        return $formatted;
    }

    public function saleUnitLabel(): string
    {
        return $this->snapshotUnit() === Product::UNIT_KG ? 'kg' : 'piece';
    }

    public function saleQtyUnitLabel(): string
    {
        return $this->snapshotUnit() === Product::UNIT_KG ? 'kg' : 'pieces';
    }

    public function quantityWithUnit(): string
    {
        return $this->formattedSaleQuantity().' '.$this->saleQtyUnitLabel();
    }

    public function unitPriceWithUnit(): string
    {
        return number_format((float) $this->unitcost, 2).' / '.$this->saleUnitLabel();
    }
}
