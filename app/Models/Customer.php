<?php

namespace App\Models;

use App\Traits\BelongsToShop;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Kyslik\ColumnSortable\Sortable;

class Customer extends Model
{
    use BelongsToShop, HasFactory, SoftDeletes, Sortable;

    protected $fillable = [
        'name',
        'email',
        'phone',
        'address',
        'shopname',
        'photo',
        'account_holder',
        'account_number',
        'bank_name',
        'bank_branch',
        'city',
        'shop_id',
        'child_shop_id',
        'credit_limit',
        'credit_amount',
        'credit_days',
        'is_system',
        'is_walkin',
    ];
    public $sortable = [
        'name',
        'email',
        'phone',
        'shopname',
        // 'city', // Removed from UI - may be needed in future
    ];

    protected $guarded = [
        'id',
    ];

    protected $casts = [
        'is_system' => 'boolean',
        'is_walkin' => 'boolean',
    ];

    public function scopeFilter($query, array $filters)
    {
        $query->when($filters['search'] ?? false, function ($query, $search) {
            return $query->where(function ($q) use ($search) {
                $q->where('name', 'like', '%' . $search . '%')
                    ->orWhere('shopname', 'like', '%' . $search . '%');
            });
        });
    }

    public function shop()
    {
        return $this->belongsTo(Shop::class, 'shop_id');
    }

    public function childShop()
    {
        return $this->belongsTo(Shop::class, 'child_shop_id');
    }
}
