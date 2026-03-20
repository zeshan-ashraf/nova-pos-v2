<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Kyslik\ColumnSortable\Sortable;

class Purchase extends Model
{
    use HasFactory, Sortable, SoftDeletes;

    protected static function booted(): void
    {
        static::deleting(function (Purchase $purchase) {
            if ($purchase->is_system_generated) {
                AccountTransaction::query()
                    ->where('source_type', AccountTransaction::SOURCE_PURCHASE)
                    ->where('source_id', $purchase->id)
                    ->delete();
                AccountTransaction::query()
                    ->where('source_type', AccountTransaction::SOURCE_PURCHASE_PAYMENT)
                    ->where('source_id', $purchase->id)
                    ->delete();
            }
        });
    }

    protected $fillable = [
        'supplier_id',
        'shop_id',
        'source_sale_id',
        'is_system_generated',
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

    protected $casts = [
        'is_system_generated' => 'boolean',
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

    public function order()
    {
        return $this->belongsTo(Order::class, 'source_sale_id', 'id');
    }
}
