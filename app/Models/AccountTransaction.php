<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AccountTransaction extends Model
{
    use HasFactory;

    public const ACCOUNT_TYPE_CASH = 'cash';
    public const ACCOUNT_TYPE_BANK = 'bank';

    public const DIRECTION_DEBIT = 'debit';
    public const DIRECTION_CREDIT = 'credit';

    public const SOURCE_OPENING = 'opening';
    public const SOURCE_SALE = 'sale';
    public const SOURCE_PURCHASE = 'purchase';
    public const SOURCE_EXPENSE = 'expense';
    public const SOURCE_TRANSFER = 'transfer';
    public const SOURCE_ADJUSTMENT = 'adjustment';

    protected $fillable = [
        'shop_id',
        'account_type',
        'account_ref_id',
        'direction',
        'amount',
        'source_type',
        'source_id',
        'description',
        'transaction_date',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'transaction_date' => 'date',
    ];

    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }
}
