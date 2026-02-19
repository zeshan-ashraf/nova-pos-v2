<?php

namespace App\Console\Commands;

use App\Models\AccountTransaction;
use App\Models\Activity;
use App\Models\Order;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Backfill missing journal entries into account_transactions for historical data.
 * Safe to run multiple times; skips orders/expenses that already have entries.
 */
class RepairJournalCommand extends Command
{
    protected $signature = 'accounting:repair-journal
        {--dry-run : Do not insert; only report how many would be inserted}
        {--shop_id= : Repair only this shop ID}
        {--from_date= : Only repair orders/expenses on or after this date (Y-m-d)}';

    protected $description = 'Backfill missing sale and expense journal entries in account_transactions (idempotent, safe to re-run)';

    protected int $ordersScanned = 0;
    protected int $missingOrders = 0;
    protected int $saleEntriesInserted = 0;
    protected int $expensesScanned = 0;
    protected int $missingExpenses = 0;
    protected int $expenseEntriesInserted = 0;

    /** @var array<int, array{id: int, invoice_no: string, shop_id: int, total: float}> */
    protected array $missingOrderRows = [];

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $shopId = $this->option('shop_id');
        $fromDate = $this->option('from_date');

        if ($dryRun) {
            $this->warn('DRY RUN — no data will be modified.');
        }

        $this->info('Step 1: Repair missing sales journals (orders)...');
        $this->repairSalesJournals($dryRun, $shopId, $fromDate);

        $this->newLine();
        $this->info('Step 2: Repair missing expense journals (activities)...');
        $this->repairExpenseJournals($dryRun, $shopId, $fromDate);

        $this->newLine();
        $this->printSummary($dryRun);

