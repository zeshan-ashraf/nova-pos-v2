<?php

namespace App\Console\Commands;

use App\Models\AccountTransaction;
use App\Models\Order;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Rebuild ledger entries in account_transactions for a given sale order.
 * Uses orders table as source of truth. Production-safe: transaction, dry-run, soft deletes.
 */
class RebuildSaleLedger extends Command
{
    protected $signature = 'ledger:rebuild-sale
        {--order_id= : Order ID (required)}
        {--dry-run : Show what would be done without modifying data}';

    protected $description = 'Safely rebuild ledger entries in account_transactions for a given sale order.';

    public function handle(): int
    {
        $orderId = $this->option('order_id');
        $dryRun = (bool) $this->option('dry-run');

        if ($orderId === null || $orderId === '') {
            $this->error('Option --order_id= is required.');
            $this->line('Example: php artisan ledger:rebuild-sale --order_id=123');
            $this->line('Dry run:  php artisan ledger:rebuild-sale --order_id=123 --dry-run');
            return self::FAILURE;
        }

        $orderId = (int) $orderId;
        if ($orderId < 1) {
            $this->error('--order_id must be a positive integer.');
            return self::FAILURE;
        }

        $order = Order::find($orderId);
        if (!$order) {
            $this->error("Order with ID {$orderId} not found.");
            return self::FAILURE;
        }

        $this->info("Order #{$order->id} | Invoice: " . ($order->invoice_no ?? '—') . " | Total: {$order->total} | Pay: {$order->pay} | Due: {$order->due}");

        if ($dryRun) {
            return $this->runDryRun($order);
        }

        return $this->runRebuild($order);
    }

    private function runDryRun(Order $order): int
    {
        $this->warn('--- DRY RUN (no changes will be made) ---');

        $existingCount = AccountTransaction::query()
            ->where('source_type', AccountTransaction::SOURCE_SALE)
            ->where('source_id', $order->id)
            ->count();

        $this->line("Existing account_transactions for this sale (deleted_at IS NULL): {$existingCount}");

        if ($existingCount > 0) {
            $this->line('Would soft-delete these ' . $existingCount . ' row(s).');
        }

        $toCreate = $this->describeEntriesToCreate($order);
        $this->line('Would create ' . count($toCreate) . ' new row(s):');
        foreach ($toCreate as $i => $entry) {
            $this->line('  ' . ($i + 1) . ') ' . $entry);
        }

        $this->info('Dry run completed. Run without --dry-run to apply changes.');
        return self::SUCCESS;
    }

    private function describeEntriesToCreate(Order $order): array
    {
        $total = (float) $order->total;
        $pay = (float) $order->pay;
        $invoiceNo = $order->invoice_no ?? (string) $order->id;
        $desc = [];

        $desc[] = "Sale CREDIT amount={$total} | Invoice {$invoiceNo}";
        $desc[] = "Customer DEBIT amount={$total} | Invoice {$invoiceNo}";

        if (strtolower((string) $order->payment_status) === 'credit' && $pay == 0) {
            return $desc;
        }

        if ($pay > 0) {
            $desc[] = "Cash DEBIT amount={$pay}";
            $desc[] = "Customer CREDIT amount={$pay} (payment)";
        }

        return $desc;
    }

