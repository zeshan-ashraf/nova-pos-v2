<?php

namespace App\Services;

use App\Models\AccountTransaction;
use App\Models\Order;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Sale posting service: double-entry ledger for sales (invoice + payments).
 * All entries use source_type = 'sale' and source_id = sale.id (order.id).
 * Never uses payment_logs.id as source_id.
 *
 * OPTION 1 — Registered customer: Customer debit (AR), Sale credit; optional Cash/Bank debit + Customer credit.
 * OPTION 2 — Walk-in customer: Sale credit once; Cash/Bank debit per payment slice.
 */
class SalePostingService
{
    /**
     * Post sale ledger entries for an order (invoice + optional payment).
     * Prefer postOrderPayments() when posting multiple slices on one invoice.
     *
     * @param bool $validateBalance When false, skip debit/credit check (legacy single-slice callers)
     *
     * @throws InvalidArgumentException
     */
    public function postSale(Order $sale, float $paidAmount, string $paymentMethod, ?int $shopBankId = null, bool $validateBalance = true): void
    {
        DB::transaction(function () use ($sale, $paidAmount, $paymentMethod, $shopBankId, $validateBalance) {
            $this->applySalePaymentSlice($sale, $paidAmount, $paymentMethod, $shopBankId);

            if ($validateBalance) {
                $this->validateDebitCreditBalance((int) $sale->id);
            }
        });
    }

    /**
     * Post one or two payment slices atomically. Must be called inside a DB transaction (OrderController).
     *
     * @param  array{amount: float, method: string, bank: int|null}  $payment1
     * @param  array{amount: float, method: string, bank: int|null}|null  $payment2
     *
     * @throws InvalidArgumentException
     */
    public function postOrderPayments(Order $sale, array $payment1, ?array $payment2 = null): void
    {
        $sale->loadMissing('customer');
        $isWalkin = !$sale->customer || (bool) $sale->customer->is_walkin;

        $pay1 = (float) ($payment1['amount'] ?? 0);
        $method1 = (string) ($payment1['method'] ?? '');
        $bank1 = $payment1['bank'] ?? null;

        $pay2 = (float) ($payment2['amount'] ?? 0);
        $method2 = $payment2 !== null ? (string) ($payment2['method'] ?? '') : '';
        $bank2 = $payment2['bank'] ?? null;
        $hasSecondSlice = $payment2 !== null && $pay2 > 0 && $method2 !== '';

        if ($pay1 > 0) {
            $this->applySalePaymentSlice($sale, $pay1, $method1, $bank1);
        } elseif (!$isWalkin) {
            $this->applySalePaymentSlice($sale, 0, 'credit', null);
        }

        if ($hasSecondSlice) {
            $this->applySalePaymentSlice($sale, $pay2, $method2, $bank2);
        }

        $this->validateDebitCreditBalance((int) $sale->id);
    }

    /**
     * Apply one payment slice to the ledger (no transaction wrapper — caller owns the transaction).
     *
     * @throws InvalidArgumentException
     */
    private function applySalePaymentSlice(Order $sale, float $paidAmount, string $paymentMethod, ?int $shopBankId = null): void
    {
        $sale->loadMissing('customer');
        $customer = $sale->customer;
        $totalAmount = (float) $sale->total;
        $shopId = (int) $sale->shop_id;
        $isWalkin = !$customer || (bool) $customer->is_walkin;

        if ($isWalkin && !in_array($paymentMethod, ['cash', 'bank'], true)) {
            throw new InvalidArgumentException('Walk-in sales only support cash or bank payment.');
        }

        if ($isWalkin && $paidAmount <= 0) {
            throw new InvalidArgumentException('Walk-in payment amount must be greater than zero.');
        }

        $transactionDate = $sale->order_date
            ? \Illuminate\Support\Carbon::parse($sale->order_date)->toDateString()
            : now()->toDateString();
        $description = 'Invoice ' . ($sale->invoice_no ?? (string) $sale->id);
        $saleId = (int) $sale->id;
        $entries = [];

        if ($isWalkin) {
            if ($totalAmount <= 0) {
                return;
            }
            $this->ensureWalkInSaleCreditExists($totalAmount, $shopId, $saleId, $description, $transactionDate);
            $accountType = $this->resolveCashOrBank($paymentMethod);
            $accountRefId = ($accountType === AccountTransaction::ACCOUNT_TYPE_BANK && $shopBankId) ? $shopBankId : null;
            $paymentDesc = 'Payment – Invoice ' . ($sale->invoice_no ?? (string) $sale->id);
            $entries[] = $this->entry($shopId, $accountType, $accountRefId, AccountTransaction::DIRECTION_DEBIT, $paidAmount, $saleId, $paymentDesc, $transactionDate);
        } else {
            if ($totalAmount > 0) {
                $this->ensureInvoiceEntriesExist($totalAmount, $shopId, $saleId, $description, $transactionDate, $customer);
            }
            if ($paidAmount > 0 && in_array($paymentMethod, ['cash', 'bank', 'cheque'], true)) {
                $accountType = $this->resolveCashOrBank($paymentMethod);
                $accountRefId = ($accountType === AccountTransaction::ACCOUNT_TYPE_BANK && $shopBankId) ? $shopBankId : null;
                $paymentDesc = 'Payment – Invoice ' . ($sale->invoice_no ?? (string) $sale->id);
                $entries[] = $this->entry($shopId, $accountType, $accountRefId, AccountTransaction::DIRECTION_DEBIT, $paidAmount, $saleId, $paymentDesc, $transactionDate);
                if ($customer) {
                    $entries[] = $this->entry($shopId, AccountTransaction::ACCOUNT_TYPE_CUSTOMER, (int) $customer->id, AccountTransaction::DIRECTION_CREDIT, $paidAmount, $saleId, $paymentDesc, $transactionDate);
                }
            }
        }

        foreach ($entries as $attrs) {
            AccountTransaction::create($attrs);
        }
    }

