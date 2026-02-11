<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Kyslik\ColumnSortable\Sortable;

class Expense extends Model
{
    use HasFactory, SoftDeletes, Sortable;

    protected $fillable = [
        'expense_title',
        'shop_id',
    ];

    public $sortable = [
        'expense_title',
        'id',
    ];

    public function shop()
    {
        return $this->belongsTo(Shop::class, 'shop_id');
    }

    public function activities()
    {
        return $this->hasMany(Activity::class, 'expense_id');
    }
}
