<?php

namespace App\Services\Reports;

use App\Models\AccountTransaction;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Read-only Day Book from the ledger only (`account_transactions`).
 *
 * Rows: (1) sale cash/bank debits, (2) expense cash/bank credits, (3) standalone customer payment cash/bank debits.
 * Dates are filtered on `transaction_date`. Does not read payment_logs, activities, or shop_expenses directly.
 */
class DayBookReportService
{
    /**
     * @param  \Illuminate\Support\Collection<int, int>  $shopIds
     * @return Collection<int, object{
     *   sort_date: string,
     *   sort_key: string,
     *   date: string,
     *   ref_no: string,
     *   type: string,
     *   particular: string,
     *   net_amount: float,
     *   title: string,
     *   amount_in_cash: float,
     *   amount_out_cash: float,
     *   amount_in_online: float,
     *   amount_out_online: float
     * }>
     */
    public function buildRows(Carbon $start, Carbon $end, Collection $shopIds): Collection
    {
        if ($shopIds->isEmpty()) {
            return collect();
        }

        $rows = collect();
        $rows = $rows->merge($this->saleLedgerInflowRows($start, $end, $shopIds));
        $rows = $rows->merge($this->expenseLedgerOutflowRows($start, $end, $shopIds));
        $rows = $rows->merge($this->customerPaymentLedgerInflowRows($start, $end, $shopIds));

        return $rows
            ->sortBy([
                ['sort_date', 'asc'],
                ['sort_key', 'asc'],
            ])
            ->values();
    }

    /**
     * Cash/bank received on sales: ledger debits on cash or bank with source_type sale (per SalePostingService).
     *
     * @param  \Illuminate\Support\Collection<int, int>  $shopIds
     */
    protected function saleLedgerInflowRows(Carbon $start, Carbon $end, Collection $shopIds): Collection
    {
        $base = AccountTransaction::query()
            ->where('account_transactions.source_type', AccountTransaction::SOURCE_SALE)
            ->where('account_transactions.direction', AccountTransaction::DIRECTION_DEBIT)
            ->whereIn('account_transactions.account_type', [
                AccountTransaction::ACCOUNT_TYPE_CASH,
                AccountTransaction::ACCOUNT_TYPE_BANK,
            ])
            ->whereBetween('account_transactions.transaction_date', [$start->toDateString(), $end->toDateString()])
            ->whereIn('account_transactions.shop_id', $shopIds)
            ->join('orders', 'orders.id', '=', 'account_transactions.source_id')
            ->whereNull('orders.deleted_at')
            ->leftJoin('customers', 'customers.id', '=', 'orders.customer_id')
            ->select([
                'account_transactions.id',
                'account_transactions.transaction_date',
                'account_transactions.amount',
                'account_transactions.account_type',
                'orders.id as order_id',
                'orders.invoice_no',
                'orders.customer_id',
                'customers.shopname',
                'customers.name as customer_name',
            ]);

        return $base->get()->map(function ($row) {
            $net = (float) $row->amount;
            $sortDate = Carbon::parse($row->transaction_date)->format('Y-m-d');
            $customerLabel = trim((string) ($row->shopname ?: $row->customer_name ?: ''));
            if ($row->customer_id === null) {
                $customerLabel = 'Walk-in';
            } elseif ($customerLabel === '') {
                $customerLabel = '—';
            }

            $isBank = $row->account_type === AccountTransaction::ACCOUNT_TYPE_BANK;
            $channel = $isBank ? 'BANK' : 'CASH';

            return (object) [
                'sort_date' => $sortDate,
                'sort_key' => 'SALE-' . $row->id,
                'date' => $sortDate,
                'ref_no' => (string) ($row->invoice_no ?? 'INV-' . $row->order_id),
                'type' => 'SALE',
                'particular' => $customerLabel,
                'net_amount' => $net,
                'title' => 'Sale — ' . $channel,
                'amount_in_cash' => $isBank ? 0.0 : $net,
                'amount_out_cash' => 0.0,
                'amount_in_online' => $isBank ? $net : 0.0,
                'amount_out_online' => 0.0,
            ];
        });
    }

