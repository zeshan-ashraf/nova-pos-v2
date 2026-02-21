<?php

namespace App\Console\Commands;

use App\Models\AccountTransaction;
use App\Models\Order;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Repair corrupted account_transactions where payment entries use source_id = payment_logs.id
 * instead of source_id = sale.id (order.id).
 *
 * Safe for production: transaction-wrapped, reversible (rollback on error), logged, dry-run.
 */
class RepairSalesLedger extends Command
{
    protected $signature = 'ledger:repair-sales
        {--shop= : Limit repair to this shop ID}
        {--dry-run : Do not update; only scan and report}
        {--only-walkin : (Reserved for future) Limit to walk-in related entries}';

    protected $description = 'Repair sale payment entries in account_transactions (wrong source_id → order.id)';

    private int $totalScanned = 0;
    private int $totalCorrupted = 0;
    private int $totalFixed = 0;
    private int $totalSkippedNoSale = 0;
    private int $totalSkippedDuplicate = 0;

    /** @var array<int, int> Map of repaired source_id (order.id) for integrity check */
    private array $repairedSourceIds = [];

    private const DESCRIPTION_PREFIX_SALE_PAYMENT = 'Sale payment – Invoice ';
    private const DESCRIPTION_PREFIX_PAYMENT = 'Payment – Invoice ';
    private const CHUNK_SIZE = 500;

    public function handle(): int
    {
        $shopId = $this->option('shop');
        $dryRun = (bool) $this->option('dry-run');
        $onlyWalkin = (bool) $this->option('only-walkin');

        if ($dryRun) {
            $this->warn('DRY RUN — no database updates will be performed.');
        }
        if ($onlyWalkin) {
            $this->warn('--only-walkin is reserved for future use; all matching rows are processed.');
        }

        try {
            DB::transaction(function () use ($shopId, $dryRun) {
                $this->runRepair($shopId, $dryRun);
                $this->runIntegrityCheck();
            });
        } catch (Throwable $e) {
            $this->error('Repair failed: ' . $e->getMessage());
            $this->error('Entire transaction has been rolled back.');
            if ($this->output->isVerbose()) {
                $this->error($e->getTraceAsString());
            }
            return 1;
        }

        $this->printSummary();
        return 0;
    }

    private function runRepair(?string $shopId, bool $dryRun): void
    {
        // Repair both legs of old payment entries: "Sale payment – Invoice X" (cash/bank debit)
        // and "Payment – Invoice X" (customer credit). Both were wrongly source_id = payment_logs.id.
        $query = AccountTransaction::query()
            ->where('source_type', AccountTransaction::SOURCE_SALE)
            ->where(function ($q) {
                $q->where('description', 'like', 'Sale payment%')
                    ->orWhere('description', 'like', 'Payment – Invoice%');
            })
            ->whereNull('deleted_at')
            ->orderBy('id');

        if ($shopId !== null && $shopId !== '') {
            $query->where('shop_id', (int) $shopId);
        }

        $query->chunkById(self::CHUNK_SIZE, function ($rows) use ($dryRun) {
            foreach ($rows as $tx) {
                $this->processRow($tx, $dryRun);
            }
        });
    }

    private function processRow(AccountTransaction $tx, bool $dryRun): void
    {
        $this->totalScanned++;

        $invoiceNo = $this->extractInvoiceNoFromDescription($tx->description);
        if ($invoiceNo === null || $invoiceNo === '') {
            return;
        }

        $sale = $this->findSaleByInvoiceOrId((int) $tx->shop_id, $invoiceNo);
        if ($sale === null) {
            $this->totalSkippedNoSale++;
            $this->logRepair($tx->id, (int) $tx->shop_id, (int) $tx->source_id, null, $invoiceNo, 'skipped_no_sale');
            return;
        }

        $saleId = (int) $sale->id;
        if ((int) $tx->source_id === $saleId) {
            return;
        }

        $this->totalCorrupted++;
        $oldSourceId = (int) $tx->source_id;

        if ($dryRun) {
            $this->logRepair($tx->id, (int) $tx->shop_id, $oldSourceId, $saleId, $invoiceNo, 'would_fix');
            return;
        }

        $tx->source_id = $saleId;
        $tx->save();

        $this->totalFixed++;
        $this->repairedSourceIds[$saleId] = ($this->repairedSourceIds[$saleId] ?? 0) + 1;
        $this->logRepair($tx->id, (int) $tx->shop_id, $oldSourceId, $saleId, $invoiceNo, 'fixed');
    }

