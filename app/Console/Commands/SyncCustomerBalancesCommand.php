<?php

namespace App\Console\Commands;

use App\Models\Customer;
use App\Services\Ledger\LedgerBalanceService;
use Illuminate\Console\Command;

class SyncCustomerBalancesCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'customer:sync-balances
        {--customer= : Sync only this customer ID}
        {--dry-run : Show what would be updated without making changes}
        {--force : Skip confirmation prompt}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sync customers.credit_amount from account_transactions (ledger). Shop-scoped. Negative = advance.';

    /**
     * Execute the console command.
     */
    public function handle(LedgerBalanceService $balanceService): int
    {
        $customerId = $this->option('customer');
        $dryRun = (bool) $this->option('dry-run');
        $force = (bool) $this->option('force');

        $query = Customer::query()->orderBy('id');
        if ($customerId) {
            $query->where('id', (int) $customerId);
            if ($query->doesntExist()) {
                $this->error("Customer with ID {$customerId} not found.");
                return 1;
            }
        }

        $customers = $query->get();
        if ($customers->isEmpty()) {
            $this->warn('No customers found.');
            return 0;
        }

        $updates = [];
        foreach ($customers as $customer) {
            $ledgerBalance = $balanceService->getCustomerBalance(
                (int) $customer->id,
                $customer->shop_id
            );
            $currentCreditAmount = (float) ($customer->credit_amount ?? 0);

            if (abs($ledgerBalance - $currentCreditAmount) < 0.01) {
                continue;
            }

            $updates[] = [
                'id' => $customer->id,
                'name' => $customer->shopname ?: $customer->name,
                'shop_id' => $customer->shop_id,
                'current' => $currentCreditAmount,
                'ledger' => $ledgerBalance,
            ];
        }

        if (empty($updates)) {
            $this->info('All customer balances are already in sync.');
            return 0;
        }

        $this->info('Customers with balance mismatch: ' . count($updates));
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

        if (!$force && !$this->confirm('Apply these updates to customers.credit_amount?', false)) {
            $this->info('Aborted.');
            return 0;
        }

        $updated = 0;
        foreach ($updates as $u) {
            Customer::where('id', $u['id'])->update(['credit_amount' => $u['ledger']]);
            $updated++;
        }

        $this->info("Updated {$updated} customer(s).");
        return 0;
    }
}
