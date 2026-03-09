<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Payable extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'shop_id',
        'name',
        'phone',
        'notes',
    ];

    public function shop()
    {
        return $this->belongsTo(Shop::class, 'shop_id');
    }

    public function payableTransactions()
    {
        return $this->hasMany(PayableTransaction::class);
    }

    /**
     * Balance = sum(borrow) - sum(repayment). Calculated dynamically from transactions.
     */
    public function getBalanceAttribute(): float
    {
        $borrow = (float) $this->payableTransactions()
            ->where('type', 'borrow')
            ->sum('amount');
        $repayment = (float) $this->payableTransactions()
            ->where('type', 'repayment')
            ->sum('amount');

        return round($borrow - $repayment, 2);
    }
}
