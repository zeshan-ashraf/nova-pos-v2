<?php

namespace App\Console\Commands;

use App\Services\OrderRestoreService;
use Illuminate\Console\Command;
use RuntimeException;

class RestoreOrderCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'order:restore
        {order_id : The ID of the deleted order to restore}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Restore a soft-deleted order and all its related entries (order_details, payment_logs, account_transactions, stock_logs). Re-applies stock and syncs customer balance. Also restores linked child purchase if any.';

    /**
     * Execute the console command.
     */
    public function handle(OrderRestoreService $restoreService): int
    {
        $orderId = (int) $this->argument('order_id');

        if ($orderId <= 0) {
            $this->error('Order ID must be a positive integer.');
            return self::FAILURE;
        }

        try {
            $restoreService->restoreOrder($orderId);
            $this->info("Order {$orderId} and all related entries have been restored successfully.");
            return self::SUCCESS;
        } catch (RuntimeException $e) {
            $this->error($e->getMessage());
            return self::FAILURE;
        } catch (\Throwable $e) {
            $this->error('Restore failed: ' . $e->getMessage());
            if ($this->output->isVerbose()) {
                $this->newLine();
                $this->line($e->getTraceAsString());
            }
            return self::FAILURE;
        }
    }
}
