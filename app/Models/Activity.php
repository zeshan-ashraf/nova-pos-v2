<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Kyslik\ColumnSortable\Sortable;

class Activity extends Model
{
    use HasFactory, SoftDeletes, Sortable;

    protected $table = 'activities';

    public $sortable = [
        'id',
        'description',
        'date',
        'activity_cost',
        'expense.expense_title',
    ];

    protected $fillable = [
        'title',
        'description',
        'date',
        'images',
        'activity_cost',
        'payment_method',
        'shop_bank_id',
        'expense_id',
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

    public function expense()
    {
        return $this->belongsTo(Expense::class, 'expense_id');
    }
}
