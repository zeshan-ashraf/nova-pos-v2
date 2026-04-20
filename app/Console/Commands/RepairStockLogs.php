<?php

namespace App\Console\Commands;

use App\Models\Order;
use App\Models\Purchase;
use App\Models\StockLog;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Repairs missing stock_logs for a single sale (Order) or purchase.
 *
 * Note: Sales are stored as {@see Order} with {@see Order::orderDetails()}, not a separate Sale model.
 * Rows follow ledger rules: positive qty + direction in/out (see {@see \App\Models\StockLog}).
 */
class RepairStockLogs extends Command
{
    protected $signature = 'stocklogs:repair
                            {--shop= : Shop ID}
                            {--id= : Order ID (sale) or Purchase ID}
                            {--type= : sale or purchase}
                            {--dry-run : Do not write to DB}';

    protected $description = 'Safely insert missing stock_logs for one sale (order) or purchase (no duplicates).';

    public function handle(): int
    {
        $shopId = $this->option('shop');
        $orderId = $this->option('id');
        $type = $this->option('type');
        $dryRun = (bool) $this->option('dry-run');

        if ($shopId === null || $shopId === '') {
            $this->error('The --shop option is required.');
            return Command::FAILURE;
        }
        if ($orderId === null || $orderId === '') {
            $this->error('The --id option is required.');
            return Command::FAILURE;
        }
        if ($type === null || $type === '') {
            $this->error('The --type option is required (sale or purchase).');
            return Command::FAILURE;
        }

        $type = strtolower((string) $type);
        if (! in_array($type, ['sale', 'purchase'], true)) {
            $this->error('--type must be "sale" or "purchase".');
            return Command::FAILURE;
        }

        $shopId = (int) $shopId;
        $orderId = (int) $orderId;

        if ($shopId < 1 || $orderId < 1) {
            $this->error('--shop and --id must be positive integers.');
            return Command::FAILURE;
        }

        Log::info('Stock log repair', [
            'shop_id' => $shopId,
            'order_id' => $orderId,
            'type' => $type,
            'dry_run' => $dryRun,
        ]);

        $existing = StockLog::withoutGlobalScopes()
            ->whereNull('deleted_at')
            ->where('source_type', $type)
            ->where('source_id', (string) $orderId)
            ->where('shop_id', $shopId)
            ->count();

        if ($existing > 0) {
            $this->warn("Stock logs already exist for shop_id={$shopId}, source_type={$type}, source_id={$orderId} ({$existing} row(s)). Aborting to avoid duplicates.");

            return Command::FAILURE;
        }

        if ($type === 'sale') {
            $order = Order::withoutGlobalScopes()
                ->with('orderDetails')
                ->where('shop_id', $shopId)
                ->findOrFail($orderId);

            $items = $order->orderDetails;
            $adjustmentDate = $order->order_date
                ? Carbon::parse($order->order_date)->format('Y-m-d H:i:s')
                : null;
            $createdAt = $order->created_at ? $order->created_at->format('Y-m-d H:i:s') : now()->format('Y-m-d H:i:s');

            $rows = [];
            foreach ($items as $item) {
                $qty = (int) $item->quantity;
                $signedQty = -abs($qty);

                $rows[] = [
                    'shop_id' => $shopId,
                    'product_id' => $item->product_id,
                    'supplier_id' => null,
                    'qty' => abs($qty),
                    'stock_qty' => -abs($qty),
                    'direction' => 'out',
                    'source_type' => 'sale',
                    'source_id' => (string) $order->id,
                    'price' => (float) ($item->unitcost ?? 0),
                    'cost_per_unit' => $item->cost_per_unit !== null ? (float) $item->cost_per_unit : null,
                    'reason' => null,
                    'adjustment_date' => $adjustmentDate,
                    'created_at' => $createdAt,
                    'updated_at' => now()->format('Y-m-d H:i:s'),
                    '_signed_qty' => $signedQty,
                    '_date' => $adjustmentDate,
                ];
            }
        } else {
            $order = Purchase::withoutGlobalScopes()
                ->with('purchaseDetails')
                ->where('shop_id', $shopId)
                ->findOrFail($orderId);

            $items = $order->purchaseDetails;
            $adjustmentDate = $order->purchase_date
                ? Carbon::parse($order->purchase_date)->format('Y-m-d H:i:s')
                : null;
            $createdAt = $order->created_at ? $order->created_at->format('Y-m-d H:i:s') : now()->format('Y-m-d H:i:s');

            $rows = [];
            foreach ($items as $item) {
                $qty = (int) $item->quantity;
                $signedQty = abs($qty);

                $rows[] = [
                    'shop_id' => $shopId,
                    'product_id' => $item->product_id,
                    'supplier_id' => $order->supplier_id,
                    'qty' => abs($qty),
                    'stock_qty' => abs($qty),
                    'direction' => 'in',
                    'source_type' => 'purchase',
                    'source_id' => (string) $order->id,
                    'price' => (float) ($item->unitcost ?? 0),
                    'cost_per_unit' => null,
                    'reason' => null,
                    'adjustment_date' => $adjustmentDate,
                    'created_at' => $createdAt,
                    'updated_at' => now()->format('Y-m-d H:i:s'),
                    '_signed_qty' => $signedQty,
                    '_date' => $adjustmentDate,
                ];
            }
        }

        if ($items->isEmpty()) {
            $this->warn('No line items found; nothing to insert.');

            return Command::FAILURE;
        }

        if ($dryRun) {
            $this->info('DRY RUN — no database writes.');

            $this->table(
                ['product_id', 'quantity', 'type', 'date'],
                collect($rows)->map(function (array $r) use ($type) {
                    return [
                        $r['product_id'],
                        $r['_signed_qty'],
                        $type,
                        $r['_date'] ?? '—',
                    ];
                })->all()
            );

            return Command::SUCCESS;
        }

        $insertRows = collect($rows)->map(function (array $r) {
            unset($r['_signed_qty'], $r['_date']);

            return $r;
        })->all();

        try {
            DB::beginTransaction();
            foreach ($insertRows as $row) {
                StockLog::create($row);
            }
            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            $this->error($e->getMessage());

            return Command::FAILURE;
        }

        $this->info('Stock logs repaired successfully.');

        return Command::SUCCESS;
    }
}
