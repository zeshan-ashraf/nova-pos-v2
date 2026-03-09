<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class PayableTransaction extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'payable_id',
        'date',
        'type',
        'amount',
        'expected_return_date',
        'notes',
    ];

    protected $casts = [
        'date' => 'date',
        'expected_return_date' => 'date',
        'amount' => 'decimal:2',
    ];

    public function payable()
    {
        return $this->belongsTo(Payable::class);
    }
}
