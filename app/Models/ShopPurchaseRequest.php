<?php

namespace App\Models;

use App\Support\InterShopTransferStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ShopPurchaseRequest extends Model
{
    protected $fillable = [
        'type',
        'mother_shop_sale_id',
        'child_shop_id',
        'mapped_purchase_id',
        'status',
        'payload',
        'approved_at',
        'approved_by',
    ];

    protected $casts = [
        'payload' => 'array',
        'approved_at' => 'datetime',
    ];

    public function motherSale(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'mother_shop_sale_id');
    }

    public function childShop(): BelongsTo
    {
        return $this->belongsTo(Shop::class, 'child_shop_id');
    }

    public function mappedPurchase(): BelongsTo
    {
        return $this->belongsTo(Purchase::class, 'mapped_purchase_id');
    }

    public function isPending(): bool
    {
        return $this->status === InterShopTransferStatus::PENDING;
    }

    public function scopeForChildShop($query, int $childShopId)
    {
        return $query->where('child_shop_id', $childShopId);
    }
}
