<?php

namespace App\Models;

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
        'unitcost',
        'item_discount',
        'total',
    ];

    protected $guarded = [
        'id',
    ];
    protected $with = ['product'];

    public function product()
    {
        return $this->belongsTo(Product::class, 'product_id', 'id');
    }
}
