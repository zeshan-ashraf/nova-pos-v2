<?php

namespace App\Services;

use App\Models\AccountTransaction;
use App\Models\PaymentLog;

class SalePaymentLedgerService
{
    /**
     * Create a single ledger entry from a sale payment_log.
     * One payment_log = at most one ledger entry.
     *
     * @param int $paymentLogId
     * @return AccountTransaction
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException
     * @throws \InvalidArgumentException
     */
    public function createFromPaymentLog(int $paymentLogId): AccountTransaction
    {
        $paymentLog = PaymentLog::with('order')->findOrFail($paymentLogId);

        if ($paymentLog->amount_paid <= 0) {
            throw new \InvalidArgumentException('Sale payment ledger requires positive amount_paid.');
        }

        $order = $paymentLog->order;
        if (!$order || !$order->shop_id) {
            throw new \InvalidArgumentException('Payment log order must have a shop_id.');
        }

        $isBank = in_array($paymentLog->payment_method, ['bank', 'cheque'], true);
        $accountType = $isBank ? AccountTransaction::ACCOUNT_TYPE_BANK : AccountTransaction::ACCOUNT_TYPE_CASH;
        $accountRefId = $isBank && $paymentLog->shop_bank_id ? $paymentLog->shop_bank_id : null;

        $transactionDate = $order->order_date
            ? \Illuminate\Support\Carbon::parse($order->order_date)->toDateString()
            : now()->toDateString();

        return AccountTransaction::create([
            'shop_id' => $order->shop_id,
            'account_type' => $accountType,
            'account_ref_id' => $accountRefId,
            'direction' => AccountTransaction::DIRECTION_CREDIT,
            'amount' => $paymentLog->amount_paid,
            'source_type' => AccountTransaction::SOURCE_SALE,
            'source_id' => $paymentLog->id,
            'description' => 'Sale payment – Invoice ' . ($order->invoice_no ?? $order->id),
            'transaction_date' => $transactionDate,
        ]);
    }
}
