<?php

namespace App\Console\Commands;

use App\Models\AccountTransaction;
use App\Models\Purchase;
use App\Models\PurchasePaymentLog;
use App\Models\Supplier;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Idempotent backfill: create missing account_transactions for purchases that have
 * ZERO active rows with source_type=purchase and source_id=purchase.id.
 *
 * Does not modify or delete existing rows. Does not attempt to fix partial/corrupt ledgers.
 */
class RepairPurchaseAccounts extends Command
{
    private const MONEY_EPS = 0.02;

    protected $signature = 'purchases:repair-accounts
        {purchase_ids : Comma separated purchase IDs}
        {--dry-run : Preview only, no DB changes}';

    protected $description = 'Backfill missing purchase ledger rows (account_transactions) for given purchase IDs';

    public function handle(): int
    {
        $raw = (string) $this->argument('purchase_ids');
        $dryRun = (bool) $this->option('dry-run');

        $ids = $this->parseIds($raw);
        if ($ids === []) {
            $this->error('No valid purchase IDs provided.');
            return 1;
        }

        if ($dryRun) {
            $this->warn('DRY RUN — no database inserts will be performed.');
        }

        foreach ($ids as $purchaseId) {
            try {
                $this->processPurchase((int) $purchaseId, $dryRun);
            } catch (Throwable $e) {
                $msg = "ERROR: purchase_id={$purchaseId} exception: " . $e->getMessage();
                $this->error($msg);
                Log::error($msg, ['exception' => $e]);
                return 1;
            }
        }

        return 0;
    }

