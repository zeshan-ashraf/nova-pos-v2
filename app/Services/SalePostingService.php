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
 * OPTION 2 — Walk-in customer: Cash/Bank debit + Sale credit only; full payment required.
 */
class SalePostingService
{
    /**
     * Post sale ledger entries for an order (invoice + optional payment).
     * Call once for invoice legs (1)(2); call again for each payment slice to add (3)(4).
     * All entries use source_id = $sale->id.
     *
     * @param Order $sale The order (sale/invoice)
     * @param float $paidAmount Amount paid in this payment (0 = invoice only)
     * @param string $paymentMethod One of: cash, bank, cheque, credit
     * @param int|null $shopBankId Required when paymentMethod is bank/cheque
     * @return void
     * @throws InvalidArgumentException Walk-in partial payment, or invalid input
     */
    public function postSale(Order $sale, float $paidAmount, string $paymentMethod, ?int $shopBankId = null): void
    {
        $sale->loadMissing('customer');
        $customer = $sale->customer;
        $totalAmount = (float) $sale->total;
        $shopId = (int) $sale->shop_id;

        // No customer or walk-in: do not touch customer ledger; payment slices posted separately
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

        DB::transaction(function () use (
            $sale,
            $totalAmount,
            $paidAmount,
            $paymentMethod,
            $shopBankId,
            $customer,
            $isWalkin,
            $shopId,
            $transactionDate,
            $description
        ) {
            $saleId = (int) $sale->id;
            $entries = [];

            if ($isWalkin) {
                // OPTION 2 — Walk-in: Sale credit once; each payment posts a Cash/Bank debit slice
                if ($totalAmount <= 0) {
                    return;
                }
                $this->ensureWalkInSaleCreditExists($sale, $totalAmount, $shopId, $saleId, $description, $transactionDate);
                $accountType = $this->resolveCashOrBank($paymentMethod);
                $accountRefId = ($accountType === AccountTransaction::ACCOUNT_TYPE_BANK && $shopBankId) ? $shopBankId : null;
                $paymentDesc = 'Payment – Invoice ' . ($sale->invoice_no ?? (string) $sale->id);
                $entries[] = $this->entry($shopId, $accountType, $accountRefId, AccountTransaction::DIRECTION_DEBIT, $paidAmount, $saleId, $paymentDesc, $transactionDate);
            } else {
                // OPTION 1 — Registered customer: (1) Customer debit, (2) Sale credit
                if ($totalAmount > 0) {
                    $this->ensureInvoiceEntriesExist($sale, $totalAmount, $shopId, $saleId, $description, $transactionDate, $customer);
                }
                // (3) Cash/Bank debit, (4) Customer credit — only when paid and method is cash/bank/cheque
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

            $this->validateDebitCreditBalance($saleId);
        });
    }

    /**
     * Ensure walk-in sale credit exists once for the full invoice total. Idempotent.
     */
    private function ensureWalkInSaleCreditExists(
        Order $sale,
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
        Order $sale,
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
     * Validate SUM(debit) = SUM(credit) for all entries with source_id = sale.id. Rollback if not.
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
