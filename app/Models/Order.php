<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Kyslik\ColumnSortable\Sortable;

class Order extends Model
{
    use HasFactory, Sortable, SoftDeletes;

    protected $fillable = [
        'customer_id',
        'shop_id',
        'order_date',
        'order_status',
        'total_products',
        'sub_total',
        'invoice_discount',
        'vat',
        'invoice_no',
        'total',
        'payment_status',
        'pay',
        'due',
        'comment',
        'edited_from_order_id',
    ];

    public $sortable = [
        'customer_id',
        'customer.name',
        'order_date',
        'pay',
        'due',
        'total',
    ];

    protected $guarded = [
        'id',
    ];

    public function customer()
    {
        return $this->belongsTo(Customer::class, 'customer_id', 'id');
    }

    public function shop()
    {
        return $this->belongsTo(Shop::class, 'shop_id');
    }

    public function orderDetails()
    {
        return $this->hasMany(OrderDetails::class, 'order_id', 'id');
    }

    public function paymentLogs()
    {
        return $this->hasMany(PaymentLog::class, 'order_id', 'id');
    }

    public function saleReturns()
    {
        return $this->hasMany(SaleReturn::class, 'order_id', 'id');
    }
}
