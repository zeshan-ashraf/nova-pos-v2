<?php

namespace App\Console\Commands;

use App\Models\AccountTransaction;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\StockLog;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class RebuildPurchaseStockCommand extends Command
{
    protected $signature = 'stock:rebuild-purchase
                            {purchase_id : The purchase ID to rebuild}
                            {--dry-run : Preview only, do not persist changes}';

    protected $description = 'Rebuild one purchase totals, stock logs, product stock, and purchase account transaction safely.';

    public function handle(): int
    {
        $purchaseId = (int) $this->argument('purchase_id');
        $dryRun = (bool) $this->option('dry-run');

        if ($purchaseId <= 0) {
            $this->error('purchase_id must be a positive integer.');

            return Command::FAILURE;
        }

        $purchase = Purchase::withoutGlobalScopes()
            ->with(['purchaseDetails' => function ($q) {
                $q->whereNull('deleted_at');
            }])
            ->find($purchaseId);

        if (! $purchase) {
            $this->error("Purchase {$purchaseId} not found.");
            Log::warning('stock:rebuild-purchase purchase not found', ['purchase_id' => $purchaseId]);

            return Command::FAILURE;
        }

        $details = $purchase->purchaseDetails;
        if ($details->isEmpty()) {
            $this->error("Purchase {$purchaseId} has no active purchase_details.");
            Log::warning('stock:rebuild-purchase empty details', ['purchase_id' => $purchaseId]);

            return Command::FAILURE;
        }

        $oldTotal = (float) ($purchase->total ?? 0);
        $newTotal = $this->calculateSubtotalFromDetails($details->all());
        $detailTotalPayloads = $this->buildDetailTotalPayloads($details->all());
        $productIds = $details->pluck('product_id')->filter()->map(fn ($id) => (int) $id)->unique()->values()->all();

        $oldPurchaseLogCount = StockLog::withoutGlobalScopes()
            ->whereNull('deleted_at')
            ->where('source_type', 'purchase')
            ->where('source_id', (string) $purchase->id)
            ->count();

        $oldTxnCount = AccountTransaction::withoutGlobalScopes()
            ->whereNull('deleted_at')
            ->where('source_type', AccountTransaction::SOURCE_PURCHASE)
            ->where('source_id', $purchase->id)
            ->count();

        $newStockRows = $this->buildStockRows($purchase, $details->all());
        $stockPreview = $this->previewStockChanges($purchase, $productIds);
        $transactionPayload = $this->buildPurchaseTransactionPayload($purchase, $newTotal);

        if ($dryRun) {
            $newProductStocks = $this->simulateStocksAfterRebuild($purchase, $details->all(), $productIds);
            $this->warn('DRY RUN - no changes will be written.');
            $this->outputSummary(
                $purchase->id,
                $oldTotal,
                $newTotal,
                $oldPurchaseLogCount,
                count($newStockRows),
                $stockPreview,
                $newProductStocks,
                $oldTxnCount,
                $transactionPayload !== null
            );

            return Command::SUCCESS;
        }

        try {
            DB::transaction(function () use (
                $purchase,
                $newTotal,
                $detailTotalPayloads,
                $newStockRows,
                $productIds,
                $transactionPayload,
                &$oldPurchaseLogCount,
                &$oldTxnCount
            ) {
                foreach ($detailTotalPayloads as $payload) {
                    DB::table('purchase_details')
                        ->where('id', $payload['id'])
                        ->whereNull('deleted_at')
                        ->update([
                            'total' => $payload['total'],
                            'updated_at' => now(),
                        ]);
                }

                $purchase->sub_total = $newTotal;
                $purchase->total = $newTotal;
                $purchase->save();

                $oldPurchaseLogCount = StockLog::withoutGlobalScopes()
                    ->whereNull('deleted_at')
                    ->where('source_type', 'purchase')
                    ->where('source_id', (string) $purchase->id)
                    ->update([
                        'deleted_at' => now(),
                        'updated_at' => now(),
                    ]);

                foreach ($newStockRows as $row) {
                    StockLog::create($row);
                }

                $stocks = $this->calculateCurrentStocksForProducts($purchase, $productIds);
                foreach ($stocks as $productId => $stock) {
                    Product::withoutGlobalScopes()
                        ->whereKey($productId)
                        ->where('shop_id', $purchase->shop_id)
                        ->update(['product_store' => $stock]);
                }

                $oldTxnCount = AccountTransaction::withoutGlobalScopes()
                    ->whereNull('deleted_at')
                    ->where('source_type', AccountTransaction::SOURCE_PURCHASE)
                    ->where('source_id', $purchase->id)
                    ->update([
                        'deleted_at' => now(),
                        'updated_at' => now(),
                    ]);

                if ($transactionPayload !== null) {
                    AccountTransaction::create($transactionPayload);
                }
            });
        } catch (\Throwable $e) {
            Log::error('stock:rebuild-purchase failed', [
                'purchase_id' => $purchase->id,
                'error' => $e->getMessage(),
            ]);
            $this->error('Rebuild failed: '.$e->getMessage());

            return Command::FAILURE;
        }

        $finalStocks = $this->calculateCurrentStocksForProducts($purchase, $productIds);

        $this->outputSummary(
            $purchase->id,
            $oldTotal,
            $newTotal,
            $oldPurchaseLogCount,
            count($newStockRows),
            $stockPreview,
            $finalStocks,
            $oldTxnCount,
            $transactionPayload !== null
        );

        Log::info('stock:rebuild-purchase success', [
            'purchase_id' => $purchase->id,
            'old_total' => $oldTotal,
            'new_total' => $newTotal,
            'stock_logs_deleted' => $oldPurchaseLogCount,
            'stock_logs_inserted' => count($newStockRows),
            'products_updated' => count($productIds),
            'account_transactions_deleted' => $oldTxnCount,
            'account_transaction_inserted' => $transactionPayload !== null ? 1 : 0,
        ]);

        return Command::SUCCESS;
    }

    private function calculateSubtotalFromDetails(array $details): float
    {
        $subtotal = 0.0;
        foreach ($details as $detail) {
            $qty = (float) ($detail->quantity ?? 0);
            $cost = $this->resolveCostPerUnit($detail);
            $subtotal += ($qty * $cost);
        }

        return round($subtotal, 2);
    }

    private function resolveCostPerUnit($detail): float
    {
        $costPerUnit = data_get($detail, 'cost_per_unit');
        if ($costPerUnit !== null && (float) $costPerUnit > 0) {
            return (float) $costPerUnit;
        }

        return (float) ($detail->unitcost ?? 0);
    }

    /**
     * Recalculate each purchase_details.total using qty * resolved cost.
     *
     * @param  array<int, \App\Models\PurchaseDetail>  $details
     * @return array<int, array{id:int,total:float}>
     */
    private function buildDetailTotalPayloads(array $details): array
    {
        $payloads = [];
        foreach ($details as $detail) {
            $payloads[] = [
                'id' => (int) $detail->id,
                'total' => round(((float) ($detail->quantity ?? 0)) * $this->resolveCostPerUnit($detail), 2),
            ];
        }

        return $payloads;
    }

    private function buildStockRows(Purchase $purchase, array $details): array
    {
        $rows = [];
        $adjustmentDate = $purchase->purchase_date
            ? Carbon::parse($purchase->purchase_date)->format('Y-m-d H:i:s')
            : now()->format('Y-m-d H:i:s');
        $createdAt = $purchase->created_at
            ? $purchase->created_at->format('Y-m-d H:i:s')
            : now()->format('Y-m-d H:i:s');

        foreach ($details as $detail) {
            $qty = $this->ledgerQtyMagnitude($detail->quantity ?? 0);
            $rows[] = [
                'shop_id' => $purchase->shop_id,
                'product_id' => (int) $detail->product_id,
                'qty' => $qty,
                'stock_qty' => $qty,
                'direction' => 'in',
                'source_type' => 'purchase',
                'source_id' => (string) $purchase->id,
                'adjustment_date' => $adjustmentDate,
                'supplier_id' => $purchase->supplier_id ? (int) $purchase->supplier_id : null,
                'cost_per_unit' => $this->resolveCostPerUnit($detail),
                'price' => (float) ($detail->unitcost ?? 0),
                'created_at' => $createdAt,
                'updated_at' => now()->format('Y-m-d H:i:s'),
            ];
        }

        return $rows;
    }

    /**
     * Pure in-memory simulation: strip this purchase's purchase stock_logs, append lines from current details,
     * then aggregate with the same path as {@see calculateCurrentStocksForProducts}.
     *
     * @param  array<int, \App\Models\PurchaseDetail>  $details
     * @param  array<int>  $productIds
     * @return array<int, int>
     */
    private function simulateStocksAfterRebuild(Purchase $purchase, array $details, array $productIds): array
    {
        if ($productIds === []) {
            return [];
        }

        $rows = $this->getStockRowsForProducts($purchase->shop_id, $productIds);

        $rows = $rows->filter(function ($row) use ($purchase) {
            return ! (
                $row->source_type === 'purchase'
                && (string) $row->source_id === (string) $purchase->id
            );
        })->values();

        foreach ($details as $detail) {
            $rows->push((object) [
                'product_id' => (int) $detail->product_id,
                'source_type' => 'purchase',
                'qty' => $this->ledgerQtyMagnitude($detail->quantity ?? 0),
            ]);
        }

        return $this->aggregateLedgerStocksForProducts($rows, $productIds);
    }

    private function calculateCurrentStocksForProducts(Purchase $purchase, array $productIds): array
    {
        if ($productIds === []) {
            return [];
        }

        $rows = $this->getStockRowsForProducts($purchase->shop_id, $productIds);

        return $this->aggregateLedgerStocksForProducts($rows, $productIds);
    }

    /**
     * Single base dataset for ledger stock: active rows scoped by shop and purchase line products only.
     * source_id is selected for simulation (strip/replace one purchase); aggregation uses only qty + source_type.
     *
     * @param  array<int>  $productIds
     */
    private function getStockRowsForProducts(?int $shopId, array $productIds): Collection
    {
        if ($productIds === []) {
            return collect();
        }

        return DB::table('stock_logs')
            ->select('product_id', 'source_type', 'qty', 'source_id')
            ->whereNull('deleted_at')
            ->where('shop_id', $shopId)
            ->whereIn('product_id', $productIds)
            ->get();
    }

    /**
     * Deterministic ledger sum: for each row, stock += abs(qty) * sign(source_type). Ordering-independent.
     * Uses only qty and source_type; ignores stock_qty, direction, and source_id.
     *
     * @param  iterable<int, object>  $rows
     * @param  array<int>  $productIds
     * @return array<int, int>
     */
    private function aggregateLedgerStocksForProducts(iterable $rows, array $productIds): array
    {
        $stocks = [];
        foreach ($productIds as $productId) {
            $stocks[$productId] = 0;
        }

        foreach ($rows as $row) {
            $productId = (int) $row->product_id;
            if (! array_key_exists($productId, $stocks)) {
                continue;
            }
            $qty = $this->ledgerQtyMagnitude($row->qty ?? 0);
            $sign = $this->stockSignBySourceType((string) ($row->source_type ?? ''));
            $stocks[$productId] += $qty * $sign;
        }

        return $stocks;
    }

    /**
     * Ledger invariant: qty magnitude is abs(int); sign comes only from source_type via {@see stockSignBySourceType}.
     */
    private function ledgerQtyMagnitude(mixed $qty): int
    {
        return abs((int) ($qty ?? 0));
    }

    /**
     * Single source of truth for stock movement sign from source_type (positive qty × sign).
     * purchase, sale_return => IN (+); sale, purchase_return, mother_sale => OUT (-).
     */
    private function stockSignBySourceType(string $sourceType): int
    {
        return match ($sourceType) {
            'purchase', 'sale_return' => 1,
            'sale', 'purchase_return', 'mother_sale' => -1,
            default => 0,
        };
    }

    private function previewStockChanges(Purchase $purchase, array $productIds): array
    {
        if ($productIds === []) {
            return [];
        }

        $products = Product::withoutGlobalScopes()
            ->where('shop_id', $purchase->shop_id)
            ->whereIn('id', $productIds)
            ->get(['id', 'product_store']);

        $preview = [];
        foreach ($products as $product) {
            $preview[(int) $product->id] = (int) ($product->product_store ?? 0);
        }

        return $preview;
    }

    private function buildPurchaseTransactionPayload(Purchase $purchase, float $newTotal): ?array
    {
        if (! $purchase->shop_id || ! $purchase->supplier_id) {
            return null;
        }

        $transactionDate = $purchase->purchase_date
            ? Carbon::parse($purchase->purchase_date)->toDateString()
            : now()->toDateString();

        return [
            'shop_id' => $purchase->shop_id,
            'account_type' => AccountTransaction::ACCOUNT_TYPE_SUPPLIER,
            'account_ref_id' => (int) $purchase->supplier_id,
            // In this ledger, a purchase increases supplier payable => credit.
            'direction' => AccountTransaction::DIRECTION_CREDIT,
            'amount' => $newTotal,
            'source_type' => AccountTransaction::SOURCE_PURCHASE,
            'source_id' => $purchase->id,
            'description' => 'Rebuilt purchase '.($purchase->purchase_no ?? (string) $purchase->id),
            'transaction_date' => $transactionDate,
        ];
    }

    private function outputSummary(
        int $purchaseId,
        float $oldTotal,
        float $newTotal,
        int $deletedLogs,
        int $insertedLogs,
        array $oldProductStocks,
        array $newProductStocks,
        int $deletedTransactions,
        bool $insertedTransaction
    ): void {
        $this->info("purchase_id: {$purchaseId}");
        $this->line('total: '.number_format($oldTotal, 2).' -> '.number_format($newTotal, 2));
        $this->line("stock_logs: deleted={$deletedLogs}, inserted={$insertedLogs}");
        $this->line('products_updated: '.count($newProductStocks));
        $this->line('account_transaction: deleted='.$deletedTransactions.', inserted='.($insertedTransaction ? 1 : 0));

        if ($oldProductStocks !== []) {
            $this->line('');
            $this->line('Stock changes (product_id: old -> new):');
            foreach ($newProductStocks as $productId => $newStock) {
                $oldStock = $oldProductStocks[$productId] ?? 0;
                $this->line(" - {$productId}: {$oldStock} -> {$newStock}");
            }
        }

        if (! $insertedTransaction) {
            $this->warn('No new account transaction inserted (missing supplier_id or shop_id).');
        }
    }
}