    /**
     * Ensure walk-in sale credit exists once for the full invoice total. Idempotent.
     */
    private function ensureWalkInSaleCreditExists(
        float $totalAmount,
        int $shopId,
        int $saleId,
        string $description,
        string $transactionDate
    ): void {
        $exists = AccountTransaction::query()
            ->where('source_type', AccountTransaction::SOURCE_SALE)
            ->where('source_id', $saleId)
            ->where('account_type', AccountTransaction::ACCOUNT_TYPE_SALE)
            ->where('direction', AccountTransaction::DIRECTION_CREDIT)
            ->where('shop_id', $shopId)
            ->exists();

        if ($exists) {
            return;
        }

        AccountTransaction::create($this->entry(
            $shopId,
            AccountTransaction::ACCOUNT_TYPE_SALE,
            null,
            AccountTransaction::DIRECTION_CREDIT,
            $totalAmount,
            $saleId,
            $description,
            $transactionDate
        ));
    }

    /**
     * Ensure invoice pair (Customer debit, Sale credit) exists for this sale. Idempotent.
     */
    private function ensureInvoiceEntriesExist(
        float $totalAmount,
        int $shopId,
        int $saleId,
        string $description,
        string $transactionDate,
        $customer
    ): void {
        $exists = AccountTransaction::query()
            ->where('source_type', AccountTransaction::SOURCE_SALE)
            ->where('source_id', $saleId)
            ->where('account_type', AccountTransaction::ACCOUNT_TYPE_SALE)
            ->where('direction', AccountTransaction::DIRECTION_CREDIT)
            ->where('shop_id', $shopId)
            ->exists();

        if ($exists) {
            return;
        }

        AccountTransaction::create($this->entry($shopId, AccountTransaction::ACCOUNT_TYPE_SALE, null, AccountTransaction::DIRECTION_CREDIT, $totalAmount, $saleId, $description, $transactionDate));
        if ($customer) {
            AccountTransaction::create($this->entry($shopId, AccountTransaction::ACCOUNT_TYPE_CUSTOMER, (int) $customer->id, AccountTransaction::DIRECTION_DEBIT, $totalAmount, $saleId, $description, $transactionDate));
        }
    }

    private function resolveCashOrBank(string $paymentMethod): string
    {
        return in_array($paymentMethod, ['bank', 'cheque'], true)
            ? AccountTransaction::ACCOUNT_TYPE_BANK
            : AccountTransaction::ACCOUNT_TYPE_CASH;
    }

    private function entry(
        int $shopId,
        string $accountType,
        ?int $accountRefId,
        string $direction,
        float $amount,
        int $sourceId,
        string $description,
        string $transactionDate
    ): array {
        return [
            'shop_id' => $shopId,
            'account_type' => $accountType,
            'account_ref_id' => $accountRefId,
            'direction' => $direction,
            'amount' => round($amount, 2),
            'source_type' => AccountTransaction::SOURCE_SALE,
            'source_id' => $sourceId,
            'description' => $description,
            'transaction_date' => $transactionDate,
        ];
    }

    /**
     * Validate SUM(debit) = SUM(credit) for all entries with source_id = sale.id.
     */
    private function validateDebitCreditBalance(int $saleId): void
    {
        $rows = AccountTransaction::query()
            ->where('source_type', AccountTransaction::SOURCE_SALE)
            ->where('source_id', $saleId)
            ->get();
        $totalDebit = $rows->where('direction', AccountTransaction::DIRECTION_DEBIT)->sum('amount');
        $totalCredit = $rows->where('direction', AccountTransaction::DIRECTION_CREDIT)->sum('amount');
        if (abs((float) $totalDebit - (float) $totalCredit) > 0.01) {
            throw new InvalidArgumentException(
                sprintf(
                    'Ledger imbalance for sale %d: debit=%s credit=%s.',
                    $saleId,
                    number_format($totalDebit, 2),
                    number_format($totalCredit, 2)
                )
            );
        }
    }
}
