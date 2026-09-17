<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Kyslik\ColumnSortable\Sortable;
use App\Support\InterShopTransferStatus;

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
        'landed_cost_status',
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
        'approved_at',
        'approved_by',
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
        'approved_at' => 'datetime',
        'sub_total' => 'decimal:2',
        'invoice_discount' => 'decimal:2',
        'vat' => 'decimal:2',
        'total' => 'decimal:2',
        'pay' => 'decimal:2',
        'due' => 'decimal:2',
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

    /**
     * Purchase expenses (activities) used for landed-cost allocation.
     */
    public function activities()
    {
        return $this->hasMany(Activity::class, 'purchase_id', 'id');
    }

    public function paymentLogs()
    {
        return $this->hasMany(PurchasePaymentLog::class, 'purchase_id', 'id');
    }

    public function order()
    {
        return $this->belongsTo(Order::class, 'source_sale_id', 'id');
    }

    public function purchaseStatusDisplayLabel(): string
    {
        return InterShopTransferStatus::uiPurchaseStatusLabel($this->purchase_status);
    }
}
