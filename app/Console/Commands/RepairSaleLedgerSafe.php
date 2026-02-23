<?php

namespace App\Console\Commands;

use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class RepairSaleLedgerSafe extends Command
{
    protected $signature = 'ledger:repair-sale-safe
        {--order_id= : Process only this order/sale ID}
        {--fix : Apply changes; default is dry run}';

    protected $description = 'Safely repair duplicates and missing legs in sale ledger.';

    public function handle(): int
    {
        $orderId = $this->option('order_id');
        $fix = (bool) $this->option('fix');

        $sales = $orderId
            ? [(int)$orderId]
            : DB::table('account_transactions')
                ->where('source_type', 'sale')
                ->distinct()
                ->pluck('source_id')
                ->map(fn($id) => (int)$id)
                ->all();

        $this->info('Total sales to process: ' . count($sales));
        $now = Carbon::now()->toDateTimeString();

        foreach ($sales as $sale) {
            $this->processSale($sale, $fix, $now);
        }

        return self::SUCCESS;
    }

    private function processSale(int $sale, bool $fix, string $now): void
    {
        $this->info("Processing Sale ID: {$sale}");

        $txns = DB::table('account_transactions')
            ->where('source_type', 'sale')
            ->where('source_id', $sale)
            ->whereNull('deleted_at')
            ->get();

        if ($txns->isEmpty()) {
            $this->warn("No transactions found.");
            return;
        }

        /*
        =====================================
        1️⃣ REMOVE DUPLICATES (Logical Leg)
        =====================================
        */

        $grouped = [];

        foreach ($txns as $txn) {
            $key = "{$txn->account_type}-{$txn->direction}-" . round($txn->amount, 2);
            $grouped[$key][] = $txn;
        }

        foreach ($grouped as $key => $rows) {

            if (count($rows) <= 1) continue;

            usort($rows, fn($a,$b) => strtotime($a->created_at) <=> strtotime($b->created_at));

            $keep = array_shift($rows);
            $deleteIds = array_map(fn($r) => $r->id, $rows);

            $this->warn("Duplicate detected: {$key}");
            $this->line("Keeping {$keep->id}, deleting: " . implode(',', $deleteIds));

            if ($fix) {
                DB::table('account_transactions')
                    ->whereIn('id', $deleteIds)
                    ->update([
                        'deleted_at' => $now,
                        'updated_at' => $now
                    ]);
            }
        }

        if ($fix) {
            $txns = DB::table('account_transactions')
                ->where('source_type', 'sale')
                ->where('source_id', $sale)
                ->whereNull('deleted_at')
                ->get();
        }

        /*
        =====================================
        2️⃣ DETERMINE SALE TYPE
        =====================================
        */

        $invoice = $txns->first(fn($t) =>
            $t->account_type === 'sale' && $t->direction === 'credit'
        );

        if (!$invoice) {
            $this->warn("No invoice leg found.");
            $this->validateBalance($sale);
            return;
        }

        $invoiceAmount = (float)$invoice->amount;

        $cashDebitTotal = $txns
            ->where('direction','debit')
            ->whereIn('account_type',['cash','bank'])
            ->sum('amount');

        $customerDebitTotal = $txns
            ->where('account_type','customer')
            ->where('direction','debit')
            ->sum('amount');

        /*
        =====================================
        3️⃣ REPAIR ONLY WHEN LOGICALLY REQUIRED
        =====================================
        */

        // 🔹 Case A: Pure Walk-in Cash Sale
        if (abs($cashDebitTotal - $invoiceAmount) < 0.01 && $customerDebitTotal == 0) {
            $this->info("Walk-in cash sale detected. No customer leg required.");
            $this->validateBalance($sale);
            return;
        }

        // 🔹 Case B: Credit Sale (customer debit must exist)
        if ($customerDebitTotal == 0) {

            $this->warn("Missing customer debit leg.");

            if ($fix) {
                DB::table('account_transactions')->insert([
                    'shop_id' => $invoice->shop_id,
                    'account_type' => 'customer',
                    'account_ref_id' => $invoice->account_ref_id,
                    'direction' => 'debit',
                    'amount' => $invoiceAmount,
                    'source_type' => 'sale',
                    'source_id' => $sale,
                    'description' => 'Invoice INV-'.$sale,
                    'transaction_date' => $invoice->transaction_date,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);

                $this->info("Inserted missing customer debit leg.");
            }
        }

        /*
        =====================================
        4️⃣ FINAL BALANCE CHECK
        =====================================
        */

        $this->validateBalance($sale);
        $this->line('---------------------------------------------');
    }

    private function validateBalance(int $sale): void
    {
        $balance = DB::table('account_transactions')
            ->where('source_type','sale')
            ->where('source_id',$sale)
            ->whereNull('deleted_at')
            ->selectRaw("
                SUM(CASE WHEN direction='debit' THEN amount ELSE 0 END) as debit,
                SUM(CASE WHEN direction='credit' THEN amount ELSE 0 END) as credit
            ")
            ->first();

        $debit = (float)($balance->debit ?? 0);
        $credit = (float)($balance->credit ?? 0);

        if (abs($debit - $credit) >= 0.01) {
            $this->error("UNBALANCED → Debit={$debit}, Credit={$credit}");
        } else {
            $this->info("Sale {$sale} balanced.");
        }
    }
}