<?php

namespace App\Services;

use App\Models\AccountTransaction;
use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderDetails;
use App\Models\PaymentLog;
use App\Models\Product;
use App\Models\SaleReturn;
use App\Models\SaleReturnDetail;
use App\Models\StockLog;
use App\Services\Stock\StockService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

/**
 * Encapsulates business rules for sale returns:
 * - quantity validation against original sale
 * - inventory restoration and stock log creation
 * - financial/accounting impact for walk-in vs registered customers
 * - safe rollback of inventory and ledger when deleting a return
 */
class SaleReturnService
{
    public function __construct(
        private StockService $stockService,
        private CustomerCreditService $customerCreditService,
    ) {
    }

    /**
    * STEP 1 — Validate that requested return quantities do not exceed
    * (sold quantity - already returned quantity) per order line.
    *
    * Called BEFORE creating any sale_return or sale_return_details rows
    * so the entire operation can be safely rejected.
    *
    * @param Order $order
    * @param array<int, array<string, mixed>> $products
    * @throws ValidationException
    */
    public function validateReturnQuantities(Order $order, array $products): void
    {
        $selected = $this->filterSelectedProducts($products);
        if (empty($selected)) {
            throw ValidationException::withMessages([
                'products' => ['Please select at least one item to return.'],
            ]);
        }

        foreach ($selected as $line) {
            $orderDetailId = (int) ($line['order_detail_id'] ?? 0);
            $requestedQty = (float) ($line['quantity'] ?? 0);

            if ($orderDetailId <= 0 || $requestedQty <= 0) {
                continue;
            }

            /** @var OrderDetails|null $orderDetail */
            $orderDetail = $order->orderDetails
                ->where('id', $orderDetailId)
                ->first();

            if (!$orderDetail) {
                throw ValidationException::withMessages([
                    'products' => ["Invalid order line selected for return (ID {$orderDetailId})."],
                ]);
            }

            $alreadyReturned = SaleReturnDetail::where('order_detail_id', $orderDetail->id)
                ->sum('quantity');
            $returnableQty = (float) $orderDetail->quantity - (float) $alreadyReturned;

            if ($requestedQty > $returnableQty) {
                throw ValidationException::withMessages([
                    'products' => [sprintf(
                        'Cannot return more than sold for product line %d. Requested: %s, available to return: %s.',
                        $orderDetail->id,
                        $requestedQty,
                        $returnableQty
                    )],
                ]);
            }
        }
    }

    /**
     * STEP 3 & 4 — Persist one sale_return_details row and restore inventory
     * for a single returned item. Called inside an outer DB transaction.
     *
     * - Inserts sale_return_details row
     * - Increases product stock (products.product_store)
     * - Creates stock_logs entry with direction=in, source_type=sale_return
     * - Ensures stock_logs.reason and stock_logs.stock_qty follow the ERP rule
     *
     * @param SaleReturn $saleReturn
     * @param Order $order
     * @param array<string, mixed> $line
     * @param int|null $shopIdForAccess optional current user shop_id for access checks
     */
    public function createReturnDetailAndRestoreInventory(
        SaleReturn $saleReturn,
        Order $order,
        array $line,
        ?int $shopIdForAccess = null
    ): void {
        $orderDetailId = (int) ($line['order_detail_id'] ?? 0);
        $productId = (int) ($line['product_id'] ?? 0);
        $qty = (int) ($line['quantity'] ?? 0);
        $unitPrice = (float) ($line['unit_price'] ?? 0);
        $itemDiscount = (float) ($line['item_discount'] ?? 0);
        $total = (float) ($line['total'] ?? 0);

        if ($orderDetailId <= 0 || $productId <= 0 || $qty <= 0) {
            throw new InvalidArgumentException('Invalid return line data.');
        }

        /** @var OrderDetails $orderDetail */
        $orderDetail = OrderDetails::findOrFail($orderDetailId);

        /** @var Product $product */
        $product = Product::findOrFail($productId);

        if ($shopIdForAccess && $product->shop_id && $product->shop_id !== $shopIdForAccess) {
            throw new InvalidArgumentException('Product does not belong to your shop.');
        }

        // Defensive quantity validation (should have been done earlier already).
        $alreadyReturned = SaleReturnDetail::where('order_detail_id', $orderDetail->id)
            ->sum('quantity');
        $returnableQty = (float) $orderDetail->quantity - (float) $alreadyReturned;
        if ($qty > $returnableQty) {
            throw new InvalidArgumentException(sprintf(
                'Cannot return more than sold for product line %d. Requested: %s, available to return: %s.',
                $orderDetail->id,
                $qty,
                $returnableQty
            ));
        }

        $returnDetail = SaleReturnDetail::create([
            'return_id' => $saleReturn->id,
            'order_id' => $order->id,
            'order_detail_id' => $orderDetail->id,
            'product_id' => $product->id,
            'quantity' => $qty,
            'unitcost' => $unitPrice,
            'item_discount' => $itemDiscount,
            'total' => $total,
        ]);

        // Increase stock in a ledger-safe way and capture the stock log.
        $log = $this->stockService->saleReturnStock(
            $product,
            $qty,
            $saleReturn->id,
            $saleReturn->return_date
        );

        // Ensure stock_log follows ERP rule:
        // - reason = "Customer sale return"
        // - stock_qty = new stock value after the return (inventory snapshot)
        $product->refresh();
        $log->reason = 'Customer sale return';
        $log->stock_qty = (int) ($product->product_store ?? 0);
        $log->save();
    }

