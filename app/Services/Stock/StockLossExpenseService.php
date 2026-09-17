<?php

namespace App\Services\Stock;

use App\Models\Activity;
use App\Models\Product;
use App\Models\StockLog;

/**
 * Creates system (non-cash) expenses when stock is reduced due to loss, theft, or expiration.
 * These expenses affect Profit & Loss but NOT Cash Flow (no account_transactions).
 * Append-only; duplicate protection by linked_stock_log_id.
 */
class StockLossExpenseService
{
    public const CATEGORY_INVENTORY_LOSS = 'Inventory Loss';

    /** source_type values that trigger a system expense (direction must be 'out'). */
    public const LOSS_SOURCE_TYPES = ['loss', 'expired', 'theft'];

    /**
     * Create a system expense for a stock loss log (direction=out, source_type in loss/expired/theft).
     * Duplicate protection: if an expense already exists for this stock_log id, skip and return existing.
     * NON-CASH: do NOT create account_transactions.
     *
     * @param StockLog $stockLog Must have direction='out', source_type in (loss, expired, theft), shop_id, product_id, qty
     * @return Activity The created or existing system expense
     */
    public function recordExpenseFromStockLog(StockLog $stockLog): Activity
    {
        if ($stockLog->direction !== 'out' || !in_array($stockLog->source_type, self::LOSS_SOURCE_TYPES, true)) {
            throw new \InvalidArgumentException(
                'Stock loss expense requires direction=out and source_type in (loss, expired, theft).'
            );
        }

        // Duplicate protection: do not create a second expense for the same stock_log
        $existing = Activity::query()
            ->where('linked_stock_log_id', $stockLog->id)
            ->first();

        if ($existing) {
            return $existing;
        }

        $shopId = $stockLog->shop_id;
        if (!$shopId) {
            throw new \InvalidArgumentException('Stock log must have shop_id for system expense.');
        }

        $product = Product::find($stockLog->product_id);
        $productName = $product ? $product->product_name : 'Product #' . $stockLog->product_id;
        $costPerUnit = (float) ($stockLog->price ?? $product->buying_price ?? 0);
        $qty = (string) ($stockLog->qty ?? '0');
        $lossAmount = round((float) bcmul($qty, number_format($costPerUnit, 4, '.', ''), 4), 2);

        $reason = $stockLog->reason ?? $stockLog->source_type;
        $notes = sprintf('Stock loss: %s | Product: %s', $reason, $productName);

        $logCreatedAt = $stockLog->created_at ?? now();
        $expenseDate = $logCreatedAt instanceof \DateTimeInterface
            ? $logCreatedAt->format('Y-m-d')
            : \Carbon\Carbon::parse($logCreatedAt)->format('Y-m-d');

        return Activity::create([
            'shop_id' => $shopId,
            'title' => self::CATEGORY_INVENTORY_LOSS,
            'description' => $notes,
            'date' => $expenseDate,
            'activity_cost' => $lossAmount,
            'payment_method' => 'system',
            'shop_bank_id' => null,
            'category' => self::CATEGORY_INVENTORY_LOSS,
            'is_system' => true,
            'linked_stock_log_id' => $stockLog->id,
            'reversal_of_expense_id' => null,
            'customer_id' => null,
            'images' => json_encode([]),
        ]);
    }

    /**
     * Create a reversal expense when a stock loss is reversed (stock added back).
     * Amount = negative original amount; DO NOT delete the original expense.
     * NON-CASH: do NOT create account_transactions.
     *
     * @param Activity $originalExpense System expense linked to the stock loss being reversed
     * @return Activity The reversal expense row
     */
    public function recordReversal(Activity $originalExpense): Activity
    {
        if (!($originalExpense->is_system ?? false)) {
            throw new \InvalidArgumentException('Reversal only allowed for system expenses.');
        }

        $reversalAmount = - (float) $originalExpense->activity_cost;

        return Activity::create([
            'shop_id' => $originalExpense->shop_id,
            'title' => self::CATEGORY_INVENTORY_LOSS,
            'description' => 'Reversal of stock loss',
            'date' => now()->format('Y-m-d'),
            'activity_cost' => $reversalAmount,
            'payment_method' => 'system',
            'shop_bank_id' => null,
            'category' => self::CATEGORY_INVENTORY_LOSS,
            'is_system' => true,
            'linked_stock_log_id' => null,
            'reversal_of_expense_id' => $originalExpense->id,
            'customer_id' => null,
            'images' => json_encode([]),
        ]);
    }
}
