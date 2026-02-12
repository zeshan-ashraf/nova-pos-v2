<?php

namespace App\Models;

use App\Traits\BelongsToShop;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Kyslik\ColumnSortable\Sortable;

class Supplier extends Model
{
    use BelongsToShop, HasFactory, Sortable;

    protected $fillable = [
        'name',
        'email',
        'phone',
        'address',
        'shopname',
        'photo',
        'type',
        'account_holder',
        'account_number',
        'bank_name',
        'bank_branch',
        'city',
        'shop_id',
        'mother_shop_id',
        'credit_limit',
        'credit_amount',
        'credit_days',
    ];
    public $sortable = [
        // 'name', // Removed from UI - may be needed in future
        'email',
        'phone',
        'shopname',
        'type',
        // 'city', // Removed from UI - may be needed in future
    ];

    protected $guarded = [
        'id',
    ];

    public function scopeFilter($query, array $filters)
    {
        $query->when($filters['search'] ?? false, function ($query, $search) {
            return $query->where('shopname', 'like', '%' . $search . '%');
        });
    }

    public function shop()
    {
        return $this->belongsTo(Shop::class, 'shop_id');
    }

    public function motherShop()
    {
        return $this->belongsTo(Shop::class, 'mother_shop_id');
    }

    public function purchases()
    {
        return $this->hasMany(Purchase::class, 'supplier_id', 'id');
    }
}
