<?php

namespace App\Console\Commands;

use App\Models\AccountTransaction;
use App\Models\Order;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

class RepairSaleMissingLegs extends Command
{
    protected $signature = 'ledger:repair-sale-missing
        {--shop= : Limit repair to a specific shop ID}
        {--dry-run : Only preview changes without updating DB}';

    protected $description = 'Fix sale transactions missing any leg and ensure source_id consistency';

    private int $totalFound = 0;
    private int $totalInserted = 0;
    private int $totalUpdated = 0;

    private const CHUNK_SIZE = 500;

    public function handle(): int
    {
        $shopId = $this->option('shop');
        $dryRun = (bool) $this->option('dry-run');

        if ($dryRun) {
            $this->warn('DRY RUN — no database changes will be applied.');
        }

        try {
            DB::transaction(function () use ($shopId, $dryRun) {
                $this->processSales($shopId, $dryRun);
            });
        } catch (Throwable $e) {
            $this->error('Repair failed: ' . $e->getMessage());
            if (!$dryRun) {
                $this->error('All changes rolled back.');
            }
            return 1;
        }

        $this->newLine();
        $this->info('--- Summary ---');
        $this->info("Total affected sale groups: {$this->totalFound}");
        $this->info("Total inserted missing legs: {$this->totalInserted}");
        $this->info("Total updated source_id rows: {$this->totalUpdated}");

        return 0;
    }

    private function processSales(?string $shopId, bool $dryRun): void
    {
        $query = AccountTransaction::query()
            ->where('source_type', AccountTransaction::SOURCE_SALE)
            ->whereNull('deleted_at')
            ->select('shop_id', 'description')
            ->groupBy('shop_id', 'description')
            ->havingRaw('COUNT(*) != 4')
            ->orderBy('shop_id');

        if ($shopId !== null && $shopId !== '') {
            $query->where('shop_id', (int) $shopId);
        }

        $query->chunk(self::CHUNK_SIZE, function ($groups) use ($dryRun) {
            foreach ($groups as $group) {
                $this->totalFound++;
                $this->repairGroup((int) $group->shop_id, $group->description ?? '', $dryRun);
            }
        });
    }

    private function repairGroup(int $shopId, string $description, bool $dryRun): void
    {
        $transactions = AccountTransaction::query()
            ->where('shop_id', $shopId)
            ->where('source_type', AccountTransaction::SOURCE_SALE)
            ->where('description', $description)
            ->whereNull('deleted_at')
            ->get();

        if ($transactions->isEmpty()) {
            return;
        }

        $orderId = $this->extractOrderId($transactions);

        if (!$orderId) {
            $this->warn("Could not determine order_id for description: {$description}");
            return;
        }

        $exists = $transactions->map(fn ($t) => $t->account_type . '-' . $t->direction)->values()->all();
        $legs = [
            'sale-credit',
            'customer-debit',
            'cash-debit',
            'customer-credit',
        ];
        $missingLegs = array_diff($legs, $exists);

        foreach ($missingLegs as $leg) {
            [$accountType, $direction] = explode('-', $leg);

            $row = [
                'shop_id' => $shopId,
                'account_type' => $accountType,
                'account_ref_id' => $accountType === 'customer' ? $this->getCustomerId($transactions) : null,
                'direction' => $direction,
                'amount' => $this->getAmount($transactions),
                'source_type' => AccountTransaction::SOURCE_SALE,
                'source_id' => $orderId,
                'description' => $description,
                'transaction_date' => $this->getTransactionDate($transactions),
            ];

            if ($dryRun) {
                $this->line('[DRY RUN] Would insert missing leg: ' . json_encode($row));
            } else {
                AccountTransaction::create($row);
                $this->totalInserted++;
            }
        }

        foreach ($transactions as $tx) {
            if ((int) $tx->source_id !== $orderId) {
                if ($dryRun) {
                    $this->line("[DRY RUN] Would update transaction ID {$tx->id}: source_id {$tx->source_id} → {$orderId}");
                } else {
                    $tx->source_id = $orderId;
                    $tx->save();
                    $this->totalUpdated++;
                }
            }
        }
    }

    private function extractOrderId($transactions): ?int
    {
        $shopId = $transactions->first()->shop_id ?? null;
        if ($shopId === null) {
            return null;
        }

        foreach ($transactions as $tx) {
            if (preg_match('/INV-\d+/', (string) $tx->description, $m)) {
                $invoiceNo = $m[0];
                $order = Order::query()
                    ->where('invoice_no', $invoiceNo)
                    ->where('shop_id', $shopId)
                    ->whereNull('deleted_at')
                    ->first();
                if ($order) {
                    return (int) $order->id;
                }
            }
        }
        return null;
    }

    private function getCustomerId($transactions): ?int
    {
        $customerTx = $transactions->firstWhere('account_type', 'customer');
        $refId = $customerTx?->account_ref_id;
        return $refId !== null ? (int) $refId : null;
    }

    private function getAmount($transactions): float
    {
        $first = $transactions->first();
        return $first ? (float) $first->amount : 0.0;
    }

    private function getTransactionDate($transactions)
    {
        $first = $transactions->first();
        $date = $first?->transaction_date;
        return $date ?? now()->toDateString();
    }
}