    /**
     * @return int[]
     */
    private function parseIds(string $raw): array
    {
        $parts = preg_split('/\s*,\s*/', trim($raw), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $ids = [];
        foreach ($parts as $p) {
            if (ctype_digit((string) $p)) {
                $ids[] = (int) $p;
            }
        }

        return array_values(array_unique($ids));
    }

    private function processPurchase(int $purchaseId, bool $dryRun): void
    {
        $purchase = Purchase::query()->find($purchaseId);
        if (!$purchase) {
            $line = "SKIPPED: purchase_id={$purchaseId} purchase not found";
            $this->line($line);
            Log::info($line);

            return;
        }

        $hasExisting = AccountTransaction::query()
            ->where('source_type', AccountTransaction::SOURCE_PURCHASE)
            ->where('source_id', $purchase->id)
            ->whereNull('deleted_at')
            ->exists();

        if ($hasExisting) {
            $line = "SKIPPED: purchase_id={$purchaseId} already has account entries";
            $this->line($line);
            Log::info($line);

            return;
        }

        $total = round((float) $purchase->total, 2);
        $paid = round((float) ($purchase->pay ?? 0), 2);
        $due = round((float) ($purchase->due ?? 0), 2);

        if ($total <= 0) {
            $line = "ERROR: purchase_id={$purchaseId} total_amount must be positive";
            $this->warn($line);
            Log::warning($line);

            return;
        }

        if ($purchase->shop_id === null) {
            $line = "ERROR: purchase_id={$purchaseId} shop_id must not be null";
            $this->warn($line);
            Log::warning($line);

            return;
        }

        if ($paid < -self::MONEY_EPS || $due < -self::MONEY_EPS) {
            $line = "ERROR: purchase_id={$purchaseId} negative pay or due";
            $this->warn($line);
            Log::warning($line);

            return;
        }

        if (abs($paid + $due - $total) > self::MONEY_EPS) {
            $line = "ERROR: purchase_id={$purchaseId} inconsistent pay+due vs total (pay={$paid} due={$due} total={$total})";
            $this->warn($line);
            Log::warning($line);

            return;
        }

        if ($paid > $total + self::MONEY_EPS) {
            $line = "ERROR: purchase_id={$purchaseId} paid amount exceeds total";
            $this->warn($line);
            Log::warning($line);

            return;
        }

        $type = $this->classifyPurchaseType($paid, $total);
        if ($type === null) {
            $line = "ERROR: purchase_id={$purchaseId} could not classify purchase type (paid={$paid} total={$total})";
            $this->warn($line);
            Log::warning($line);

            return;
        }

        if (($type === 'credit' || $type === 'partial') && !$purchase->supplier_id) {
            $line = "ERROR: purchase_id={$purchaseId} supplier_id required for {$type} purchase";
            $this->warn($line);
            Log::warning($line);

            return;
        }

        if (($type === 'credit' || $type === 'partial') && ! Supplier::query()->whereKey($purchase->supplier_id)->exists()) {
            $line = "ERROR: purchase_id={$purchaseId} supplier_id does not exist";
            $this->warn($line);
            Log::warning($line);

            return;
        }

        if ($type === 'credit' && $due <= self::MONEY_EPS) {
            $line = "ERROR: purchase_id={$purchaseId} credit purchase requires positive due";
            $this->warn($line);
            Log::warning($line);

            return;
        }

        if ($type === 'paid' && $paid <= self::MONEY_EPS) {
            $line = "ERROR: purchase_id={$purchaseId} paid purchase requires positive pay";
            $this->warn($line);
            Log::warning($line);

            return;
        }

        if ($type === 'partial' && ($paid <= self::MONEY_EPS || $due <= self::MONEY_EPS)) {
            $line = "ERROR: purchase_id={$purchaseId} partial purchase requires positive pay and due";
            $this->warn($line);
            Log::warning($line);

            return;
        }

        $entries = $this->countPlannedEntries($due, $paid);
        if (($type === 'credit' || $type === 'paid') && $entries !== 2) {
            $line = "ERROR: purchase_id={$purchaseId} expected 2 ledger lines for type {$type}, computed {$entries}";
            $this->warn($line);
            Log::warning($line);

            return;
        }

        if ($type === 'partial' && $entries !== 3) {
            $line = "ERROR: purchase_id={$purchaseId} expected 3 ledger lines for partial, computed {$entries}";
            $this->warn($line);
            Log::warning($line);

            return;
        }

        $transactionDate = $purchase->purchase_date
            ? Carbon::parse($purchase->purchase_date)->toDateString()
            : now()->toDateString();

        if ($dryRun) {
            $this->line('WOULD INSERT:');
            $this->line("purchase_id={$purchaseId}");
            $this->line("type={$type}");
            $this->line("entries={$entries}");
            $this->line("shop_id={$purchase->shop_id}");
            $this->line("total={$total}");
            $this->line("paid={$paid}");
            Log::info('WOULD INSERT purchase ledger backfill', [
                'purchase_id' => $purchaseId,
                'type' => $type,
                'entries' => $entries,
                'shop_id' => $purchase->shop_id,
                'total' => $total,
                'paid' => $paid,
            ]);

            return;
        }

        $inserted = 0;

        DB::transaction(function () use ($purchase, $total, $paid, $due, $transactionDate, &$inserted) {
            $desc = 'Purchase ' . ($purchase->purchase_no ?? (string) $purchase->id);

            AccountTransaction::create([
                'shop_id' => $purchase->shop_id,
                'account_type' => AccountTransaction::ACCOUNT_TYPE_PURCHASE,
                'account_ref_id' => null,
                'direction' => AccountTransaction::DIRECTION_DEBIT,
                'amount' => $total,
                'source_type' => AccountTransaction::SOURCE_PURCHASE,
                'source_id' => $purchase->id,
                'description' => $desc,
                'transaction_date' => $transactionDate,
            ]);
            $inserted++;

            if ($due > self::MONEY_EPS) {
                AccountTransaction::create([
                    'shop_id' => $purchase->shop_id,
                    'account_type' => AccountTransaction::ACCOUNT_TYPE_SUPPLIER,
                    'account_ref_id' => $purchase->supplier_id,
                    'direction' => AccountTransaction::DIRECTION_CREDIT,
                    'amount' => $due,
                    'source_type' => AccountTransaction::SOURCE_PURCHASE,
                    'source_id' => $purchase->id,
                    'description' => $desc,
                    'transaction_date' => $transactionDate,
                ]);
                $inserted++;
            }

            if ($paid > self::MONEY_EPS) {
                [$cashBankType, $cashBankRefId] = $this->resolveCashBankAccount($purchase);
                AccountTransaction::create([
                    'shop_id' => $purchase->shop_id,
                    'account_type' => $cashBankType,
                    'account_ref_id' => $cashBankRefId,
                    'direction' => AccountTransaction::DIRECTION_CREDIT,
                    'amount' => $paid,
                    'source_type' => AccountTransaction::SOURCE_PURCHASE,
                    'source_id' => $purchase->id,
                    'description' => 'Inventory purchase',
                    'transaction_date' => $transactionDate,
                ]);
                $inserted++;
            }
        });

        $line = "SUCCESS: purchase_id={$purchaseId} {$inserted} entries inserted";
        $this->info($line);
        Log::info($line);
    }

    private function classifyPurchaseType(float $paid, float $total): ?string
    {
        if ($this->nearlyZero($paid)) {
            return 'credit';
        }
        if ($this->nearlyEquals($paid, $total)) {
            return 'paid';
        }
        if ($paid > self::MONEY_EPS && $paid < $total - self::MONEY_EPS) {
            return 'partial';
        }

        return null;
    }

    private function nearlyZero(float $x): bool
    {
        return abs($x) < self::MONEY_EPS;
    }

    private function nearlyEquals(float $a, float $b): bool
    {
        return abs($a - $b) < self::MONEY_EPS;
    }

    private function countPlannedEntries(float $due, float $paid): int
    {
        $n = 1;
        if ($due > self::MONEY_EPS) {
            $n++;
        }
        if ($paid > self::MONEY_EPS) {
            $n++;
        }

        return $n;
    }

    /**
     * @return array{0: string, 1: int|null}
     */
    private function resolveCashBankAccount(Purchase $purchase): array
    {
        $firstPaidLog = PurchasePaymentLog::query()
            ->where('purchase_id', $purchase->id)
            ->whereNull('deleted_at')
            ->where('amount_paid', '>', 0)
            ->orderBy('id')
            ->first();

        if ($firstPaidLog) {
            $isBank = (int) $firstPaidLog->shop_bank_id > 0;
            if ($isBank) {
                return [AccountTransaction::ACCOUNT_TYPE_BANK, (int) $firstPaidLog->shop_bank_id];
            }

            return [AccountTransaction::ACCOUNT_TYPE_CASH, null];
        }

        $paymentStatus = strtolower((string) ($purchase->payment_status ?? ''));
        $isBank = in_array($paymentStatus, ['bank', 'cheque'], true);

        return $isBank
            ? [AccountTransaction::ACCOUNT_TYPE_BANK, null]
            : [AccountTransaction::ACCOUNT_TYPE_CASH, null];
    }
}
