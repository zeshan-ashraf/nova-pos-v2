<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ShopNotification extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'shop_id',
        'shop_purchase_request_id',
        'type',
        'data',
        'is_read',
        'created_at',
    ];

    protected $casts = [
        'data' => 'array',
        'is_read' => 'boolean',
        'created_at' => 'datetime',
    ];

    public function shop()
    {
        return $this->belongsTo(Shop::class, 'shop_id');
    }

    public function shopPurchaseRequest()
    {
        return $this->belongsTo(ShopPurchaseRequest::class, 'shop_purchase_request_id');
    }
}
