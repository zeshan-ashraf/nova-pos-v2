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
use App\Support\ProductUnitValidator;
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
        private ProductUnitValidator $units,
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
        foreach ($products as $line) {
            $this->rejectIfOverspecifiedQuantity($line['quantity'] ?? null);
        }

        $selected = $this->filterSelectedProducts($products);
        if (empty($selected)) {
            throw ValidationException::withMessages([
                'products' => ['Please select at least one item to return.'],
            ]);
        }

        foreach ($selected as $line) {
            $orderDetailId = (int) ($line['order_detail_id'] ?? 0);
            $requestedRaw = $line['quantity'] ?? 0;

            if ($orderDetailId <= 0 || ! $this->isPositiveBusinessQuantity($requestedRaw)) {
                $this->rejectIfOverspecifiedQuantity($requestedRaw);
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

            $unit = $this->returnUnitForOrderDetail($orderDetail);
            if (! $this->units->isValidQuantity($requestedRaw, $unit, false)) {
                throw ValidationException::withMessages([
                    'products' => [$this->units->invalidQuantityMessage($requestedRaw, $unit, false)],
                ]);
            }

            $requestedQty = $this->units->formatQuantity($requestedRaw);
            $returnableQty = $this->returnableQuantity($orderDetail);

            if ($this->units->compare($requestedQty, $returnableQty) > 0) {
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
        $qtyRaw = $line['quantity'] ?? 0;
        $unitPrice = (float) ($line['unit_price'] ?? 0);
        $itemDiscount = (float) ($line['item_discount'] ?? 0);
        $total = (float) ($line['total'] ?? 0);

        if ($orderDetailId <= 0 || $productId <= 0) {
            throw new InvalidArgumentException('Invalid return line data.');
        }

        /** @var OrderDetails $orderDetail */
        $orderDetail = OrderDetails::findOrFail($orderDetailId);

        /** @var Product $product */
        $product = Product::findOrFail($productId);
        $unit = $this->returnUnitForOrderDetail($orderDetail);
        $this->units->validateQuantity($qtyRaw, $unit, false);
        $qty = $this->units->formatQuantity($qtyRaw);

        if ($shopIdForAccess && $product->shop_id && $product->shop_id !== $shopIdForAccess) {
            throw new InvalidArgumentException('Product does not belong to your shop.');
        }

        $returnableQty = $this->returnableQuantity($orderDetail);
        if ($this->units->compare($qty, $returnableQty) > 0) {
            throw new InvalidArgumentException(sprintf(
                'Cannot return more than sold for product line %d. Requested: %s, available to return: %s.',
                $orderDetail->id,
                $qty,
                $returnableQty
            ));
        }

        SaleReturnDetail::create([
            'return_id' => $saleReturn->id,
            'order_id' => $order->id,
            'order_detail_id' => $orderDetail->id,
            'product_id' => $product->id,
            'quantity' => $qty,
            'unit' => $unit,
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

        $log->reason = 'Customer sale return';
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

        $shopId = $saleReturn->shop_id
            ?? $order->shop_id
            ?? $customer?->shop_id;

        if (empty($shopId)) {
            throw new InvalidArgumentException('Unable to post sale return financial impact: missing shop_id.');
        }
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

            $current = $this->units->formatQuantity($product->product_store ?? '0');
            $returned = $this->units->formatQuantity($detail->quantity ?? '0');
            $newQty = $this->units->compare($current, $returned) <= 0
                ? '0.000'
                : $this->units->subtract($current, $returned);
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
            if (empty($product['product_id']) || ! isset($product['quantity']) || $product['quantity'] === '') {
                return false;
            }

            return $this->isPositiveBusinessQuantity($product['quantity']);
        });

        return array_values($filtered);
    }

    /**
     * Historical sale-line unit. Falls back to the product unit only when the snapshot is missing.
     */
    public function returnUnitForOrderDetail(OrderDetails $orderDetail): string
    {
        $snapshot = $orderDetail->snapshotUnit();
        $productUnit = $orderDetail->product?->unit ?: Product::UNIT_PIECE;
        if ($snapshot !== $productUnit && in_array($orderDetail->unit, Product::allowedUnits(), true)) {
            return $snapshot;
        }

        return in_array($snapshot, Product::allowedUnits(), true) ? $snapshot : $productUnit;
    }

    private function isPositiveBusinessQuantity(mixed $quantity): bool
    {
        $normalized = $this->units->normalizeQuantity($quantity);
        if ($normalized === null || $this->hasMoreThanThreeDecimalPlaces($normalized)) {
            return false;
        }

        return $this->units->compare($normalized, '0') > 0;
    }

    private function rejectIfOverspecifiedQuantity(mixed $quantity): void
    {
        $normalized = $this->units->normalizeQuantity($quantity);
        if ($normalized !== null && $this->hasMoreThanThreeDecimalPlaces($normalized)) {
            throw ValidationException::withMessages([
                'products' => ['Kg quantity must be at least 0.001 with at most 3 decimal places.'],
            ]);
        }
    }

    private function hasMoreThanThreeDecimalPlaces(string $quantity): bool
    {
        $dot = strpos(ltrim($quantity, '+-'), '.');

        return $dot !== false && strlen(substr(ltrim($quantity, '+-'), $dot + 1)) > ProductUnitValidator::DECIMAL_PLACES;
    }

    public function returnableQuantity(OrderDetails $orderDetail): string
    {
        $sold = $this->units->formatQuantity($orderDetail->quantity ?? '0');
        $alreadyReturned = $this->units->formatQuantity(
            SaleReturnDetail::where('order_detail_id', $orderDetail->id)->sum('quantity')
        );

        if ($this->units->compare($sold, $alreadyReturned) <= 0) {
            return '0.000';
        }

        return $this->units->subtract($sold, $alreadyReturned);
    }
}

