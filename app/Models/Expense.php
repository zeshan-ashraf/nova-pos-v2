<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Expense extends Model
{
    use HasFactory;
    // use Sortable;
    protected $table = 'activities';

    protected $fillable = [
        'title',
        'description',
        'date',
        'images',
        'activity_cost',
        'payment_method',
        'shop_bank_id',
        'category',
        'is_system',
        'linked_stock_log_id',
        'reversal_of_expense_id',
        'customer_id',
        'shop_id',
    ];

    protected $casts = [
        'is_system' => 'boolean',
    ];

    public function getImagesAttribute($value)
    {
        return json_decode($value, true);
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function shop()
    {
        return $this->belongsTo(Shop::class, 'shop_id');
    }
}
