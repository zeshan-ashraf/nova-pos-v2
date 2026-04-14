<?php

namespace App\Console\Commands;

use App\Models\Purchase;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class BackfillPurchaseSourceSaleId extends Command
{
    /**
     * We default to safe behavior by requiring explicit non-dry runs.
     */
    protected $signature = 'purchases:backfill-source-sale-id
                            {--dry-run : Show what would be updated without writing}
                            {--limit=0 : Limit number of candidate purchases (0 = no limit)}';

    protected $description = 'Backfill purchases.source_sale_id for child purchases from mother shop id 1: supplier.mother_shop_id=1, then match sale by totals + line counts/qty/sums.';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $limit = max(0, (int) $this->option('limit'));

        $this->info('Starting source_sale_id backfill' . ($dryRun ? ' (DRY RUN)' : ''));

        // Only purchases whose supplier points at mother shop id = 1 (inter-branch child-side purchase)
        $candidatesQuery = Purchase::withoutGlobalScopes()
            ->whereNull('source_sale_id')
            ->whereNotNull('shop_id')
            ->whereNotNull('supplier_id')
            ->whereNotNull('purchase_date')
            ->whereExists(function ($q) {
                $q->select(DB::raw('1'))
                    ->from('suppliers')
                    ->whereColumn('suppliers.id', 'purchases.supplier_id')
                    ->where('suppliers.mother_shop_id', 1);
            })
            ->orderBy('id');

        if ($limit > 0) {
            $candidatesQuery->limit($limit);
        }

        $candidates = $candidatesQuery->get([
            'id',
            'purchase_no',
            'supplier_id',
            'shop_id',
            'purchase_date',
            'total_products',
            'sub_total',
            'total',
        ]);

        if ($candidates->isEmpty()) {
            $this->warn('No candidate purchases found.');
            return self::SUCCESS;
        }

        $updated = 0;
        $wouldUpdate = 0;
        $skippedNoMatch = 0;
        $skippedAmbiguous = 0;
        $skippedUnsafe = 0;

        /** @var list<array{purchase_id:int, purchase_no:?string, sale_order_id:?int, sale_invoice_no:?string, result:string}> $dryRunReportRows */
        $dryRunReportRows = [];

        $bar = $this->output->createProgressBar($candidates->count());
        $bar->start();

        foreach ($candidates as $purchase) {
            $supplier = DB::table('suppliers')
                ->where('id', $purchase->supplier_id)
                ->where('mother_shop_id', 1)
                ->first(['id', 'mother_shop_id']);

            if (!$supplier) {
                $skippedUnsafe++;
                if ($dryRun) {
                    $dryRunReportRows[] = [
                        'purchase_id' => (int) $purchase->id,
                        'purchase_no' => $purchase->purchase_no,
                        'sale_order_id' => null,
                        'sale_invoice_no' => null,
                        'result' => 'Skipped (unsafe): supplier not linked to mother shop id 1',
                    ];
                }
                $bar->advance();
                continue;
            }

            $purchaseAgg = DB::table('purchase_details')
                ->where('purchase_id', $purchase->id)
                ->whereNull('deleted_at')
                ->selectRaw('COUNT(*) as line_count, COALESCE(SUM(quantity), 0) as qty_sum, COALESCE(SUM(total), 0) as lines_total')
                ->first();

            if (!$purchaseAgg || (int) $purchaseAgg->line_count === 0) {
                $skippedUnsafe++;
                if ($dryRun) {
                    $dryRunReportRows[] = [
                        'purchase_id' => (int) $purchase->id,
                        'purchase_no' => $purchase->purchase_no,
                        'sale_order_id' => null,
                        'sale_invoice_no' => null,
                        'result' => 'Skipped (unsafe): purchase has no line items',
                    ];
                }
                $bar->advance();
                continue;
            }

            // Match sale on mother shop (id 1): same date, header totals, line count, sum qty, sum line totals.
            $orderDetailsAgg = DB::table('order_details')
                ->whereNull('deleted_at')
                ->groupBy('order_id')
                ->selectRaw('order_id, COUNT(*) as line_count, COALESCE(SUM(quantity), 0) as qty_sum, COALESCE(SUM(total), 0) as lines_total');

            $matches = DB::table('orders')
                ->joinSub($orderDetailsAgg, 'oda', function ($join) {
                    $join->on('oda.order_id', '=', 'orders.id');
                })
                ->whereNull('orders.deleted_at')
                ->where('orders.shop_id', 1)
                ->whereDate('orders.order_date', $purchase->purchase_date)
                ->whereRaw('ABS(COALESCE(orders.total, 0) - ?) <= 0.01', [(float) $purchase->total])
                ->whereRaw('ABS(COALESCE(orders.sub_total, 0) - ?) <= 0.01', [(float) $purchase->sub_total])
                ->where('orders.total_products', (int) $purchase->total_products)
                ->where('oda.line_count', (int) $purchaseAgg->line_count)
                ->whereRaw('ABS(oda.qty_sum - ?) <= 0.01', [(float) $purchaseAgg->qty_sum])
                ->whereRaw('ABS(oda.lines_total - ?) <= 0.01', [(float) $purchaseAgg->lines_total])
                ->orderBy('orders.id')
                ->select(['orders.id', 'orders.invoice_no'])
                ->get();

            if ($matches->count() === 0) {
                $skippedNoMatch++;
                if ($dryRun) {
                    $dryRunReportRows[] = [
                        'purchase_id' => (int) $purchase->id,
                        'purchase_no' => $purchase->purchase_no,
                        'sale_order_id' => null,
                        'sale_invoice_no' => null,
                        'result' => 'No matching sale order (strict criteria)',
                    ];
                }
                $bar->advance();
                continue;
            }

            if ($matches->count() > 1) {
                $skippedAmbiguous++;
                if ($dryRun) {
                    $ids = $matches->pluck('id')->implode(', ');
                    $dryRunReportRows[] = [
                        'purchase_id' => (int) $purchase->id,
                        'purchase_no' => $purchase->purchase_no,
                        'sale_order_id' => null,
                        'sale_invoice_no' => null,
                        'result' => 'Ambiguous: multiple sale orders match (ids: ' . $ids . ')',
                    ];
                }
                $bar->advance();
                continue;
            }

            $matchedOrder = $matches->first();
            $orderId = (int) $matchedOrder->id;
            $saleInvoiceNo = $matchedOrder->invoice_no ?? null;

            // Safety: one source sale should not be linked to multiple purchases.
            $alreadyLinked = Purchase::withoutGlobalScopes()
                ->where('source_sale_id', $orderId)
                ->where('id', '!=', $purchase->id)
                ->exists();

            if ($alreadyLinked) {
                $skippedUnsafe++;
                if ($dryRun) {
                    $dryRunReportRows[] = [
                        'purchase_id' => (int) $purchase->id,
                        'purchase_no' => $purchase->purchase_no,
                        'sale_order_id' => $orderId,
                        'sale_invoice_no' => $saleInvoiceNo,
                        'result' => 'Skipped (unsafe): matching sale order already has source_sale_id on another purchase',
                    ];
                }
                $bar->advance();
                continue;
            }

            if ($dryRun) {
                $wouldUpdate++;
                $dryRunReportRows[] = [
                    'purchase_id' => (int) $purchase->id,
                    'purchase_no' => $purchase->purchase_no,
                    'sale_order_id' => $orderId,
                    'sale_invoice_no' => $saleInvoiceNo,
                    'result' => 'Would set source_sale_id to matching sale order',
                ];
            } else {
                Purchase::withoutGlobalScopes()
                    ->where('id', $purchase->id)
                    ->update(['source_sale_id' => $orderId]);
                $updated++;
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);

        if ($dryRun && $dryRunReportRows !== []) {
            $this->info('Dry run — per purchase (purchase id → matching sale order id when found):');
            $this->table(
                ['Purchase ID', 'Purchase No', 'Matching sale order ID', 'Sale invoice no', 'Result'],
                array_map(function (array $r) {
                    return [
                        $r['purchase_id'],
                        $r['purchase_no'] ?? '—',
                        $r['sale_order_id'] !== null ? $r['sale_order_id'] : '—',
                        $r['sale_invoice_no'] ?? '—',
                        $r['result'],
                    ];
                }, $dryRunReportRows)
            );
            $this->newLine();
        }

        $this->info('Backfill complete.');
        $this->line('Candidates: ' . $candidates->count());
        $this->line('Updated: ' . $updated);
        $this->line('Would update (dry-run): ' . $wouldUpdate);
        $this->line('Skipped (no match): ' . $skippedNoMatch);
        $this->line('Skipped (ambiguous): ' . $skippedAmbiguous);
        $this->line('Skipped (unsafe): ' . $skippedUnsafe);

        return self::SUCCESS;
    }
}

