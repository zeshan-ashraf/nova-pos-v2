<?php

namespace App\Console\Commands;

use App\Models\AccountTransaction;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class RepairExpenseLegs extends Command
{
    protected $signature = 'ledger:repair-expenses
        {--shop= : Limit repair to a specific shop ID}
        {--dry-run : Do not actually insert, only report what would be inserted}';

    protected $description = 'Repair missing expense legs (credit side) in account_transactions';

    private int $totalMissing = 0;
    private int $totalInserted = 0;

    public function handle(): int
    {
        $shopId = $this->option('shop');
        $dryRun = (bool) $this->option('dry-run');

        if ($dryRun) {
            $this->warn('DRY RUN — no changes will be made.');
        }

        $run = function () use ($shopId, $dryRun) {
            $query = AccountTransaction::query()
                ->where('source_type', AccountTransaction::SOURCE_EXPENSE)
                ->whereNull('deleted_at')
                ->where('direction', AccountTransaction::DIRECTION_DEBIT);

            if ($shopId !== null && $shopId !== '') {
                $query->where('shop_id', (int) $shopId);
            }

            $query->chunkById(500, function ($rows) use ($dryRun) {
                foreach ($rows as $tx) {
                    $exists = AccountTransaction::query()
                        ->where('shop_id', $tx->shop_id)
                        ->where('source_type', AccountTransaction::SOURCE_EXPENSE)
                        ->where('source_id', $tx->source_id)
                        ->where('direction', AccountTransaction::DIRECTION_CREDIT)
                        ->whereNull('deleted_at')
                        ->exists();

                    if (!$exists) {
                        $this->totalMissing++;
                        $this->line("Missing credit leg for expense source_id={$tx->source_id}, shop_id={$tx->shop_id}, amount={$tx->amount} (transaction_id={$tx->id})");

                        if (!$dryRun) {
                            AccountTransaction::create([
                                'shop_id' => $tx->shop_id,
                                'account_type' => AccountTransaction::ACCOUNT_TYPE_CASH,
                                'account_ref_id' => null,
                                'direction' => AccountTransaction::DIRECTION_CREDIT,
                                'amount' => $tx->amount,
                                'source_type' => AccountTransaction::SOURCE_EXPENSE,
                                'source_id' => $tx->source_id,
                                'transaction_date' => $tx->transaction_date,
                                'description' => 'Auto-repaired missing expense leg',
                            ]);
                            $this->totalInserted++;
                        }
                    }
                }
            });
        };

        if ($dryRun) {
            $run();
        } else {
            DB::transaction($run);
        }

        $this->newLine();
        $this->info('--- Summary ---');
        $this->info("Total missing expense legs found: {$this->totalMissing}");
        if (!$dryRun) {
            $this->info("Total expense legs inserted: {$this->totalInserted}");
        } else {
            $this->info('DRY RUN — no inserts performed.');
        }

        return 0;
    }
}
