<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PaymentLog extends Model
{
    use HasFactory;
    protected $fillable = ['order_id', 'amount_paid'];

    public function order()
    {
        return $this->belongsTo(Order::class);
    }
}
