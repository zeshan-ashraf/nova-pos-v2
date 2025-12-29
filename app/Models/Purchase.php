<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Kyslik\ColumnSortable\Sortable;

class Purchase extends Model
{
    use HasFactory, Sortable, SoftDeletes;

    protected $fillable = [
        'supplier_id',
        'shop_id',
        'purchase_date',
        'purchase_status',
        'total_products',
        'sub_total',
        'invoice_discount',
        'vat',
        'purchase_no',
        'total',
        'payment_status',
        'pay',
        'due',
        'comment',
    ];

    public $sortable = [
        'supplier_id',
        'purchase_date',
        'pay',
        'due',
        'total',
    ];

    protected $guarded = [
        'id',
    ];

    public function supplier()
    {
        return $this->belongsTo(Supplier::class, 'supplier_id', 'id');
    }

    public function shop()
    {
        return $this->belongsTo(Shop::class, 'shop_id');
    }

    public function purchaseDetails()
    {
        return $this->hasMany(PurchaseDetail::class, 'purchase_id', 'id');
    }

    public function paymentLogs()
    {
        return $this->hasMany(PurchasePaymentLog::class, 'purchase_id', 'id');
    }
}
