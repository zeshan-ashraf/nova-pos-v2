<?php

namespace App\Services\Ledger;

use App\Models\AccountTransaction;
use App\Models\Order;
use Illuminate\Support\Carbon;

/**
 * Double-entry ledger for sales (invoice + payments).
 * Rule: Sale (revenue) = credit; Customer (AR) = debit (owes more), credit (owes less).
 * - Invoice: sale credit (total), customer debit (total). Payments add cash/bank debit + customer credit.
 */
class SaleLedgerService
{
    /**
     * Record double-entry for invoice creation: (1) sale credit (revenue), (2) customer debit (AR increase).
     * Call once per order inside the same DB transaction as Order::create.
     */
    public function recordInvoiceCustomerDebit(Order $order): ?AccountTransaction
    {
        if (!$order->customer_id || !$order->shop_id) {
            return null;
        }

        $amount = (float) $order->total;
        if ($amount <= 0) {
            return null;
        }

        $existingCustomer = AccountTransaction::query()
            ->where('account_type', AccountTransaction::ACCOUNT_TYPE_CUSTOMER)
            ->where('account_ref_id', $order->customer_id)
            ->where('source_type', AccountTransaction::SOURCE_SALE)
            ->where('source_id', $order->id)
            ->first();
        if ($existingCustomer) {
            return $existingCustomer;
        }

        $transactionDate = $order->order_date
            ? Carbon::parse($order->order_date)->toDateString()
            : now()->toDateString();

        $description = 'Invoice ' . ($order->invoice_no ?? (string) $order->id);

        // Row 1: Sale CREDIT — revenue increase (double-entry: offsets customer debit)
        $existingSale = AccountTransaction::query()
            ->where('account_type', AccountTransaction::ACCOUNT_TYPE_SALE)
            ->where('source_type', AccountTransaction::SOURCE_SALE)
            ->where('source_id', $order->id)
            ->where('shop_id', $order->shop_id)
            ->first();
        if (!$existingSale) {
            AccountTransaction::create([
                'shop_id' => $order->shop_id,
                'account_type' => AccountTransaction::ACCOUNT_TYPE_SALE,
                'account_ref_id' => null,
                'direction' => AccountTransaction::DIRECTION_CREDIT,
                'amount' => $amount,
                'source_type' => AccountTransaction::SOURCE_SALE,
                'source_id' => $order->id,
                'description' => $description,
                'transaction_date' => $transactionDate,
            ]);
        }

        // Row 2: Customer DEBIT — receivable increase (customer owes us)
        return AccountTransaction::create([
            'shop_id' => $order->shop_id,
            'account_type' => AccountTransaction::ACCOUNT_TYPE_CUSTOMER,
            'account_ref_id' => $order->customer_id,
            'direction' => AccountTransaction::DIRECTION_DEBIT,
            'amount' => $amount,
            'source_type' => AccountTransaction::SOURCE_SALE,
            'source_id' => $order->id,
            'description' => $description,
            'transaction_date' => $transactionDate,
        ]);
    }

    /**
     * Record customer credit when a payment is received (AR decrease).
     * Call alongside SalePaymentLedgerService::createFromPaymentLog (bank/cash debit).
     * Duplicate-safe when paymentLogId is passed: one customer credit per payment log.
     */
    public function recordPaymentCustomerCredit(Order $order, float $amountPaid, ?int $paymentLogId = null): ?AccountTransaction
    {
        if ($amountPaid <= 0 || !$order->customer_id || !$order->shop_id) {
            return null;
        }

        if ($paymentLogId !== null) {
            $existing = AccountTransaction::query()
                ->where('account_type', AccountTransaction::ACCOUNT_TYPE_CUSTOMER)
                ->where('account_ref_id', $order->customer_id)
                ->where('source_type', AccountTransaction::SOURCE_SALE)
                ->where('source_id', $paymentLogId)
                ->first();
            if ($existing) {
                return $existing;
            }
        }

        $transactionDate = $order->order_date
            ? Carbon::parse($order->order_date)->toDateString()
            : now()->toDateString();

        return AccountTransaction::create([
            'shop_id' => $order->shop_id,
            'account_type' => AccountTransaction::ACCOUNT_TYPE_CUSTOMER,
            'account_ref_id' => $order->customer_id,
            'direction' => AccountTransaction::DIRECTION_CREDIT,
            'amount' => $amountPaid,
            'source_type' => AccountTransaction::SOURCE_SALE,
            'source_id' => $paymentLogId ?? $order->id,
            'description' => 'Payment – Invoice ' . ($order->invoice_no ?? (string) $order->id),
            'transaction_date' => $transactionDate,
        ]);
    }
}