    private function extractInvoiceNoFromDescription(?string $description): ?string
    {
        if ($description === null || $description === '') {
            return null;
        }
        if (str_starts_with($description, self::DESCRIPTION_PREFIX_SALE_PAYMENT)) {
            return trim(substr($description, strlen(self::DESCRIPTION_PREFIX_SALE_PAYMENT)));
        }
        if (str_starts_with($description, self::DESCRIPTION_PREFIX_PAYMENT)) {
            return trim(substr($description, strlen(self::DESCRIPTION_PREFIX_PAYMENT)));
        }
        if (preg_match('/Invoice\s+(.+)$/', $description, $m)) {
            return trim($m[1]);
        }
        return null;
    }

    /**
     * Find order (sale) by invoice_no and shop_id. If invoice part is numeric, also try order id.
     * Returns null if not found or duplicate invoice_no per shop (ambiguous).
     */
    private function findSaleByInvoiceOrId(int $shopId, string $invoiceNo): ?Order
    {
        $countByInvoice = Order::query()
            ->where('shop_id', $shopId)
            ->where('invoice_no', $invoiceNo)
            ->whereNull('deleted_at')
            ->count();

        if ($countByInvoice > 1) {
            $this->totalSkippedDuplicate++;
            $this->warn("Duplicate invoice_no for shop {$shopId}: '{$invoiceNo}'. Skipping row.");
            return null;
        }

        if ($countByInvoice === 1) {
            return Order::query()
                ->where('shop_id', $shopId)
                ->where('invoice_no', $invoiceNo)
                ->whereNull('deleted_at')
                ->first();
        }

        if (is_numeric($invoiceNo)) {
            $order = Order::query()
                ->where('shop_id', $shopId)
                ->where('id', (int) $invoiceNo)
                ->whereNull('deleted_at')
                ->first();
            if ($order !== null) {
                return $order;
            }
        }

        return null;
    }

    private function logRepair(
        int $transactionId,
        int $shopId,
        int $oldSourceId,
        ?int $newSourceId,
        string $invoiceNo,
        string $action
    ): void {
        $msg = sprintf(
            'transaction_id=%d shop_id=%d old_source_id=%d new_source_id=%s invoice_no=%s action=%s',
            $transactionId,
            $shopId,
            $oldSourceId,
            $newSourceId === null ? 'null' : (string) $newSourceId,
            $invoiceNo,
            $action
        );
        $this->line($msg);
        if (function_exists('logger')) {
            logger()->info('ledger:repair-sales', [
                'transaction_id' => $transactionId,
                'shop_id' => $shopId,
                'old_source_id' => $oldSourceId,
                'new_source_id' => $newSourceId,
                'invoice_no' => $invoiceNo,
                'action' => $action,
            ]);
        }
    }

    private function runIntegrityCheck(): void
    {
        if ($this->totalFixed === 0 || empty($this->repairedSourceIds)) {
            return;
        }

        $this->info('Running integrity check (SUM(debit) == SUM(credit)) for repaired source_ids...');

        foreach (array_keys($this->repairedSourceIds) as $sourceId) {
            $rows = AccountTransaction::query()
                ->where('source_type', AccountTransaction::SOURCE_SALE)
                ->where('source_id', $sourceId)
                ->whereNull('deleted_at')
                ->get();

            $totalDebit = (float) $rows->where('direction', AccountTransaction::DIRECTION_DEBIT)->sum('amount');
            $totalCredit = (float) $rows->where('direction', AccountTransaction::DIRECTION_CREDIT)->sum('amount');

            if (abs($totalDebit - $totalCredit) > 0.01) {
                throw new \RuntimeException(sprintf(
                    'Integrity check failed for source_id=%d: debit=%s credit=%s. Rollback required.',
                    $sourceId,
                    number_format($totalDebit, 2),
                    number_format($totalCredit, 2)
                ));
            }
        }

        $this->info('Integrity check passed for all repaired source_ids.');
    }

    private function printSummary(): void
    {
        $this->newLine();
        $this->info('--- Summary ---');
        $this->table(
            ['Metric', 'Count'],
            [
                ['Total scanned', $this->totalScanned],
                ['Total corrupted found', $this->totalCorrupted],
                ['Total fixed', $this->totalFixed],
                ['Total skipped (no matching sale)', $this->totalSkippedNoSale],
                ['Total skipped (duplicate invoice)', $this->totalSkippedDuplicate],
            ]
        );
    }
}
