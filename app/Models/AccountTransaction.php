<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class AccountTransaction extends Model
{
    use HasFactory, SoftDeletes;

    public const ACCOUNT_TYPE_CASH = 'cash';
    public const ACCOUNT_TYPE_BANK = 'bank';
    public const ACCOUNT_TYPE_CUSTOMER = 'customer';
    public const ACCOUNT_TYPE_SUPPLIER = 'supplier';
    /** Revenue (credit increases). */
    public const ACCOUNT_TYPE_SALE = 'sale';
    /** Inventory / cost (debit increases). */
    public const ACCOUNT_TYPE_PURCHASE = 'purchase';
    /** Expense (debit increases). */
    public const ACCOUNT_TYPE_EXPENSE = 'expense';

    public const DIRECTION_DEBIT = 'debit';
    public const DIRECTION_CREDIT = 'credit';

    public const SOURCE_OPENING = 'opening';
    public const SOURCE_SALE = 'sale';
    public const SOURCE_PURCHASE = 'purchase';
    public const SOURCE_PURCHASE_PAYMENT = 'purchase_payment';
    public const SOURCE_CUSTOMER_PAYMENT = 'customer_payment';
    public const SOURCE_SUPPLIER_PAYMENT = 'supplier_payment';
    public const SOURCE_EXPENSE = 'expense';
    public const SOURCE_TRANSFER = 'transfer';
    public const SOURCE_ADJUSTMENT = 'adjustment';
    /** Customer opening balance (one active row per customer). */
    public const SOURCE_CUSTOMER_OPENING = 'customer_opening';
    /** Supplier opening balance (one active row per supplier). */
    public const SOURCE_SUPPLIER_OPENING = 'supplier_opening';

    protected $fillable = [
        'shop_id',
        'account_type',
        'account_ref_id',
        'direction',
        'amount',
        'source_type',
        'source_id',
        'receipt_no',
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

    /** When account_type is customer, account_ref_id is the customer id. */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'account_ref_id');
    }
}
