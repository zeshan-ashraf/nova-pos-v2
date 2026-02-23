<?php

namespace App\Console\Commands;

use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class AuditDuplicateLedger extends Command
{
    protected $signature = 'ledger:audit-duplicates 
                            {--fix : Apply soft delete to duplicate entries}';

    protected $description = 'Audit and optionally soft delete duplicate ledger entries safely (account_transactions)';

    public function handle(): int
    {
        $this->info('Scanning for duplicate ledger entries...');

        $duplicates = DB::table('account_transactions')
            ->select(
                'shop_id',
                'source_type',
                'source_id',
                'account_type',
                'account_ref_id',
                'direction',
                'amount',
                'transaction_date',
                DB::raw('COUNT(*) as total'),
                DB::raw('GROUP_CONCAT(id ORDER BY id ASC) as ids')
            )
            ->whereNull('deleted_at')
            ->groupBy(
                'shop_id',
                'source_type',
                'source_id',
                'account_type',
                'account_ref_id',
                'direction',
                'amount',
                'transaction_date'
            )
            ->having('total', '>', 1)
            ->get();

        if ($duplicates->isEmpty()) {
            $this->info('No duplicates found.');
            return self::SUCCESS;
        }

        $fix = $this->option('fix');
        $now = Carbon::now()->toDateTimeString();

        foreach ($duplicates as $dup) {

            $ids = array_map('intval', explode(',', (string) $dup->ids));
            $keepId = array_shift($ids);
            $deleteIds = $ids;

            $this->warn("Duplicate group (shop={$dup->shop_id}, type={$dup->source_type}, id={$dup->source_id})");
            $this->line("  Keeping ID: {$keepId}");
            $this->line("  Candidate delete IDs: " . implode(', ', $deleteIds));

            // 🔎 Get full journal for this source
            $journal = DB::table('account_transactions')
                ->where('shop_id', $dup->shop_id)
                ->where('source_type', $dup->source_type)
                ->where('source_id', $dup->source_id)
                ->whereNull('deleted_at')
                ->get();

            $totalDebit = $journal->where('direction', 'debit')->sum('amount');
            $totalCredit = $journal->where('direction', 'credit')->sum('amount');

            // simulate deletion
            $journalAfter = $journal->reject(function ($row) use ($deleteIds) {
                return in_array($row->id, $deleteIds);
            });

            $newDebit = $journalAfter->where('direction', 'debit')->sum('amount');
            $newCredit = $journalAfter->where('direction', 'credit')->sum('amount');

            $this->line("  Before → Debit: {$totalDebit}, Credit: {$totalCredit}");
            $this->line("  After  → Debit: {$newDebit}, Credit: {$newCredit}");

            if ($newDebit != $newCredit) {
                $this->error("  ❌ Skipped (would break balance)");
                $this->line('-------------------------------------------');
                continue;
            }

            if ($fix && count($deleteIds) > 0) {
                DB::transaction(function () use ($deleteIds, $now) {
                    DB::table('account_transactions')
                        ->whereIn('id', $deleteIds)
                        ->update([
                            'deleted_at' => $now,
                            'updated_at' => $now,
                        ]);
                });

                $this->info("  ✅ Soft deleted safely.");
            } else {
                $this->info("  ✔ Safe to delete (dry run).");
            }

            $this->line('-------------------------------------------');
        }

        if (!$fix) {
            $this->info('Dry run completed. Use --fix to apply soft delete.');
        }

        return self::SUCCESS;
    }
}