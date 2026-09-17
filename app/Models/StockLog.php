<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class StockLog extends Model
{
    use SoftDeletes;
    protected $casts = [
        'adjustment_date' => 'datetime',
        'qty' => 'decimal:3',
        'stock_qty' => 'decimal:3',
        'price' => 'decimal:2',
        'cost_per_unit' => 'decimal:4',
    ];

    protected $attributes = [
        'unit' => Product::UNIT_PIECE,
    ];

    protected $fillable = [
        'shop_id',
        'product_id',
        'supplier_id',
        'qty',            // positive quantity; direction controls in/out (schema is DECIMAL(12,3))
        'unit',           // snapshot of product unit at movement time
        'stock_qty',      // legacy signed column (optional)
        'direction',      // 'in' | 'out'
        'source_type',    // opening, purchase, sale, purchase_return, sale_return, adjustment, loss
        'source_id',      // e.g. order_id, purchase_id (nullable for opening/adjustment)
        'price',
        'cost_per_unit',  // cost at time of sale (for sales); nullable for other source types
        'reason',
        'adjustment_date',
    ];

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
    }
}