        return 0;
    }

    protected function repairSalesJournals(bool $dryRun, ?string $shopId, ?string $fromDate): void
    {
        // Missing sale journal = no matching account_transactions row (source_type=sale, source_id=order.id,
        // account_type=sale, direction=credit, deleted_at IS NULL, shop_id match). Use LEFT JOIN so we only
        // get orders with no such row. Explicitly exclude soft-deleted orders (orders.deleted_at IS NULL).
        $query = Order::query()
            ->whereNull('orders.deleted_at')
            ->select([
                'orders.id',
                'orders.invoice_no',
                'orders.shop_id',
                'orders.total',
                'orders.order_date',
                'orders.customer_id',
            ])
            ->leftJoin('account_transactions as at', function ($join) {
                $join->on('at.source_type', '=', DB::raw("'sale'"))
                    ->on(DB::raw('at.source_id'), '=', DB::raw('orders.id'))
                    ->on('at.account_type', '=', DB::raw("'sale'"))
                    ->on('at.direction', '=', DB::raw("'credit'"))
                    ->on('at.shop_id', '=', 'orders.shop_id')
                    ->whereNull('at.deleted_at');
            })
            ->whereNull('at.id')
            ->orderBy('orders.id');

        if ($shopId !== null && $shopId !== '') {
            $query->where('orders.shop_id', (int) $shopId);
        }
        if ($fromDate !== null && $fromDate !== '') {
            $query->where('orders.order_date', '>=', $fromDate);
        }

        $chunkSize = 500;
        $query->chunkById($chunkSize, function ($orders) use ($dryRun) {
            $this->ordersScanned += $orders->count();
            foreach ($orders as $order) {
                $this->missingOrders++;
                $this->missingOrderRows[] = [
                    'id'         => (int) $order->id,
                    'invoice_no' => $order->invoice_no ?? (string) $order->id,
                    'shop_id'    => (int) $order->shop_id,
                    'total'      => (float) $order->total,
                ];
                if (!$dryRun) {
                    $this->line(sprintf(
                        'Order ID: %s | Invoice: %s | Shop ID: %s | Total: %s',
                        $order->id,
                        $order->invoice_no ?? $order->id,
                        $order->shop_id,
                        $order->total
                    ));
                    DB::transaction(function () use ($order) {
                        $desc = 'Backfilled Sale Invoice #' . ($order->invoice_no ?? $order->id);
                        $amount = (float) $order->total;
                        $date = $order->order_date instanceof \Carbon\Carbon
                            ? $order->order_date->toDateString()
                            : \Carbon\Carbon::parse($order->order_date)->toDateString();

                        AccountTransaction::create([
                            'shop_id' => $order->shop_id,
                            'account_type' => AccountTransaction::ACCOUNT_TYPE_CUSTOMER,
                            'account_ref_id' => $order->customer_id,
                            'direction' => AccountTransaction::DIRECTION_DEBIT,
                            'amount' => $amount,
                            'source_type' => AccountTransaction::SOURCE_SALE,
                            'source_id' => $order->id,
                            'transaction_date' => $date,
                            'description' => $desc,
                        ]);
                        AccountTransaction::create([
                            'shop_id' => $order->shop_id,
                            'account_type' => AccountTransaction::ACCOUNT_TYPE_SALE,
                            'account_ref_id' => null,
                            'direction' => AccountTransaction::DIRECTION_CREDIT,
                            'amount' => $amount,
                            'source_type' => AccountTransaction::SOURCE_SALE,
                            'source_id' => $order->id,
                            'transaction_date' => $date,
                            'description' => $desc,
                        ]);
                    });
                    $this->saleEntriesInserted += 2;
                }
            }
        }, 'id');

        if ($this->missingOrderRows !== []) {
            $this->newLine();
            $this->table(
                ['Order ID', 'Invoice', 'Shop', 'Total'],
                array_map(fn ($r) => [$r['id'], $r['invoice_no'], $r['shop_id'], number_format($r['total'], 2)], $this->missingOrderRows)
            );
            $this->line('Total Missing Orders Found: ' . $this->missingOrders);
            $this->newLine();
        }
    }

    protected function repairExpenseJournals(bool $dryRun, ?string $shopId, ?string $fromDate): void
    {
        $query = Activity::query()
            ->whereNotExists(function ($q) {
                $q->select(DB::raw(1))
                    ->from('account_transactions')
                    ->whereColumn('account_transactions.source_id', 'activities.id')
                    ->where('account_transactions.source_type', AccountTransaction::SOURCE_EXPENSE);
            })
            ->where(function ($q) {
                $q->where('is_system', false)
                    ->orWhere('is_system', 0)
                    ->orWhereNull('is_system');
            })
            ->where(function ($q) {
                $q->where('payment_method', '!=', 'system')
                    ->orWhereNull('payment_method');
            })
            ->where('activity_cost', '>', 0)
            ->orderBy('activities.id');

        if ($shopId !== null && $shopId !== '') {
            $query->where('activities.shop_id', (int) $shopId);
        }
        if ($fromDate !== null && $fromDate !== '') {
            $query->where('activities.date', '>=', $fromDate);
        }

        $chunkSize = 500;
        $query->chunkById($chunkSize, function ($activities) use ($dryRun) {
            $this->expensesScanned += $activities->count();
            foreach ($activities as $activity) {
                if (($activity->is_system ?? false) || ($activity->payment_method ?? '') === 'system') {
                    continue;
                }
                $this->missingExpenses++;
                if (!$dryRun) {
                    DB::transaction(function () use ($activity) {
                        $amount = (float) $activity->activity_cost;
                        $date = $activity->date instanceof \Carbon\Carbon
                            ? $activity->date->toDateString()
                            : \Carbon\Carbon::parse($activity->date)->toDateString();
                        $descExpense = 'Backfilled Expense: ' . ($activity->title ?? '#' . $activity->id);
                        $paymentMethod = strtolower((string) ($activity->payment_method ?? 'cash'));
                        $isBank = in_array($paymentMethod, ['bank', 'cheque'], true);
                        $accountType = $isBank ? AccountTransaction::ACCOUNT_TYPE_BANK : AccountTransaction::ACCOUNT_TYPE_CASH;
                        $accountRefId = $isBank && $activity->shop_bank_id ? $activity->shop_bank_id : null;

                        AccountTransaction::create([
                            'shop_id' => $activity->shop_id,
                            'account_type' => AccountTransaction::ACCOUNT_TYPE_EXPENSE,
                            'account_ref_id' => null,
                            'direction' => AccountTransaction::DIRECTION_DEBIT,
                            'amount' => $amount,
                            'source_type' => AccountTransaction::SOURCE_EXPENSE,
                            'source_id' => $activity->id,
                            'transaction_date' => $date,
                            'description' => $descExpense,
                        ]);
                        AccountTransaction::create([
                            'shop_id' => $activity->shop_id,
                            'account_type' => $accountType,
                            'account_ref_id' => $accountRefId,
                            'direction' => AccountTransaction::DIRECTION_CREDIT,
                            'amount' => $amount,
                            'source_type' => AccountTransaction::SOURCE_EXPENSE,
                            'source_id' => $activity->id,
                            'transaction_date' => $date,
                            'description' => 'Backfilled Expense Payment',
                        ]);
                    });
                    $this->expenseEntriesInserted += 2;
                }
            }
        });
    }

    protected function printSummary(bool $dryRun): void
    {
        $this->table(
            ['Metric', 'Count'],
            [
                ['Total Orders Scanned', $this->ordersScanned],
                ['Missing Orders Found', $this->missingOrders],
                ['Sale Journal Entries ' . ($dryRun ? '(would insert)' : 'Inserted'), $dryRun ? $this->missingOrders * 2 : $this->saleEntriesInserted],
                ['Total Expenses Scanned', $this->expensesScanned],
                ['Missing Expenses Found', $this->missingExpenses],
                ['Expense Journal Entries ' . ($dryRun ? '(would insert)' : 'Inserted'), $dryRun ? $this->missingExpenses * 2 : $this->expenseEntriesInserted],
            ]
        );
        if ($dryRun && ($this->missingOrders > 0 || $this->missingExpenses > 0)) {
            $this->info('Run without --dry-run to apply changes.');
        }
    }
}
