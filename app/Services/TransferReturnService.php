<?php

namespace App\Services;

use App\Models\AccountTransaction;
use App\Models\Order;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\PurchaseReturn;
use App\Models\PurchaseReturnDetail;
use App\Models\SaleReturn;
use App\Models\SaleReturnDetail;
use App\Models\OrderDetails;
use App\Models\Supplier;
use App\Models\StockLog;
use App\Services\Stock\StockService;
use Carbon\Carbon;
use Haruncpi\LaravelIdGenerator\IdGenerator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Handles approval and rejection of transfer-based purchase returns.
 * Child shop creates PurchaseReturn; mother shop approval creates a mirror SaleReturn
 * and reverses stock + accounting on both sides.
 */
class TransferReturnService
{
    public function __construct(
        private StockService $stockService,
        private CustomerCreditService $customerCreditService,
        private SupplierCreditService $supplierCreditService,
    ) {
    }

    /**
     * Orchestrate approval of a transfer return request.
     * Single responsibility: validate + coordinate lower-level steps.
     */
    public function approveReturn(PurchaseReturn $purchaseReturn): void
    {
        if ($purchaseReturn->status !== 'pending') {
            throw new InvalidArgumentException('This transfer return has already been processed.');
        }

        $purchase = Purchase::with(['supplier', 'shop'])->findOrFail($purchaseReturn->purchase_id);
        if (!$purchase->source_sale_id) {
            throw new InvalidArgumentException('This purchase is not linked to a mother shop sale.');
        }

        $motherSale = Order::with(['customer', 'shop'])
            ->findOrFail($purchase->source_sale_id);

        // 1. Create mother shop sale return header
        $saleReturn = $this->createMotherSaleReturn($purchaseReturn, $motherSale);

        // 2. Create mother sale return line items from child purchase return lines
        $this->createMotherSaleReturnItems($purchaseReturn, $saleReturn, $motherSale);

        // 3. Adjust inventory on child shop (stock out)
        $this->reduceChildInventory($purchaseReturn);

        // 4. Adjust inventory on mother shop (stock in)
        $this->increaseMotherInventory($purchaseReturn, $saleReturn, $motherSale);

        // 5. Create stock logs on child shop for purchase_return
        $this->createChildStockLogs($purchaseReturn);

        // 6. Create stock logs on mother shop for sale_return
        $this->createMotherStockLogs($purchaseReturn, $saleReturn, $motherSale);

        // 7. Reverse accounting for child shop (purchase + supplier balance)
        $this->reverseChildAccounting($purchaseReturn, $purchase);

        // 8. Reverse accounting for mother shop (sale + customer balance)
        $this->reverseMotherAccounting($purchaseReturn, $motherSale);

        // 9. Link purchase_return to mother sale_return
        $this->linkReturnDocuments($purchaseReturn, $saleReturn);

        // 10. Mark purchase_return as approved
        $this->markReturnApproved($purchaseReturn);
    }

    /**
     * Create mother shop SaleReturn header mirroring the transfer purchase return.
     */
    public function createMotherSaleReturn(PurchaseReturn $purchaseReturn, Order $motherSale): SaleReturn
    {
        $returnNo = IdGenerator::generate([
            'table' => 'sale_returns',
            'field' => 'return_no',
            'length' => 12,
            'prefix' => 'TRR-',
        ]);

        $subTotal = (float) $purchaseReturn->sub_total;
        $totalProducts = (int) $purchaseReturn->total_products;

        return SaleReturn::create([
            'order_id' => $motherSale->id,
            'customer_id' => $motherSale->customer_id,
            'shop_id' => $motherSale->shop_id,
            'return_date' => Carbon::parse($purchaseReturn->return_date)->format('Y-m-d H:i:s'),
            'return_status' => 'completed',
            'return_no' => $returnNo,
            'total_products' => $totalProducts,
            'sub_total' => $subTotal,
            'invoice_discount' => 0,
            'vat' => 0,
            'total' => $subTotal,
            'reason' => 'Transfer return from child shop purchase #' . $purchaseReturn->return_no,
        ]);
    }

