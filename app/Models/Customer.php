<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

use Kyslik\ColumnSortable\Sortable;

class Customer extends Model
{
    use HasFactory, Sortable;

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
        'credit_limit',
        'credit_amount',
        'credit_days',
    ];
    public $sortable = [
        // 'name', // Removed from UI - may be needed in future
        'email',
        'phone',
        'shopname',
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
}
