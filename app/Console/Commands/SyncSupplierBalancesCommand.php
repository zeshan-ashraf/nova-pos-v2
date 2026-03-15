<?php

namespace App\Console\Commands;

use App\Models\Supplier;
use App\Services\Ledger\LedgerBalanceService;
use Illuminate\Console\Command;

class SyncSupplierBalancesCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'supplier:sync-balances
        {--supplier= : Sync only this supplier ID}
        {--dry-run : Show what would be updated without making changes}
        {--force : Skip confirmation prompt}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sync suppliers.credit_amount from account_transactions (ledger). Shop-scoped. Positive = we owe supplier.';

    /**
     * Execute the console command.
     */
    public function handle(LedgerBalanceService $balanceService): int
    {
        $supplierId = $this->option('supplier');
        $dryRun = (bool) $this->option('dry-run');
        $force = (bool) $this->option('force');

        $query = Supplier::query()->orderBy('id');
        if ($supplierId) {
            $query->where('id', (int) $supplierId);
            if ($query->doesntExist()) {
                $this->error("Supplier with ID {$supplierId} not found.");
                return 1;
            }
        }

        $suppliers = $query->get();
        if ($suppliers->isEmpty()) {
            $this->warn('No suppliers found.');
            return 0;
        }

        $updates = [];
        foreach ($suppliers as $supplier) {
            $ledgerBalance = $balanceService->getSupplierBalance(
                (int) $supplier->id,
                $supplier->shop_id
            );
            $currentCreditAmount = (float) ($supplier->credit_amount ?? 0);

            if (abs($ledgerBalance - $currentCreditAmount) < 0.01) {
                continue;
            }

            $updates[] = [
                'id' => $supplier->id,
                'name' => $supplier->name,
                'shop_id' => $supplier->shop_id,
                'current' => $currentCreditAmount,
                'ledger' => $ledgerBalance,
            ];
        }

        if (empty($updates)) {
            $this->info('All supplier balances are already in sync.');
            return 0;
        }

        $this->info('Suppliers with balance mismatch: ' . count($updates));
        $this->table(
            ['ID', 'Name', 'Shop ID', 'Current credit_amount', 'Ledger balance'],
            array_map(fn ($u) => [
                $u['id'],
                $u['name'],
                $u['shop_id'] ?? '—',
                number_format($u['current'], 2),
                number_format($u['ledger'], 2),
            ], $updates)
        );

        if ($dryRun) {
            $this->newLine();
            $this->info('Dry run. No changes made. Run without --dry-run to apply.');
            return 0;
        }

        if (!$force && !$this->confirm('Apply these updates to suppliers.credit_amount?', false)) {
            $this->info('Aborted.');
            return 0;
        }

        $updated = 0;
        foreach ($updates as $u) {
            Supplier::where('id', $u['id'])->update(['credit_amount' => $u['ledger']]);
            $updated++;
        }

        $this->info("Updated {$updated} supplier(s).");
        return 0;
    }
}
