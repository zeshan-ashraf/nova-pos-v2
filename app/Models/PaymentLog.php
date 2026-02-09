<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class PaymentLog extends Model
{
    use HasFactory, SoftDeletes;
    protected $fillable = ['order_id', 'amount_paid', 'type', 'payment_method', 'shop_bank_id'];

    public function order()
    {
        return $this->belongsTo(Order::class);
    }
}