    /**
     * STEP 5 — Apply financial impact of a completed sale return.
     *
     * CASE A: Walk-in customer
     *   - Refund cash (account_transactions: cash / credit)
     *   - Insert payment_logs row of type=refund, payment_method=cash
     *   - DO NOT touch customers.credit_amount
     *
     * CASE B: Registered customer
     *   - Decrease customers.credit_amount by return_total
     *   - Insert account_transactions row for customer balance adjustment
     *
     * @param SaleReturn $saleReturn
     * @param Order $order
     * @param Customer|null $customer
     */
    public function applyFinancialImpact(SaleReturn $saleReturn, Order $order, ?Customer $customer): void
    {
        if (!$customer) {
            return;
        }

        $returnTotal = (float) ($saleReturn->total ?? 0);
        if ($returnTotal <= 0) {
            return;
        }

        $shopId = $order->shop_id;
        $today = Carbon::now()->toDateString();

        if ($customer->is_walkin) {
            // CASE A — Walk-in: refund cash, no change to customer credit.
            AccountTransaction::create([
                'shop_id' => $shopId,
                'account_type' => AccountTransaction::ACCOUNT_TYPE_CASH,
                'account_ref_id' => null,
                'direction' => AccountTransaction::DIRECTION_CREDIT,
                'amount' => $returnTotal,
                'source_type' => AccountTransaction::SOURCE_ADJUSTMENT,
                'source_id' => $saleReturn->id,
                'description' => 'Walk-in sale return refund',
                'transaction_date' => $today,
            ]);

            PaymentLog::create([
                'order_id' => $order->id,
                'amount_paid' => $returnTotal,
                'type' => 'refund',
                'payment_method' => 'cash',
            ]);

            return;
        }

        // CASE B — Registered customer: adjust running balance and ledger.
        $this->customerCreditService->applyPayment($customer, $returnTotal);

        AccountTransaction::create([
            'shop_id' => $shopId,
            'account_type' => AccountTransaction::ACCOUNT_TYPE_CUSTOMER,
            'account_ref_id' => $customer->id,
            'direction' => AccountTransaction::DIRECTION_CREDIT,
            'amount' => $returnTotal,
            'source_type' => AccountTransaction::SOURCE_ADJUSTMENT,
            'source_id' => $saleReturn->id,
            'description' => 'Sale return balance adjustment',
            'transaction_date' => $today,
        ]);
    }

    /**
     * STEP 6 — Reverse inventory and financial impact and delete
     * a sale return. MUST be called inside DB::transaction().
     *
     * - Restore stock: products.product_store -= returned_qty
     * - Delete stock logs for this return
     * - Restore customer balance (registered only)
     * - Delete accounting ledger entries (source_type=adjustment, source_id=return_id)
     * - Delete refund logs from payment_logs for the related order
     * - Soft delete sale_return_details and sale_returns rows
     */
    public function reverseAndDelete(SaleReturn $saleReturn): void
    {
        $saleReturn->loadMissing(['customer', 'order', 'returnDetails']);

        $order = $saleReturn->order;
        $customer = $saleReturn->customer;
        $returnTotal = (float) ($saleReturn->total ?? 0);

        // 1. Restore stock (subtract the quantities that were previously returned).
        foreach ($saleReturn->returnDetails as $detail) {
            /** @var Product|null $product */
            $product = Product::lockForUpdate()->find($detail->product_id);
            if (!$product) {
                continue;
            }

            $newQty = max(0, (int) ($product->product_store ?? 0) - (int) $detail->quantity);
            $product->update(['product_store' => $newQty]);
        }

        // 2. Delete stock logs for this sale return.
        StockLog::where('source_type', 'sale_return')
            ->where('source_id', (string) $saleReturn->id)
            ->delete();

        // 3. Restore customer balance (registered only).
        if ($customer && !$customer->is_walkin && $returnTotal > 0) {
            $this->customerCreditService->reversePayment($customer, $returnTotal);
        }

        // 4. Delete ledger entries for this return adjustment.
        AccountTransaction::where('source_type', AccountTransaction::SOURCE_ADJUSTMENT)
            ->where('source_id', $saleReturn->id)
            ->delete();

        // 5. Delete refund logs for this order (for this return amount only, to keep other returns intact).
        if ($order && $returnTotal > 0) {
            PaymentLog::where('order_id', $order->id)
                ->where('type', 'refund')
                ->where('amount_paid', $returnTotal)
                ->delete();
        }

        // 6. Soft delete return details and header.
        SaleReturnDetail::where('return_id', $saleReturn->id)->delete();
        $saleReturn->delete();
    }

    /**
     * Helper to filter only lines that have a product and positive quantity.
     *
     * @param array<int, array<string, mixed>> $products
     * @return array<int, array<string, mixed>>
     */
    private function filterSelectedProducts(array $products): array
    {
        $filtered = array_filter($products, function ($product) {
            return !empty($product['product_id'])
                && !empty($product['quantity'])
                && (float) $product['quantity'] > 0;
        });

        return array_values($filtered);
    }
}

