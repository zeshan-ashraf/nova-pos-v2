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

    protected $description = 'Safely repair duplicates and missing legs in sale ledger. Dry run by default, use --fix to apply changes.';

    public function handle(): int
    {
        $orderId = $this->option('order_id');
        $fix = (bool) $this->option('fix');

        $sales = $orderId !== null && $orderId !== ''
            ? [(int)$orderId]
            : DB::table('account_transactions')
                ->where('source_type', 'sale')
                ->whereNotNull('source_id')
                ->distinct()
                ->pluck('source_id')
                ->map(fn($id) => (int)$id)
                ->unique()
                ->values()
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
            $this->warn("No transactions found for sale {$sale}");
            return;
        }

        // -------------------------
        // 1️⃣ Detect and remove duplicates (logical legs)
        // -------------------------
        $legs = [];

        foreach ($txns as $txn) {
            // Logical leg key: account_type + direction + amount
            // ignore account_ref_id and description differences
            $key = "{$txn->account_type}-{$txn->direction}-" . round((float)$txn->amount, 2);
            $legs[$key][] = $txn;
        }

        foreach ($legs as $key => $rows) {
            if (count($rows) <= 1) continue;

            // Sort by created_at to keep the earliest
            usort($rows, fn($a, $b) => strtotime($a->created_at) <=> strtotime($b->created_at));

            $keep = array_shift($rows);
            $deleteIds = array_map(fn($r) => (int)$r->id, $rows);

            $this->warn("Duplicate detected for leg: {$key}");
            $this->line("Keeping ID: {$keep->id}, Candidates for delete: " . implode(',', $deleteIds));

            if ($fix && count($deleteIds) > 0) {
                DB::table('account_transactions')
                    ->whereIn('id', $deleteIds)
                    ->update(['deleted_at' => $now, 'updated_at' => $now]);
                $this->info('Soft deleted duplicates: ' . implode(',', $deleteIds));
            }
        }

        if ($fix) {
            // Reload transactions after duplicates deletion
            $txns = DB::table('account_transactions')
                ->where('source_type', 'sale')
                ->where('source_id', $sale)
                ->whereNull('deleted_at')
                ->get();
        }

        // -------------------------
        // 2️⃣ Detect and repair missing legs
        // -------------------------
        $invoice = $txns->first(fn($t) => str_contains((string)($t->description ?? ''), 'Invoice INV-'));
        if (!$invoice) {
            $invoice = $txns->first(fn($t) => $t->account_type === 'sale' && $t->direction === 'credit');
        }
        if (!$invoice) {
            $this->warn("No invoice found for sale {$sale}, skipping missing leg repair.");
            $this->validateBalance($sale);
            $this->line('---------------------------------------------');
            return;
        }

        // Determine expected legs
        $expectedLegs = [];

        // 2a. Invoice credit leg
        $expectedLegs[] = [
            'account_type'   => 'sale',
            'account_ref_id' => null,
            'direction'      => 'credit',
            'amount'         => (float)$invoice->amount,
            'description'    => 'Invoice INV-' . $sale,
        ];

        // 2b. Customer debit leg
        $customerLegs = $txns->filter(fn($t) => in_array($t->account_type, ['customer']) && $t->direction === 'debit');
        if ($customerLegs->isEmpty()) {
            $expectedLegs[] = [
                'account_type'   => 'customer',
                'account_ref_id' => $invoice->account_ref_id,
                'direction'      => 'debit',
                'amount'         => (float)$invoice->amount,
                'description'    => 'Invoice INV-' . $sale,
            ];
        }

        // 2c. Payment legs (cash/bank/customer)
        $paymentLegs = $txns->filter(fn($t) => str_contains((string)($t->description ?? ''), 'Payment'));
        $paymentAggregated = [];
        foreach ($paymentLegs as $p) {
            $key = "{$p->account_type}-{$p->direction}-" . round((float)$p->amount, 2);
            if (!isset($paymentAggregated[$key])) {
                $paymentAggregated[$key] = $p;
            }
        }
        foreach ($paymentAggregated as $p) {
            $expectedLegs[] = [
                'account_type'   => $p->account_type,
                'account_ref_id' => $p->account_ref_id,
                'direction'      => $p->direction,
                'amount'         => (float)$p->amount,
                'description'    => $p->description,
            ];
        }

        // Insert missing legs
        foreach ($expectedLegs as $leg) {
            $exists = $txns->contains(fn($t) =>
                $t->account_type === $leg['account_type']
                && $t->direction === $leg['direction']
                && abs((float)$t->amount - $leg['amount']) < 0.01
            );

            if ($exists) continue;

            if (!$fix) {
                $this->warn('Missing leg (use --fix to insert): ' . json_encode($leg));
                continue;
            }

            $transactionDate = Carbon::parse($invoice->transaction_date)->toDateString();

            DB::table('account_transactions')->insert([
                'shop_id'           => $invoice->shop_id,
                'account_type'      => $leg['account_type'],
                'account_ref_id'    => $leg['account_ref_id'],
                'direction'         => $leg['direction'],
                'amount'            => round($leg['amount'], 2),
                'source_type'       => 'sale',
                'source_id'         => $sale,
                'description'       => $leg['description'],
                'transaction_date'  => $transactionDate,
                'created_at'        => $now,
                'updated_at'        => $now,
            ]);
            $this->info('Inserted missing leg: ' . json_encode($leg));
        }

        // -------------------------
        // 3️⃣ Validate balance
        // -------------------------
        $this->validateBalance($sale);
        $this->line('---------------------------------------------');
    }

    private function validateBalance(int $sale): void
    {
        $balance = DB::table('account_transactions')
            ->where('source_type', 'sale')
            ->where('source_id', $sale)
            ->whereNull('deleted_at')
            ->selectRaw(
                "SUM(CASE WHEN direction = 'debit' THEN amount ELSE 0 END) AS debit, " .
                "SUM(CASE WHEN direction = 'credit' THEN amount ELSE 0 END) AS credit"
            )
            ->first();

        $debit = (float) ($balance->debit ?? 0);
        $credit = (float) ($balance->credit ?? 0);

        if (abs($debit - $credit) >= 0.01) {
            $this->error("Sale {$sale} still unbalanced! Debit=" . number_format($debit, 2) . ", Credit=" . number_format($credit, 2));
        } else {
            $this->info("Sale {$sale} balanced.");
        }
    }
}