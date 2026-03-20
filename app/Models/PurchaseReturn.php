<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class PurchaseReturn extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'purchase_id',
        'shop_id',
        'return_no',
        'return_date',
        'total_products',
        'sub_total',
        'total',
        'status',
        'linked_sale_return_id',
        'approved_by',
        'approved_at',
        'rejected_by',
        'rejected_at',
        'created_by',
    ];

    public function purchase()
    {
        return $this->belongsTo(Purchase::class);
    }

    public function details()
    {
        return $this->hasMany(PurchaseReturnDetail::class);
    }

    public function shop()
    {
        return $this->belongsTo(Shop::class);
    }
}

