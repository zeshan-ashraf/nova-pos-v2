<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class SaleReturnDetail extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'return_id',
        'order_id',
        'order_detail_id',
        'product_id',
        'quantity',
        'unit',
        'unitcost',
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
        'item_discount' => 'decimal:2',
        'total' => 'decimal:2',
    ];

    protected $with = ['product', 'orderDetail'];

    public function saleReturn()
    {
        return $this->belongsTo(SaleReturn::class, 'return_id', 'id');
    }

    public function order()
    {
        return $this->belongsTo(Order::class, 'order_id', 'id');
    }

    public function orderDetail()
    {
        return $this->belongsTo(OrderDetails::class, 'order_detail_id', 'id');
    }

    public function product()
    {
        return $this->belongsTo(Product::class, 'product_id', 'id');
    }
}