    /**
     * Create SaleReturnDetail rows based on PurchaseReturnDetail rows.
     */
    public function createMotherSaleReturnItems(
        PurchaseReturn $purchaseReturn,
        SaleReturn $saleReturn,
        Order $motherSale
    ): void {
        $details = PurchaseReturnDetail::with('product')
            ->where('purchase_return_id', $purchaseReturn->id)
            ->get();

        foreach ($details as $detail) {
            if ($detail->quantity <= 0) {
                continue;
            }

            // Always use the master/mother product id for mother shop sale return.
            // Child shop products map back via parent_product_id.
            $masterProduct = $detail->product ? $detail->product->getMasterProduct() : null;
            $productId = $masterProduct?->id ?? $detail->product_id;

            // sale_return_details.order_detail_id is required by schema, so resolve it from
            // the original mother sale order line using the mapped mother/master product id.
            $orderDetail = OrderDetails::query()
                ->where('order_id', $motherSale->id)
                ->where('product_id', $productId)
                ->first();

            if (!$orderDetail) {
                throw new InvalidArgumentException(
                    "Mother sale order detail not found for product_id {$productId} on order {$motherSale->id}."
                );
            }

            SaleReturnDetail::create([
                'return_id' => $saleReturn->id,
                'order_id' => $motherSale->id,
                'order_detail_id' => $orderDetail->id,
                'product_id' => $productId,
                'quantity' => (int) $detail->quantity,
                'unitcost' => (float) $detail->price,
                'item_discount' => 0,
                'total' => (float) $detail->total,
            ]);
        }
    }

    /**
     * Reduce child shop inventory for returned products.
     * Stock leaves the child shop back to the mother shop.
     */
    public function reduceChildInventory(PurchaseReturn $purchaseReturn): void
    {
        $details = PurchaseReturnDetail::with('product')
            ->where('purchase_return_id', $purchaseReturn->id)
            ->get();

        foreach ($details as $detail) {
            $product = $detail->product;
            if (!$product || $detail->quantity <= 0) {
                continue;
            }

            $newQty = max(0, (int) $product->product_store - (int) $detail->quantity);
            $product->update(['product_store' => $newQty]);
        }
    }

    /**
     * Increase mother shop inventory for returned products.
     */
    public function increaseMotherInventory(
        PurchaseReturn $purchaseReturn,
        SaleReturn $saleReturn,
        Order $motherSale
    ): void {
        $details = PurchaseReturnDetail::with('product')
            ->where('purchase_return_id', $purchaseReturn->id)
            ->get();

        foreach ($details as $detail) {
            $masterProduct = $detail->product ? $detail->product->getMasterProduct() : null;
            $product = $masterProduct && $masterProduct->shop_id == $motherSale->shop_id
                ? $masterProduct
                : null;

            if (!$product || $detail->quantity <= 0) {
                continue;
            }

            $newQty = (int) $product->product_store + (int) $detail->quantity;
            $product->update(['product_store' => $newQty]);
        }
    }

    /**
     * Create child shop stock logs for purchase_return (stock out).
     */
    public function createChildStockLogs(PurchaseReturn $purchaseReturn): void
    {
        $details = PurchaseReturnDetail::with('product')
            ->where('purchase_return_id', $purchaseReturn->id)
            ->get();

        foreach ($details as $detail) {
            $product = $detail->product;
            if (!$product || $detail->quantity <= 0) {
                continue;
            }

            StockLog::create([
                'shop_id' => $purchaseReturn->shop_id,
                'product_id' => $product->id,
                'supplier_id' => $product->supplier_id,
                'qty' => (int) $detail->quantity,
                'stock_qty' => -(int) $detail->quantity,
                'direction' => 'out',
                'source_type' => 'purchase_return',
                'source_id' => (string) $purchaseReturn->id,
                'price' => (float) $detail->price,
                'reason' => 'Transfer purchase return to mother shop',
                'adjustment_date' => Carbon::parse($purchaseReturn->return_date)->toDateString(),
            ]);
        }
    }

    /**
     * Create mother shop stock logs for sale_return (stock in).
     */
    public function createMotherStockLogs(
        PurchaseReturn $purchaseReturn,
        SaleReturn $saleReturn,
        Order $motherSale
    ): void {
        $details = PurchaseReturnDetail::with('product')
            ->where('purchase_return_id', $purchaseReturn->id)
            ->get();

        foreach ($details as $detail) {
            $childProduct = $detail->product;
            if (!$childProduct || $detail->quantity <= 0) {
                continue;
            }

            $motherProduct = $childProduct->getMasterProduct();
            if ($motherProduct->shop_id != $motherSale->shop_id) {
                continue;
            }

            if (!$motherProduct) {
                continue;
            }

            StockLog::create([
                'shop_id' => $motherSale->shop_id,
                'product_id' => $motherProduct->id,
                'supplier_id' => null,
                'qty' => (int) $detail->quantity,
                'stock_qty' => (int) $detail->quantity,
                'direction' => 'in',
                'source_type' => 'sale_return',
                'source_id' => (string) $saleReturn->id,
                'price' => (float) $detail->price,
                'reason' => 'Transfer return received from child shop',
                'adjustment_date' => Carbon::parse($purchaseReturn->return_date)->toDateString(),
            ]);
        }
    }

