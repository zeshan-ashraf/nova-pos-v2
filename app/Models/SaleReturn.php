<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Kyslik\ColumnSortable\Sortable;

class SaleReturn extends Model
{
    use HasFactory, Sortable, SoftDeletes;

    protected $fillable = [
        'order_id',
        'customer_id',
        'shop_id',
        'return_date',
        'return_status',
        'return_no',
        'total_products',
        'sub_total',
        'invoice_discount',
        'vat',
        'total',
        'reason',
    ];

    public $sortable = [
        'return_date',
        'return_no',
        'total',
    ];

    protected $guarded = [
        'id',
    ];

    public function order()
    {
        return $this->belongsTo(Order::class, 'order_id', 'id');
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class, 'customer_id', 'id');
    }

    public function shop()
    {
        return $this->belongsTo(Shop::class, 'shop_id');
    }

    public function returnDetails()
    {
        return $this->hasMany(SaleReturnDetail::class, 'return_id', 'id');
    }
}
