<?php

namespace App\Services;

use App\Models\AccountTransaction;
use App\Models\PaymentLog;
use App\Services\Ledger\SaleLedgerService;

class SalePaymentLedgerService
{
    public function __construct(
        private SaleLedgerService $saleLedgerService
    ) {}

/**
 * Create ledger entries from a sale payment_log (double-entry): (1) bank/cash debit (money in), (2) customer credit (AR decrease).
 * One payment_log = one bank/cash entry + one customer entry (when order has customer_id).
 * Rule: Receiving payment → increase asset (debit cash/bank), decrease receivable (credit customer).
 *
 * @param int $paymentLogId
 * @return AccountTransaction The bank/cash entry
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

        // Cash/Bank DEBIT — money received (asset increase)
        $bankOrCashEntry = AccountTransaction::create([
            'shop_id' => $order->shop_id,
            'account_type' => $accountType,
            'account_ref_id' => $accountRefId,
            'direction' => AccountTransaction::DIRECTION_DEBIT,
            'amount' => $paymentLog->amount_paid,
            'source_type' => AccountTransaction::SOURCE_SALE,
            'source_id' => $paymentLog->id,
            'description' => 'Sale payment – Invoice ' . ($order->invoice_no ?? $order->id),
            'transaction_date' => $transactionDate,
        ]);

        $this->saleLedgerService->recordPaymentCustomerCredit($order, (float) $paymentLog->amount_paid, (int) $paymentLog->id);

        return $bankOrCashEntry;
    }
}