    /**
     * Reverse child shop accounting for the purchase and supplier balance.
     * Uses source_type = "purchase_return" for traceability.
     */
    public function reverseChildAccounting(PurchaseReturn $purchaseReturn, Purchase $purchase): void
    {
        $shopId = $purchaseReturn->shop_id;
        $amount = (float) $purchaseReturn->total;
        if ($amount <= 0) {
            return;
        }

        $date = Carbon::parse($purchaseReturn->return_date)->toDateString();

        // Reduce purchase value (inventory / cost)
        AccountTransaction::create([
            'shop_id' => $shopId,
            'account_type' => AccountTransaction::ACCOUNT_TYPE_PURCHASE,
            'account_ref_id' => null,
            'direction' => AccountTransaction::DIRECTION_CREDIT,
            'amount' => $amount,
            'source_type' => 'purchase_return',
            'source_id' => $purchaseReturn->id,
            'description' => 'Transfer purchase return for purchase #' . $purchase->purchase_no,
            'transaction_date' => $date,
        ]);

        // Reduce supplier balance
        if ($purchase->supplier_id) {
            $supplier = Supplier::withoutGlobalScope('shop')
                ->where('id', $purchase->supplier_id)
                ->first();

            AccountTransaction::create([
                'shop_id' => $shopId,
                'account_type' => AccountTransaction::ACCOUNT_TYPE_SUPPLIER,
                'account_ref_id' => $purchase->supplier_id,
                'direction' => AccountTransaction::DIRECTION_DEBIT,
                'amount' => $amount,
                'source_type' => 'purchase_return',
                'source_id' => $purchaseReturn->id,
                'description' => 'Transfer purchase return supplier adjustment',
                'transaction_date' => $date,
            ]);

            // Update supplier running balance via service
            if ($supplier) {
                $this->supplierCreditService->applyPayment($supplier, $amount);
            }
        }
    }

    /**
     * Reverse mother shop accounting for the original sale and customer balance.
     * Uses source_type = "purchase_return" for traceability.
     */
    public function reverseMotherAccounting(PurchaseReturn $purchaseReturn, Order $motherSale): void
    {
        $shopId = $motherSale->shop_id;
        $amount = (float) $purchaseReturn->total;
        if ($amount <= 0) {
            return;
        }

        $date = Carbon::parse($purchaseReturn->return_date)->toDateString();

        // Reduce sale value (revenue)
        AccountTransaction::create([
            'shop_id' => $shopId,
            'account_type' => AccountTransaction::ACCOUNT_TYPE_SALE,
            'account_ref_id' => null,
            'direction' => AccountTransaction::DIRECTION_DEBIT,
            'amount' => $amount,
            'source_type' => 'purchase_return',
            'source_id' => $purchaseReturn->id,
            'description' => 'Transfer sale return for invoice ' . $motherSale->invoice_no,
            'transaction_date' => $date,
        ]);

        // Reduce customer balance (credit)
        if ($motherSale->customer) {
            AccountTransaction::create([
                'shop_id' => $shopId,
                'account_type' => AccountTransaction::ACCOUNT_TYPE_CUSTOMER,
                'account_ref_id' => $motherSale->customer_id,
                'direction' => AccountTransaction::DIRECTION_CREDIT,
                'amount' => $amount,
                'source_type' => 'purchase_return',
                'source_id' => $purchaseReturn->id,
                'description' => 'Transfer sale return balance adjustment',
                'transaction_date' => $date,
            ]);

            $this->customerCreditService->applyPayment($motherSale->customer, $amount);
        }
    }

    /**
     * Link purchase_return to the created mother sale_return.
     */
    public function linkReturnDocuments(PurchaseReturn $purchaseReturn, SaleReturn $saleReturn): void
    {
        $purchaseReturn->linked_sale_return_id = $saleReturn->id;
        $purchaseReturn->save();
    }

    /**
     * Mark a purchase_return as approved.
     */
    public function markReturnApproved(PurchaseReturn $purchaseReturn): void
    {
        $purchaseReturn->status = 'approved';
        $purchaseReturn->approved_by = Auth::id();
        $purchaseReturn->approved_at = Carbon::now();
        $purchaseReturn->save();
    }

    /**
     * Reject a transfer return request without touching stock or accounting.
     */
    public function rejectReturn(PurchaseReturn $purchaseReturn): void
    {
        if ($purchaseReturn->status !== 'pending') {
            throw new InvalidArgumentException('This transfer return has already been processed.');
        }

        $purchaseReturn->status = 'rejected';
        $purchaseReturn->rejected_by = Auth::id();
        $purchaseReturn->rejected_at = Carbon::now();
        $purchaseReturn->save();
    }
}