    private function runRebuild(Order $order): int
    {
        $deletedCount = 0;
        $insertedCount = 0;

        DB::transaction(function () use ($order, &$deletedCount, &$insertedCount) {
            $query = AccountTransaction::query()
                ->where('source_type', AccountTransaction::SOURCE_SALE)
                ->where('source_id', $order->id);

            $existingCount = (clone $query)->count();
            $this->line("Existing account_transactions for this sale: {$existingCount}");

            $toDelete = (clone $query)->get();
            $deletedCount = $toDelete->count();

            foreach ($toDelete as $txn) {
                $txn->delete();
            }

            $transactionDate = $order->order_date
                ? Carbon::parse($order->order_date)->toDateString()
                : now()->toDateString();

            $total = (float) $order->total;
            $pay = (float) $order->pay;
            $invoiceNo = $order->invoice_no ?? (string) $order->id;
            $description = "Invoice {$invoiceNo}";

            if (!$order->shop_id) {
                throw new \RuntimeException('Order has no shop_id. Cannot create ledger entries.');
            }

            // A) Sale account entry (credit)
            AccountTransaction::create([
                'shop_id' => $order->shop_id,
                'account_type' => AccountTransaction::ACCOUNT_TYPE_SALE,
                'account_ref_id' => null,
                'direction' => AccountTransaction::DIRECTION_CREDIT,
                'amount' => $total,
                'source_type' => AccountTransaction::SOURCE_SALE,
                'source_id' => $order->id,
                'description' => $description,
                'transaction_date' => $transactionDate,
            ]);
            $insertedCount++;

            $customerId = $order->customer_id;

            if (strtolower((string) $order->payment_status) === 'credit' && $pay == 0) {
                // B) Customer debit only (full total)
                if ($customerId) {
                    AccountTransaction::create([
                        'shop_id' => $order->shop_id,
                        'account_type' => AccountTransaction::ACCOUNT_TYPE_CUSTOMER,
                        'account_ref_id' => $customerId,
                        'direction' => AccountTransaction::DIRECTION_DEBIT,
                        'amount' => $total,
                        'source_type' => AccountTransaction::SOURCE_SALE,
                        'source_id' => $order->id,
                        'description' => $description,
                        'transaction_date' => $transactionDate,
                    ]);
                    $insertedCount++;
                }
                return;
            }

            if ($pay > 0) {
                // B) Customer debit (full total)
                if ($customerId) {
                    AccountTransaction::create([
                        'shop_id' => $order->shop_id,
                        'account_type' => AccountTransaction::ACCOUNT_TYPE_CUSTOMER,
                        'account_ref_id' => $customerId,
                        'direction' => AccountTransaction::DIRECTION_DEBIT,
                        'amount' => $total,
                        'source_type' => AccountTransaction::SOURCE_SALE,
                        'source_id' => $order->id,
                        'description' => $description,
                        'transaction_date' => $transactionDate,
                    ]);
                    $insertedCount++;
                }

                // C) Cash debit (pay amount)
                AccountTransaction::create([
                    'shop_id' => $order->shop_id,
                    'account_type' => AccountTransaction::ACCOUNT_TYPE_CASH,
                    'account_ref_id' => null,
                    'direction' => AccountTransaction::DIRECTION_DEBIT,
                    'amount' => $pay,
                    'source_type' => AccountTransaction::SOURCE_SALE,
                    'source_id' => $order->id,
                    'description' => 'Payment – ' . $description,
                    'transaction_date' => $transactionDate,
                ]);
                $insertedCount++;

                // D) Customer credit (pay amount)
                if ($customerId) {
                    AccountTransaction::create([
                        'shop_id' => $order->shop_id,
                        'account_type' => AccountTransaction::ACCOUNT_TYPE_CUSTOMER,
                        'account_ref_id' => $customerId,
                        'direction' => AccountTransaction::DIRECTION_CREDIT,
                        'amount' => $pay,
                        'source_type' => AccountTransaction::SOURCE_SALE,
                        'source_id' => $order->id,
                        'description' => 'Payment – ' . $description,
                        'transaction_date' => $transactionDate,
                    ]);
                    $insertedCount++;
                }
            }
        });

        $this->newLine();
        $this->info('--- Rebuild summary ---');
        $this->line("Deleted rows:  {$deletedCount}");
        $this->line("Inserted rows: {$insertedCount}");

        $this->printBalanceCheck($order);

        $this->info('Rebuild completed successfully.');
        return self::SUCCESS;
    }

    private function printBalanceCheck(Order $order): void
    {
        $rows = AccountTransaction::query()
            ->where('source_type', AccountTransaction::SOURCE_SALE)
            ->where('source_id', $order->id)
            ->get();

        $saleCredit = $rows->where('account_type', AccountTransaction::ACCOUNT_TYPE_SALE)->where('direction', 'credit')->sum('amount');
        $customerDebit = $rows->where('account_type', AccountTransaction::ACCOUNT_TYPE_CUSTOMER)->where('direction', 'debit')->sum('amount');
        $customerCredit = $rows->where('account_type', AccountTransaction::ACCOUNT_TYPE_CUSTOMER)->where('direction', 'credit')->sum('amount');
        $cashDebit = $rows->where('account_type', AccountTransaction::ACCOUNT_TYPE_CASH)->where('direction', 'debit')->sum('amount');

        $this->newLine();
        $this->line('Final balance check (order total = ' . $order->total . ', pay = ' . $order->pay . '):');
        $this->line("  Sale credit:      {$saleCredit}");
        $this->line("  Customer debit:   {$customerDebit}");
        $this->line("  Customer credit:  {$customerCredit}");
        $this->line("  Cash debit:       {$cashDebit}");
    }
}
