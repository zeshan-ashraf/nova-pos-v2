<?php

namespace App\Console\Commands;

use App\Models\AccountTransaction;
use App\Models\Order;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Legacy repair: fix account_transactions where source_type='sale' and source_id
 * incorrectly stores payment_logs.id instead of orders.id.
 *
 * Only touches rows with source_type='sale'. Production-safe: transaction, rollback, dry-run.
 */
class RepairSalesLedger extends Command
{
    protected $signature = 'ledger:repair-sales
        {--shop= : Limit repair to this shop ID}
        {--dry-run : Do not update; only print what would be updated}';

    protected $description = 'Repair sale ledger rows (wrong source_id → order.id)';

    private int $totalCorrupted = 0;
    private int $totalRepaired = 0;
    private int $totalSkipped = 0;

    private const CHUNK_SIZE = 500;
    private const INVOICE_REGEX = '/INV-\d+/';

    public function handle(): int
    {
        $shopId = $this->option('shop');
        $dryRun = (bool) $this->option('dry-run');

        if ($dryRun) {
            $this->warn('DRY RUN — no database updates will be performed.');
        }

        try {
            if ($dryRun) {
                $this->runRepair($shopId, true);
            } else {
                DB::transaction(function () use ($shopId) {
                    $this->runRepair($shopId, false);
                    $this->runIntegrityCheck();
                });
            }
        } catch (Throwable $e) {
            $this->error('Repair failed: ' . $e->getMessage());
            if (!$dryRun) {
                $this->error('Entire transaction has been rolled back.');
            }
            if ($this->output->isVerbose()) {
                $this->error($e->getTraceAsString());
            }
            return 1;
        }

        $this->printSummary($dryRun);
        return 0;
    }

    private function runRepair(?string $shopId, bool $dryRun): void
    {
        $query = AccountTransaction::query()
            ->where('source_type', AccountTransaction::SOURCE_SALE)
            ->whereNull('deleted_at')
            ->where('description', 'like', '%Invoice INV-%')
            ->orderBy('id');

        if ($shopId !== null && $shopId !== '') {
            $query->where('shop_id', (int) $shopId);
        }

        $query->chunk(self::CHUNK_SIZE, function ($rows) use ($dryRun) {
            foreach ($rows as $tx) {
                $this->processRow($tx, $dryRun);
            }
        });
    }

    private function processRow(AccountTransaction $tx, bool $dryRun): void
    {
        $shopId = (int) $tx->shop_id;
        $currentSourceId = (int) $tx->source_id;

        // Corrupted = source_id is not a valid order id for this shop
        $orderExists = Order::query()
            ->where('id', $currentSourceId)
            ->where('shop_id', $shopId)
            ->whereNull('deleted_at')
            ->exists();

        if ($orderExists) {
            return;
        }

        $this->totalCorrupted++;

        $invoiceNo = $this->extractInvoiceNo($tx->description);
        if ($invoiceNo === null || $invoiceNo === '') {
            $this->warn("Skipped transaction ID {$tx->id}: no invoice number in description.");
            $this->totalSkipped++;
            return;
        }

        $order = Order::query()
            ->where('invoice_no', $invoiceNo)
            ->where('shop_id', $shopId)
            ->whereNull('deleted_at')
            ->first();

        if ($order === null) {
            $this->warn("Skipped transaction ID {$tx->id}: no order found for invoice {$invoiceNo} in shop {$shopId}.");
            $this->totalSkipped++;
            return;
        }

        $newSourceId = (int) $order->id;

        if ($dryRun) {
            $this->info("Would update transaction ID {$tx->id}: source_id {$currentSourceId} → {$newSourceId}");
            $this->totalRepaired++;
            return;
        }

        $tx->source_id = $newSourceId;
        $tx->save();

        $this->info("Updated transaction ID {$tx->id}: source_id {$currentSourceId} → {$newSourceId}");
        $this->totalRepaired++;
    }

    private function extractInvoiceNo(?string $description): ?string
    {
        if ($description === null || $description === '') {
            return null;
        }
        if (preg_match(self::INVOICE_REGEX, $description, $m)) {
            return $m[0];
        }
        return null;
    }

    private function runIntegrityCheck(): void
    {
        $this->info('Running integrity check (groupBy shop_id, source_type, source_id; HAVING net != 0)...');

        $violations = DB::table('account_transactions')
            ->selectRaw('
                shop_id,
                source_type,
                source_id,
                SUM(CASE WHEN direction = ? THEN amount ELSE 0 END) AS debit,
                SUM(CASE WHEN direction = ? THEN amount ELSE 0 END) AS credit,
                SUM(CASE WHEN direction = ? THEN amount ELSE -amount END) AS net
            ', [
                AccountTransaction::DIRECTION_DEBIT,
                AccountTransaction::DIRECTION_CREDIT,
                AccountTransaction::DIRECTION_DEBIT,
            ])
            ->where('source_type', AccountTransaction::SOURCE_SALE)
            ->whereNull('deleted_at')
            ->groupBy('shop_id', 'source_type', 'source_id')
            ->havingRaw('ABS(net) > 0.01')
            ->get();

        if ($violations->isNotEmpty()) {
            $this->error('Integrity check failed after repair. Unbalanced groups:');
            foreach ($violations as $v) {
                $this->error(sprintf(
                    '  shop_id=%s source_type=%s source_id=%s debit=%s credit=%s net=%s',
                    $v->shop_id,
                    $v->source_type,
                    $v->source_id,
                    $v->debit ?? 0,
                    $v->credit ?? 0,
                    $v->net ?? 0
                ));
            }
            throw new \RuntimeException('Integrity check failed after repair.');
        }

        $this->info('Integrity check passed.');
    }

    private function printSummary(bool $dryRun): void
    {
        $this->newLine();
        $this->info('--- Summary ---');
        $this->table(
            ['Metric', 'Value'],
            [
                ['Total corrupted rows found', $this->totalCorrupted],
                ['Total repaired', $this->totalRepaired],
                ['Total skipped', $this->totalSkipped],
                ['Mode', $dryRun ? 'DRY RUN (no changes committed)' : 'Committed'],
            ]
        );
    }
}
