<?php

namespace App\Console\Commands;

use App\Models\Order;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class RepairSaleLedger extends Command
{
    protected $signature = 'ledger:repair-sale
        {--order_id= : Process only this order/sale ID}
        {--fix : Apply fixes (soft delete duplicates, insert missing legs)}';

    protected $description = 'Repair duplicates and missing legs in sale ledger safely';

    public function handle(): int
    {
        $orderId = $this->option('order_id');
        $fix = (bool) $this->option('fix');

        $saleIds = $orderId !== null && $orderId !== ''
            ? [(int) $orderId]
            : DB::table('account_transactions')
                ->where('source_type', 'sale')
                ->whereNotNull('source_id')
                ->distinct()
                ->pluck('source_id')
                ->map(fn ($id) => (int) $id)
                ->unique()
                ->values()
                ->all();

        if (empty($saleIds)) {
            $this->info('No sale ledger entries found.');
            return self::SUCCESS;
        }

        foreach ($saleIds as $saleId) {
            $this->processSale($saleId, $fix);
        }

        return self::SUCCESS;
    }

    private function processSale(int $saleId, bool $fix): void
    {
        $this->info("Processing Sale ID: {$saleId}");

        $txns = DB::table('account_transactions')
            ->where('source_type', 'sale')
            ->where('source_id', $saleId)
            ->whereNull('deleted_at')
            ->get();

        if ($txns->isEmpty()) {
            $this->warn("No transactions found for sale {$saleId}");
            return;
        }

        $now = Carbon::now()->toDateTimeString();

        // 1) Detect duplicates per logical leg (keep oldest)
        $legs = [];
        foreach ($txns as $txn) {
            $refId = $txn->account_ref_id ?? '';
            $desc = (string) ($txn->description ?? '');
            $key = "{$txn->account_type}-{$refId}-{$txn->direction}-{$txn->amount}-{$desc}";
            $legs[$key][] = $txn;
        }

        foreach ($legs as $key => $rows) {
            if (count($rows) <= 1) {
                continue;
            }
            $this->warn("Duplicate detected for leg: {$key}");
            $keep = array_shift($rows);
            $deleteIds = array_map(fn ($r) => (int) $r->id, $rows);

            if ($fix && count($deleteIds) > 0) {
                DB::table('account_transactions')
                    ->whereIn('id', $deleteIds)
                    ->update([
                        'deleted_at' => $now,
                        'updated_at' => $now,
                    ]);
                $this->info('  Soft deleted duplicate IDs: ' . implode(', ', $deleteIds));
            }
        }

        // Re-fetch after duplicate cleanup so missing-leg logic sees current state
        if ($fix) {
            $txns = DB::table('account_transactions')
                ->where('source_type', 'sale')
                ->where('source_id', $saleId)
                ->whereNull('deleted_at')
                ->get();
        }

        // 2) Detect missing legs using invoice (sale credit) leg and optional Order
        $invoiceLeg = $txns->first(fn ($t) => $t->account_type === 'sale' && $t->direction === 'credit');
        $order = Order::find($saleId);

        if ($invoiceLeg !== null) {
            $shopId = (int) $invoiceLeg->shop_id;
            $transactionDate = \Carbon\Carbon::parse($invoiceLeg->transaction_date)->toDateString();
            $invoiceAmount = (float) $invoiceLeg->amount;
            $invoiceDesc = 'Invoice ' . ($order && $order->invoice_no ? $order->invoice_no : (string) $saleId);
            $paymentDesc = 'Payment – Invoice ' . ($order && $order->invoice_no ? $order->invoice_no : (string) $saleId);

            $expectedLegs = [
                [
                    'account_type'  => 'sale',
                    'account_ref_id'=> null,
                    'direction'    => 'credit',
                    'amount'       => $invoiceAmount,
                    'description'  => $invoiceDesc,
                ],
            ];

            $firstPayment = $txns->first(fn ($t) => str_contains((string) ($t->description ?? ''), 'Payment'));
            $counterAccountType = $firstPayment ? $firstPayment->account_type : 'customer';
            $customerId = $order && $order->customer_id ? (int) $order->customer_id : null;
            $expectedLegs[] = [
                'account_type'   => $counterAccountType,
                'account_ref_id' => $counterAccountType === 'customer' ? $customerId : ($firstPayment ? $firstPayment->account_ref_id : null),
                'direction'      => 'debit',
                'amount'         => $invoiceAmount,
                'description'    => $paymentDesc,
            ];

            foreach ($expectedLegs as $leg) {
                $exists = $txns->contains(fn ($t) =>
                    $t->account_type === $leg['account_type']
                    && $t->direction === $leg['direction']
                    && abs((float) $t->amount - (float) $leg['amount']) < 0.01
                    && ($leg['account_ref_id'] === null || (int) ($t->account_ref_id ?? 0) === (int) $leg['account_ref_id'])
                );
                if ($exists) {
                    continue;
                }
                if (!$fix) {
                    $this->warn('  Missing leg (use --fix to insert): ' . json_encode($leg));
                    continue;
                }
                DB::table('account_transactions')->insert([
                    'shop_id'          => $shopId,
                    'account_type'     => $leg['account_type'],
                    'account_ref_id'   => $leg['account_ref_id'],
                    'direction'        => $leg['direction'],
                    'amount'          => round((float) $leg['amount'], 2),
                    'source_type'     => 'sale',
                    'source_id'       => $saleId,
                    'description'     => $leg['description'],
                    'transaction_date'=> $transactionDate,
                    'created_at'      => $now,
                    'updated_at'      => $now,
                ]);
                $this->info('  Inserted missing leg: ' . json_encode($leg));
            }
        }

        // 3) Validate balance (debit = credit)
        $balance = DB::table('account_transactions')
            ->where('source_type', 'sale')
            ->where('source_id', $saleId)
            ->whereNull('deleted_at')
            ->selectRaw(
                "SUM(CASE WHEN direction = 'debit' THEN amount ELSE 0 END) AS debit, " .
                "SUM(CASE WHEN direction = 'credit' THEN amount ELSE 0 END) AS credit"
            )
            ->first();

        $debit = (float) ($balance->debit ?? 0);
        $credit = (float) ($balance->credit ?? 0);

        if (abs($debit - $credit) >= 0.01) {
            $this->error("  Sale {$saleId} unbalanced. Debit=" . number_format($debit, 2) . ", Credit=" . number_format($credit, 2));
        } else {
            $this->info("  Sale {$saleId} balanced.");
        }
    }
}