    /**
     * Cash/bank paid on expenses: ledger credits on cash or bank with source_type expense (per ExpenseLedgerService).
     *
     * @param  \Illuminate\Support\Collection<int, int>  $shopIds
     */
    protected function expenseLedgerOutflowRows(Carbon $start, Carbon $end, Collection $shopIds): Collection
    {
        $base = AccountTransaction::query()
            ->where('account_transactions.source_type', AccountTransaction::SOURCE_EXPENSE)
            ->where('account_transactions.direction', AccountTransaction::DIRECTION_CREDIT)
            ->whereIn('account_transactions.account_type', [
                AccountTransaction::ACCOUNT_TYPE_CASH,
                AccountTransaction::ACCOUNT_TYPE_BANK,
            ])
            ->whereBetween('account_transactions.transaction_date', [$start->toDateString(), $end->toDateString()])
            ->whereIn('account_transactions.shop_id', $shopIds)
            ->leftJoin('activities', 'activities.id', '=', 'account_transactions.source_id')
            ->leftJoin('expenses', 'expenses.id', '=', 'activities.expense_id')
            ->leftJoin('customers', 'customers.id', '=', 'activities.customer_id')
            ->select([
                'account_transactions.id',
                'account_transactions.transaction_date',
                'account_transactions.amount',
                'account_transactions.description as at_description',
                'account_transactions.account_type',
                'activities.title as activity_title',
                'activities.description as activity_description',
                'expenses.expense_title',
                'customers.shopname',
                'customers.name as customer_name',
            ]);

        return $base->get()->map(function ($row) {
            $net = (float) $row->amount;
            $sortDate = Carbon::parse($row->transaction_date)->format('Y-m-d');
            $category = trim((string) ($row->expense_title ?: $row->activity_title ?: '')) ?: 'Expense';
            $desc = trim((string) ($row->activity_description ?? ''));
            $particular = $desc !== '' ? $category . ' — ' . $desc : $category;
            if ($particular === '' && $row->at_description) {
                $particular = (string) $row->at_description;
            }

            $custTitle = trim((string) ($row->shopname ?: $row->customer_name ?: ''));
            $title = $custTitle !== '' ? $custTitle : $category;

            $isBank = $row->account_type === AccountTransaction::ACCOUNT_TYPE_BANK;

            return (object) [
                'sort_date' => $sortDate,
                'sort_key' => 'EXP-' . $row->id,
                'date' => $sortDate,
                'ref_no' => 'EXP-' . $row->id,
                'type' => 'EXPENSE',
                'particular' => $particular,
                'net_amount' => $net,
                'title' => $title,
                'amount_in_cash' => 0.0,
                'amount_out_cash' => $isBank ? 0.0 : $net,
                'amount_in_online' => 0.0,
                'amount_out_online' => $isBank ? $net : 0.0,
            ];
        });
    }

    /**
     * Standalone customer payments: cash/bank debit legs; customer name from paired customer credit row.
     *
     * @param  \Illuminate\Support\Collection<int, int>  $shopIds
     */
    protected function customerPaymentLedgerInflowRows(Carbon $start, Carbon $end, Collection $shopIds): Collection
    {
        $t = (new AccountTransaction())->getTable();
        $st = AccountTransaction::SOURCE_CUSTOMER_PAYMENT;
        $custType = AccountTransaction::ACCOUNT_TYPE_CUSTOMER;
        $credit = AccountTransaction::DIRECTION_CREDIT;
        $debit = AccountTransaction::DIRECTION_DEBIT;

        $rows = DB::table("{$t} as pay")
            ->join("{$t} as cust", function ($join) use ($st, $custType, $credit) {
                $join->on('cust.source_id', '=', 'pay.source_id')
                    ->on('cust.shop_id', '=', 'pay.shop_id')
                    ->where('cust.source_type', '=', $st)
                    ->where('cust.account_type', '=', $custType)
                    ->where('cust.direction', '=', $credit)
                    ->whereNull('cust.deleted_at');
            })
            ->leftJoin('customers', 'customers.id', '=', 'cust.account_ref_id')
            ->where('pay.source_type', $st)
            ->whereIn('pay.account_type', [
                AccountTransaction::ACCOUNT_TYPE_CASH,
                AccountTransaction::ACCOUNT_TYPE_BANK,
            ])
            ->where('pay.direction', $debit)
            ->whereNull('pay.deleted_at')
            ->whereBetween('pay.transaction_date', [$start->toDateString(), $end->toDateString()])
            ->whereIn('pay.shop_id', $shopIds->all())
            ->select([
                'pay.id',
                'pay.transaction_date',
                'pay.amount',
                'pay.description as pay_description',
                'pay.account_type',
                'pay.receipt_no as pay_receipt_no',
                'cust.receipt_no as cust_receipt_no',
                'customers.shopname',
                'customers.name as customer_name',
            ])
            ->get();

        return $rows->map(function ($row) {
            $net = (float) $row->amount;
            $sortDate = Carbon::parse($row->transaction_date)->format('Y-m-d');
            $title = trim((string) ($row->shopname ?: $row->customer_name ?: '')) ?: '—';
            $ref = trim((string) ($row->pay_receipt_no ?: $row->cust_receipt_no ?: ''));
            if ($ref === '') {
                $ref = 'CP-' . $row->id;
            }
            $desc = trim((string) ($row->pay_description ?? ''));
            $particular = $desc !== '' ? $desc : 'Customer payment';

            $isBank = $row->account_type === AccountTransaction::ACCOUNT_TYPE_BANK;

            return (object) [
                'sort_date' => $sortDate,
                'sort_key' => 'RCP-' . $row->id,
                'date' => $sortDate,
                'ref_no' => $ref,
                'type' => 'RECEIPT',
                'particular' => $particular,
                'net_amount' => $net,
                'title' => $title,
                'amount_in_cash' => $isBank ? 0.0 : $net,
                'amount_out_cash' => 0.0,
                'amount_in_online' => $isBank ? $net : 0.0,
                'amount_out_online' => 0.0,
            ];
        });
    }

    /** @param  \Illuminate\Support\Collection<int, object>  $rows */
    public function sumTotals(Collection $rows): array
    {
        return [
            'net_amount' => (float) $rows->sum('net_amount'),
            'amount_in_cash' => (float) $rows->sum('amount_in_cash'),
            'amount_out_cash' => (float) $rows->sum('amount_out_cash'),
            'amount_in_online' => (float) $rows->sum('amount_in_online'),
            'amount_out_online' => (float) $rows->sum('amount_out_online'),
        ];
    }
}
