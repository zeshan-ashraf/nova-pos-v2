<?php

namespace App\Services;

use App\Models\AccountTransaction;
use App\Models\Category;
use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderDetails;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\PurchaseDetail;
use App\Models\PurchasePaymentLog;
use App\Models\Shop;
use App\Models\ShopNotification;
use App\Models\ShopPurchaseRequest;
use App\Models\StockLog;
use App\Models\Supplier;
use App\Models\PaymentLog;
use App\Services\Stock\StockService;
use App\Support\InterShopTransferStatus;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Haruncpi\LaravelIdGenerator\IdGenerator;

class InterShopTransferService
{
    public function __construct(
        private StockService $stockService,
        private SalePostingService $salePostingService,
    ) {
    }

    /**
     * @param  int|null  $shopPurchaseRequestId  Child-facing notifications: set so UI links to /shop-purchase-requests/{id}.
     * @param  int|null  $motherOrderId  Mother-shop notifications only: optional link to /orders/details/{id}.
     */
    public function notifyShop(int $shopId, string $type, string $message, ?int $shopPurchaseRequestId = null, ?int $motherOrderId = null): void
    {
        $data = ['message' => $message];
        if ($shopPurchaseRequestId !== null) {
            $data['shop_purchase_request_id'] = $shopPurchaseRequestId;
        }
        if ($motherOrderId !== null) {
            $data['order_id'] = $motherOrderId;
        }

        ShopNotification::create([
            'shop_id' => $shopId,
            'shop_purchase_request_id' => $shopPurchaseRequestId,
            'type' => $type,
            'data' => $data,
            'is_read' => false,
            'created_at' => now(),
        ]);
    }

    /**
     * Create mother sale + child shop purchase request (no child purchase until the child approves).
     * Reserves mother stock only; no ledger or stock movements on the child purchase yet.
     *
     * @return array{order: Order, shopPurchaseRequest: ShopPurchaseRequest}
     */
    public function createPendingTransfer(
        Request $request,
        array $validatedData,
        Shop $motherShop,
        Shop $childShop,
        Supplier $supplier,
        Customer $systemCustomer,
        string $invoiceNo,
        float $subtotal,
        int $totalProducts,
        float $vat,
        float $invoiceDiscount,
        float $total,
        float $pay,
        float $due,
        string $paymentStatus,
        string $paymentMethod1,
        ?string $paymentMethod2,
        float $pay1,
        float $pay2,
        ?int $shopBankId1,
        ?int $shopBankId2,
    ): array {
        return DB::transaction(function () use (
            $request,
            $validatedData,
            $motherShop,
            $childShop,
            $supplier,
            $systemCustomer,
            $invoiceNo,
            $subtotal,
            $totalProducts,
            $vat,
            $invoiceDiscount,
            $total,
            $pay,
            $due,
            $paymentStatus,
            $paymentMethod1,
            $paymentMethod2,
            $pay1,
            $pay2,
            $shopBankId1,
            $shopBankId2,
        ) {
            foreach ($validatedData['products'] as $product) {
                if (empty($product['product_id'])) {
                    continue;
                }
                $motherProduct = Product::lockForUpdate()->findOrFail((int) $product['product_id']);
                if ($motherProduct->shop_id !== $motherShop->id) {
                    throw new \RuntimeException("Product {$motherProduct->product_name} does not belong to your shop.");
                }
                $qty = (float) $product['quantity'];
                $store = (float) ($motherProduct->product_store ?? 0);
                $reserved = (float) ($motherProduct->reserved_stock ?? 0);
                $available = $store - $reserved;
                if ($available + 0.0001 < $qty) {
                    $label = $motherProduct->product_code ?? $motherProduct->product_name;

                    throw new \RuntimeException("Insufficient available stock for {$label}. Available: {$available}");
                }
            }

            $orderData = [
            'customer_id' => $systemCustomer->id,
            'shop_id' => $motherShop->id,
            'order_date' => Carbon::parse($validatedData['order_date'])->format('Y-m-d H:i:s'),
            'order_status' => InterShopTransferStatus::PENDING,
            'total_products' => $totalProducts,
            'sub_total' => $subtotal,
            'invoice_discount' => $invoiceDiscount,
            'vat' => $vat,
            'invoice_no' => $invoiceNo,
            'total' => $total,
            'payment_status' => $paymentStatus,
            'pay' => $pay,
            'due' => $due,
            'comment' => $request->input('comment'),
        ];
        if ($request->filled('edited_from_order_id')) {
            $orderData['edited_from_order_id'] = $request->input('edited_from_order_id');
        }

            $order = Order::create($orderData);

            foreach ($validatedData['products'] as $product) {
                if (empty($product['product_id'])) {
                    continue;
                }
                $motherProduct = Product::lockForUpdate()->findOrFail((int) $product['product_id']);
                $qty = (float) $product['quantity'];

                OrderDetails::insert([
                'order_id' => $order->id,
                'product_id' => $product['product_id'],
                'quantity' => $product['quantity'],
                'unitcost' => $product['unit_price'],
                'cost_per_unit' => (float) ($motherProduct->buying_price ?? 0),
                'item_discount' => $product['item_discount'] ?? 0,
                'total' => $product['total'],
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
                ]);

                Product::where('id', $motherProduct->id)->update([
                    'reserved_stock' => DB::raw('reserved_stock + ' . $qty),
                ]);

                $childProduct = $this->findChildShopProductForMotherSale($childShop, $motherProduct);
                if ($childProduct) {
                    if (empty($childProduct->parent_product_id)) {
                        $childProduct->parent_product_id = $motherProduct->id;
                        $childProduct->save();
                    }
                } else {
                    $motherCategory = Category::withoutGlobalScope('shop')->find($motherProduct->category_id);
                    $categoryName = $motherCategory ? trim($motherCategory->name) : 'Uncategorized';
                    $childCategory = Category::withoutGlobalScope('shop')
                        ->where('shop_id', $childShop->id)
                        ->whereRaw('LOWER(TRIM(name)) = ?', [strtolower($categoryName)])
                        ->first();
                    if (!$childCategory) {
                        $childCategory = Category::create([
                            'name' => $categoryName,
                            'shop_id' => $childShop->id,
                            'slug' => Str::slug($categoryName),
                        ]);
                    }
                    Product::create([
                        'product_name' => $motherProduct->product_name,
                        'category_id' => $childCategory->id,
                        'supplier_id' => $supplier->id,
                        'shop_id' => $childShop->id,
                        'parent_product_id' => $motherProduct->id,
                        'product_code' => $motherProduct->product_code,
                        'product_garage' => $motherProduct->product_garage,
                        'product_image' => $motherProduct->product_image,
                        'product_store' => 0,
                        'reserved_stock' => 0,
                        'low_stock_warning' => $motherProduct->low_stock_warning,
                        'buying_date' => $motherProduct->buying_date,
                        'expire_date' => $motherProduct->expire_date,
                        'buying_price' => $product['unit_price'],
                        'selling_price' => $product['unit_price'],
                        'status' => $motherProduct->status,
                    ]);
                }
            }

            $lines = [];
            foreach ($validatedData['products'] as $product) {
                if (empty($product['product_id'])) {
                    continue;
                }
                $motherProduct = Product::findOrFail((int) $product['product_id']);
                $lines[] = [
                    'mother_product_id' => (int) $product['product_id'],
                    'product_name' => $motherProduct->product_name,
                    'product_code' => $motherProduct->product_code,
                    'quantity' => $product['quantity'],
                    'unit_price' => $product['unit_price'],
                    'item_discount' => $product['item_discount'] ?? 0,
                    'total' => $product['total'],
                ];
            }

            $payload = [
                'invoice_no' => $invoiceNo,
                'order_date' => Carbon::parse($validatedData['order_date'])->format('Y-m-d H:i:s'),
                'mother_shop_id' => $motherShop->id,
                'mother_shop_name' => $motherShop->name,
                'supplier_id' => $supplier->id,
                'supplier_name' => $supplier->name ?? '',
                'lines' => $lines,
                'sub_total' => $subtotal,
                'vat' => $vat,
                'invoice_discount' => $invoiceDiscount,
                'total' => $total,
                'pay' => $pay,
                'due' => $due,
                'payment_status' => $paymentStatus,
                'payment_method_1' => $paymentMethod1,
                'payment_method_2' => $paymentMethod2,
                'pay_1' => $pay1,
                'pay_2' => $pay2,
                'shop_bank_id_1' => $shopBankId1,
                'shop_bank_id_2' => $shopBankId2,
                'comment' => $request->input('comment'),
                'preserved_purchase_no' => $request->input('preserved_purchase_no'),
                'preserved_purchase_date' => $request->input('preserved_purchase_date'),
            ];

            $shopPurchaseRequest = ShopPurchaseRequest::create([
                'type' => 'inter_shop_transfer',
                'mother_shop_sale_id' => $order->id,
                'child_shop_id' => $childShop->id,
                'mapped_purchase_id' => null,
                'status' => InterShopTransferStatus::PENDING,
                'payload' => $payload,
            ]);

            if ($pay1 > 0) {
                $this->insertOrderPaymentLog($order->id, $pay1, $paymentMethod1, $shopBankId1);
            }
            if ($pay2 > 0 && $paymentMethod2) {
                $this->insertOrderPaymentLog($order->id, $pay2, $paymentMethod2, $shopBankId2);
            }

            $this->notifyShop(
                $childShop->id,
                'order_created',
                'New inter-shop transfer pending your approval',
                (int) $shopPurchaseRequest->id,
                null
            );

            return ['order' => $order, 'shopPurchaseRequest' => $shopPurchaseRequest];
        });
    }

    private function normalizeLedgerPaymentMethod(string $raw): string
    {
        $m = strtolower(trim($raw));

        return match (true) {
            $m === 'bank' => 'bank',
            $m === 'cheque' => 'cheque',
            $m === 'cash' || $m === 'handcash' => 'cash',
            default => 'credit',
        };
    }

    private function insertOrderPaymentLog(int $orderId, float $amountPaid, string $paymentMethod, ?int $shopBankId): void
    {
        if ($amountPaid <= 0) {
            return;
        }
        $data = [
            'order_id' => $orderId,
            'amount_paid' => $amountPaid,
            'type' => 'payment',
            'payment_method' => $paymentMethod,
        ];
        if ($shopBankId !== null) {
            $data['shop_bank_id'] = $shopBankId;
        }
        PaymentLog::create($data);
    }

    /**
     * Child shop approves the mapped purchase request (creates the child purchase on first approval, or re-approves after mother reset).
     */
    public function approveShopPurchaseRequest(int $requestId, int $childShopId, int $userId): void
    {
        DB::transaction(function () use ($requestId, $childShopId, $userId) {
            $spr = ShopPurchaseRequest::query()
                ->lockForUpdate()
                ->findOrFail($requestId);

            if ((int) $spr->child_shop_id !== (int) $childShopId) {
                throw new \RuntimeException('You are not allowed to approve this request.');
            }
            if ($spr->status !== InterShopTransferStatus::PENDING) {
                throw new \RuntimeException('Only pending requests can be approved.');
            }

            $order = Order::withoutGlobalScopes()
                ->lockForUpdate()
                ->findOrFail((int) $spr->mother_shop_sale_id);

            if ($order->order_status !== InterShopTransferStatus::PENDING) {
                throw new \RuntimeException('The linked sale is not pending approval.');
            }

            $childShop = Shop::findOrFail((int) $spr->child_shop_id);
            $payload = $spr->payload ?? [];
            $supplierId = (int) ($payload['supplier_id'] ?? 0);
            if ($supplierId <= 0) {
                throw new \RuntimeException('Purchase request is missing supplier data.');
            }
            $supplier = Supplier::withoutGlobalScope('shop')->findOrFail($supplierId);

            if ($spr->mapped_purchase_id) {
                $purchase = Purchase::withoutGlobalScopes()
                    ->lockForUpdate()
                    ->findOrFail((int) $spr->mapped_purchase_id);
                if (!(bool) $purchase->is_system_generated || (int) $purchase->source_sale_id !== (int) $order->id) {
                    throw new \RuntimeException('Invalid linked purchase for this request.');
                }
                if ($purchase->purchase_status !== InterShopTransferStatus::PENDING) {
                    throw new \RuntimeException('Only pending transfers can be approved.');
                }
            } else {
                $purchase = $this->createChildInterShopPurchaseFromOrder($order, $childShop, $supplier, $spr);
                $spr->mapped_purchase_id = $purchase->id;
                $spr->save();
            }

            $purchase->update([
                'purchase_status' => InterShopTransferStatus::APPROVED,
                'approved_at' => now(),
                'approved_by' => $userId,
            ]);

            $order->update(['order_status' => InterShopTransferStatus::APPROVED]);

            $spr->update([
                'status' => InterShopTransferStatus::APPROVED,
                'approved_at' => now(),
                'approved_by' => $userId,
            ]);

            $this->notifyShop((int) $order->shop_id, 'order_approved', 'Order approved', null, (int) $order->id);
        });
    }

    /**
     * @deprecated Use approveShopPurchaseRequest; kept for routes that still post by purchase id.
     */
    public function approveTransferPurchase(int $purchaseId, int $userId): void
    {
        $spr = ShopPurchaseRequest::query()
            ->where('mapped_purchase_id', $purchaseId)
            ->first();
        if ($spr) {
            $this->approveShopPurchaseRequest((int) $spr->id, (int) $spr->child_shop_id, $userId);

            return;
        }

        DB::transaction(function () use ($purchaseId, $userId) {
            $purchase = Purchase::withoutGlobalScopes()
                ->lockForUpdate()
                ->findOrFail($purchaseId);

            if (!(bool) $purchase->is_system_generated || !$purchase->source_sale_id) {
                throw new \RuntimeException('This action is only for inter-shop transfer purchases.');
            }
            if ($purchase->purchase_status !== InterShopTransferStatus::PENDING) {
                throw new \RuntimeException('Only pending transfers can be approved.');
            }

            $order = Order::withoutGlobalScopes()
                ->lockForUpdate()
                ->findOrFail((int) $purchase->source_sale_id);
            if ($order->order_status !== InterShopTransferStatus::PENDING) {
                throw new \RuntimeException('The linked sale is not pending approval.');
            }

            $purchase->update([
                'purchase_status' => InterShopTransferStatus::APPROVED,
                'approved_at' => now(),
                'approved_by' => $userId,
            ]);
            $order->update(['order_status' => InterShopTransferStatus::APPROVED]);

            $this->notifyShop((int) $order->shop_id, 'order_approved', 'Order approved', null, (int) $order->id);
        });
    }

    private function createChildInterShopPurchaseFromOrder(Order $order, Shop $childShop, Supplier $supplier, ShopPurchaseRequest $spr): Purchase
    {
        $order->loadMissing('orderDetails');
        $payload = $spr->payload ?? [];
        $totalProducts = (int) ($order->total_products ?? $order->orderDetails->count());

        $purchaseNo = !empty($payload['preserved_purchase_no'])
            ? (string) $payload['preserved_purchase_no']
            : IdGenerator::generate([
                'table' => 'purchases',
                'field' => 'purchase_no',
                'length' => 10,
                'prefix' => 'PUR-',
            ]);
        $purchaseDate = !empty($payload['preserved_purchase_date'])
            ? Carbon::parse($payload['preserved_purchase_date'])->format('Y-m-d')
            : ($order->order_date instanceof Carbon
                ? $order->order_date->format('Y-m-d')
                : Carbon::parse($order->order_date)->format('Y-m-d'));

        $paymentMethod1 = (string) ($payload['payment_method_1'] ?? 'credit');

        $purchase = Purchase::create([
            'supplier_id' => $supplier->id,
            'shop_id' => $childShop->id,
            'source_sale_id' => $order->id,
            'is_system_generated' => true,
            'purchase_date' => $purchaseDate,
            'purchase_status' => InterShopTransferStatus::PENDING,
            'total_products' => $totalProducts,
            'sub_total' => (float) ($order->sub_total ?? 0),
            'invoice_discount' => (float) ($order->invoice_discount ?? 0),
            'vat' => (float) ($order->vat ?? 0),
            'purchase_no' => $purchaseNo,
            'total' => (float) ($order->total ?? 0),
            'payment_status' => $paymentMethod1,
            'pay' => (float) ($order->pay ?? 0),
            'due' => (float) ($order->due ?? 0),
            'comment' => $order->comment,
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now(),
        ]);

        foreach ($order->orderDetails as $detail) {
            $motherProduct = Product::withoutGlobalScope('shop')->findOrFail((int) $detail->product_id);
            $childProduct = $this->findChildShopProductForMotherSale($childShop, $motherProduct);
            if (!$childProduct) {
                throw new \RuntimeException('Child product mapping missing for product '.$motherProduct->product_name);
            }

            PurchaseDetail::insert([
                'purchase_id' => $purchase->id,
                'product_id' => $childProduct->id,
                'quantity' => $detail->quantity,
                'unitcost' => $detail->unitcost,
                'item_discount' => $detail->item_discount ?? 0,
                'total' => $detail->total,
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ]);
        }

        return $purchase->fresh();
    }

    public function resetApprovalByMother(int $orderId, int $motherShopId): void
    {
        DB::transaction(function () use ($orderId, $motherShopId) {
            $order = Order::lockForUpdate()->findOrFail($orderId);
            if ((int) $order->shop_id !== $motherShopId) {
                throw new \RuntimeException('You cannot reset this order.');
            }
            if ($order->order_status !== InterShopTransferStatus::APPROVED) {
                throw new \RuntimeException('Approval can only be reset when the order is approved.');
            }

            $spr = ShopPurchaseRequest::query()
                ->where('mother_shop_sale_id', $order->id)
                ->lockForUpdate()
                ->first();

            $purchase = null;
            if ($spr && $spr->mapped_purchase_id) {
                $purchase = Purchase::withoutGlobalScopes()
                    ->where('id', $spr->mapped_purchase_id)
                    ->lockForUpdate()
                    ->first();
            }
            if (!$purchase) {
                $purchase = Purchase::withoutGlobalScopes()
                    ->where('source_sale_id', $order->id)
                    ->where('is_system_generated', true)
                    ->lockForUpdate()
                    ->firstOrFail();
            }

            $purchase->update([
                'purchase_status' => InterShopTransferStatus::PENDING,
                'approved_at' => null,
                'approved_by' => null,
            ]);
            $order->update(['order_status' => InterShopTransferStatus::PENDING]);

            if ($spr) {
                $spr->update([
                    'status' => InterShopTransferStatus::PENDING,
                    'approved_at' => null,
                    'approved_by' => null,
                ]);
            }

            $childShopId = (int) $purchase->shop_id;
            $this->notifyShop(
                $childShopId,
                'approval_reset',
                'Approval has been reset — please review the purchase request again',
                $spr ? (int) $spr->id : null,
                null
            );
        });
    }

    public function completeTransferByMother(
        int $orderId,
        int $motherShopId,
        CustomerCreditService $creditService,
        SupplierCreditService $supplierCreditService,
    ): void {
        DB::transaction(function () use ($orderId, $motherShopId, $creditService, $supplierCreditService) {
            $order = Order::with(['orderDetails', 'customer'])
                ->lockForUpdate()
                ->findOrFail($orderId);

            if ((int) $order->shop_id !== $motherShopId) {
                throw new \RuntimeException('You cannot complete this order.');
            }
            if ($order->order_status !== InterShopTransferStatus::APPROVED) {
                throw new \RuntimeException('The order must be approved by the child shop before completion.');
            }

            $spr = ShopPurchaseRequest::query()
                ->where('mother_shop_sale_id', $order->id)
                ->lockForUpdate()
                ->first();

            $purchase = null;
            if ($spr && $spr->mapped_purchase_id) {
                $purchase = Purchase::withoutGlobalScopes()
                    ->with(['purchaseDetails.product', 'supplier'])
                    ->where('id', $spr->mapped_purchase_id)
                    ->lockForUpdate()
                    ->first();
            }
            if (!$purchase) {
                $purchase = Purchase::withoutGlobalScopes()
                    ->with(['purchaseDetails.product', 'supplier'])
                    ->where('source_sale_id', $order->id)
                    ->where('is_system_generated', true)
                    ->lockForUpdate()
                    ->firstOrFail();
            }

            if ($purchase->purchase_status !== InterShopTransferStatus::APPROVED) {
                throw new \RuntimeException('The purchase must be approved before completion.');
            }

            $motherShop = Shop::findOrFail($motherShopId);
            $childShop = Shop::findOrFail((int) $purchase->shop_id);
            $supplier = Supplier::withoutGlobalScope('shop')->findOrFail((int) $purchase->supplier_id);
            $systemCustomer = $order->customer;

            $logs = PaymentLog::query()->where('order_id', $order->id)->orderBy('id')->get();
            $paymentMethod1 = 'credit';
            $paymentMethod2 = null;
            $pay1 = 0.0;
            $pay2 = 0.0;
            $shopBankId1 = null;
            $shopBankId2 = null;
            if ($logs->count() >= 1) {
                $pay1 = (float) $logs[0]->amount_paid;
                $paymentMethod1 = $this->normalizeLedgerPaymentMethod((string) ($logs[0]->payment_method ?? 'credit'));
                $shopBankId1 = $logs[0]->shop_bank_id ? (int) $logs[0]->shop_bank_id : null;
            } else {
                $pay1 = (float) $order->pay;
                $paymentMethod1 = $this->normalizeLedgerPaymentMethod((string) ($order->payment_status ?? 'credit'));
            }
            if ($logs->count() >= 2) {
                $pay2 = (float) $logs[1]->amount_paid;
                $paymentMethod2 = $this->normalizeLedgerPaymentMethod((string) ($logs[1]->payment_method ?? ''));
                $shopBankId2 = $logs[1]->shop_bank_id ? (int) $logs[1]->shop_bank_id : null;
            }

            if ($pay1 > 0) {
                $this->salePostingService->postSale($order, $pay1, $paymentMethod1, $shopBankId1);
            } else {
                $this->salePostingService->postSale($order, 0, 'credit');
            }
            if ($pay2 > 0 && $paymentMethod2) {
                $this->salePostingService->postSale($order, $pay2, $paymentMethod2, $shopBankId2);
            }

            $due = (float) $order->due;
            if ($due > 0 && $systemCustomer) {
                $creditService->addPending($systemCustomer, $due);
            }

            foreach ($order->orderDetails as $detail) {
                $motherProduct = Product::withoutGlobalScope('shop')
                    ->lockForUpdate()
                    ->findOrFail((int) $detail->product_id);
                $qty = (float) $detail->quantity;
                $unit = (float) $detail->unitcost;

                Product::where('id', $motherProduct->id)->update([
                    'product_store' => DB::raw('product_store - ' . $qty),
                    'reserved_stock' => DB::raw('GREATEST(0, reserved_stock - ' . $qty . ')'),
                ]);

                StockLog::create([
                    'shop_id' => $motherShop->id,
                    'product_id' => $motherProduct->id,
                    'supplier_id' => null,
                    'qty' => (int) $qty,
                    'direction' => 'out',
                    'source_type' => 'mother_sale',
                    'source_id' => (string) $order->id,
                    'price' => $unit,
                    'stock_qty' => -(int) $qty,
                ]);

                $childProduct = $this->findChildShopProductForMotherSale($childShop, $motherProduct);
                if (!$childProduct) {
                    throw new \RuntimeException('Child product mapping missing for product '.$motherProduct->product_name);
                }
                if (empty($childProduct->parent_product_id)) {
                    $childProduct->parent_product_id = $motherProduct->id;
                    $childProduct->save();
                }

                Product::withoutGlobalScope('shop')
                    ->where('id', $childProduct->id)
                    ->update(['product_store' => DB::raw('product_store + ' . $qty)]);
                $childProduct->refresh();
                $this->stockService->updateBuyingPriceAfterPurchaseIn(
                    $childProduct,
                    (int) $qty,
                    $unit
                );
            }

            $purchaseDate = $purchase->purchase_date instanceof Carbon
                ? $purchase->purchase_date->format('Y-m-d')
                : Carbon::parse($purchase->purchase_date)->format('Y-m-d');
            $purchaseNo = $purchase->purchase_no;
            $total = (float) $purchase->total;
            $pay = (float) $purchase->pay;
            $duePurchase = (float) $purchase->due;

            $paymentLogId = null;
            if ($pay > 0) {
                $paymentLog = PurchasePaymentLog::create([
                    'purchase_id' => $purchase->id,
                    'amount_paid' => $pay,
                    'type' => 'payment',
                ]);
                $paymentLogId = $paymentLog->id;
            }

            if (!AccountTransaction::where('source_type', AccountTransaction::SOURCE_PURCHASE)->where('source_id', $purchase->id)->exists()) {
                $descPurchase = 'Purchase '.$purchaseNo;
                AccountTransaction::create([
                    'shop_id' => $childShop->id,
                    'account_type' => AccountTransaction::ACCOUNT_TYPE_PURCHASE,
                    'account_ref_id' => null,
                    'direction' => AccountTransaction::DIRECTION_DEBIT,
                    'amount' => $total,
                    'source_type' => AccountTransaction::SOURCE_PURCHASE,
                    'source_id' => $purchase->id,
                    'description' => $descPurchase,
                    'transaction_date' => $purchaseDate,
                ]);
                if ($duePurchase > 0) {
                    AccountTransaction::create([
                        'shop_id' => $childShop->id,
                        'account_type' => AccountTransaction::ACCOUNT_TYPE_SUPPLIER,
                        'account_ref_id' => $supplier->id,
                        'direction' => AccountTransaction::DIRECTION_CREDIT,
                        'amount' => $duePurchase,
                        'source_type' => AccountTransaction::SOURCE_PURCHASE,
                        'source_id' => $purchase->id,
                        'description' => $descPurchase,
                        'transaction_date' => $purchaseDate,
                    ]);
                }
                if ($pay > 0 && $paymentLogId !== null) {
                    $accountType = $paymentMethod1 === 'bank' ? AccountTransaction::ACCOUNT_TYPE_BANK : AccountTransaction::ACCOUNT_TYPE_CASH;
                    AccountTransaction::create([
                        'shop_id' => $childShop->id,
                        'account_type' => $accountType,
                        'account_ref_id' => null,
                        'direction' => AccountTransaction::DIRECTION_CREDIT,
                        'amount' => $pay,
                        'source_type' => AccountTransaction::SOURCE_PURCHASE_PAYMENT,
                        'source_id' => $paymentLogId,
                        'description' => 'Purchase Payment '.$purchaseNo,
                        'transaction_date' => $purchaseDate,
                    ]);
                }
            }

            foreach ($purchase->purchaseDetails as $pd) {
                // Resolve without the shop global scope: completion runs in the mother-shop
                // request context, so the eager-loaded relation would be filtered to the mother
                // shop and return null for the child product, skipping the child stock log.
                $childProduct = Product::withoutGlobalScope('shop')->find((int) $pd->product_id);
                if (!$childProduct) {
                    continue;
                }
                $qty = (int) $pd->quantity;
                if ($qty <= 0) {
                    continue;
                }
                StockLog::create([
                    'shop_id' => $childShop->id,
                    'product_id' => $childProduct->id,
                    'supplier_id' => $supplier->id,
                    'qty' => $qty,
                    'direction' => 'in',
                    'source_type' => 'purchase',
                    'source_id' => (string) $purchase->id,
                    'price' => (float) ($pd->unitcost ?? 0),
                    'stock_qty' => $qty,
                ]);
            }

            if ($duePurchase > 0) {
                $supplierCreditService->addPending($supplier, $duePurchase);
            }
            if ($duePurchase > 0 && $systemCustomer) {
                $creditService->addPending($systemCustomer, $duePurchase);
            }

            $purchase->update(['purchase_status' => InterShopTransferStatus::COMPLETED]);
            $order->update(['order_status' => InterShopTransferStatus::COMPLETED]);

            if ($spr) {
                $spr->update(['status' => InterShopTransferStatus::COMPLETED]);
            }

            $this->notifyShop(
                $childShop->id,
                'order_completed',
                'Inter-shop transfer completed',
                $spr ? (int) $spr->id : null,
                null
            );
        });
    }

    public function cancelInterShopTransfer(int $orderId, ?int $actingUserShopId): void
    {
        DB::transaction(function () use ($orderId, $actingUserShopId) {
            $order = Order::withoutGlobalScopes()
                ->with('orderDetails')
                ->lockForUpdate()
                ->findOrFail($orderId);

            $spr = ShopPurchaseRequest::query()
                ->where('mother_shop_sale_id', $order->id)
                ->lockForUpdate()
                ->first();

            $purchase = null;
            if ($spr && $spr->mapped_purchase_id) {
                $purchase = Purchase::withoutGlobalScopes()
                    ->where('id', $spr->mapped_purchase_id)
                    ->lockForUpdate()
                    ->first();
            }
            if (!$purchase) {
                $purchase = Purchase::withoutGlobalScopes()
                    ->where('source_sale_id', $order->id)
                    ->where('is_system_generated', true)
                    ->lockForUpdate()
                    ->first();
            }

            if (!in_array($order->order_status, [InterShopTransferStatus::PENDING, InterShopTransferStatus::APPROVED], true)) {
                throw new \RuntimeException('Only pending or approved transfers can be cancelled.');
            }

            $childShopId = $spr ? (int) $spr->child_shop_id : ($purchase ? (int) $purchase->shop_id : null);
            if ($actingUserShopId !== null
                && (int) $actingUserShopId !== (int) $order->shop_id
                && ($childShopId === null || (int) $actingUserShopId !== $childShopId)) {
                throw new \RuntimeException('You are not allowed to cancel this transfer.');
            }

            foreach ($order->orderDetails as $detail) {
                $qty = (float) $detail->quantity;
                Product::where('id', $detail->product_id)->update([
                    'reserved_stock' => DB::raw('GREATEST(0, reserved_stock - '.$qty.')'),
                ]);
            }

            if ($purchase) {
                $purchase->update(['purchase_status' => InterShopTransferStatus::CANCELLED]);
            }
            $order->update(['order_status' => InterShopTransferStatus::CANCELLED]);

            if ($spr) {
                $spr->update(['status' => InterShopTransferStatus::CANCELLED]);
            }
        });
    }

    /**
     * Child shop cancels while the request is still pending (no mapped purchase yet, or purchase still pending).
     */
    public function cancelShopPurchaseRequestAsChild(int $requestId, int $childShopId): void
    {
        $spr = ShopPurchaseRequest::query()->findOrFail($requestId);
        if ((int) $spr->child_shop_id !== (int) $childShopId) {
            throw new \RuntimeException('You are not allowed to cancel this request.');
        }
        if ($spr->status !== InterShopTransferStatus::PENDING) {
            throw new \RuntimeException('Only pending requests can be cancelled from the child shop.');
        }
        $this->cancelInterShopTransfer((int) $spr->mother_shop_sale_id, $childShopId);
    }

    public function releaseReservationsForOrder(Order $order): void
    {
        $order->loadMissing('orderDetails');
        foreach ($order->orderDetails as $detail) {
            $qty = (float) $detail->quantity;
            Product::where('id', $detail->product_id)->update([
                'reserved_stock' => DB::raw('GREATEST(0, reserved_stock - '.$qty.')'),
            ]);
        }
    }

    public function findChildShopProductForMotherSale(Shop $childShop, Product $motherProduct): ?Product
    {
        $byParent = Product::withoutGlobalScope('shop')
            ->where('shop_id', $childShop->id)
            ->where('parent_product_id', $motherProduct->id)
            ->first();
        if ($byParent) {
            return $byParent;
        }

        if (!empty($motherProduct->product_code)) {
            $byCode = Product::withoutGlobalScope('shop')
                ->where('shop_id', $childShop->id)
                ->where('product_code', $motherProduct->product_code)
                ->first();
            if ($byCode) {
                return $byCode;
            }
        }

        return Product::withoutGlobalScope('shop')
            ->where('shop_id', $childShop->id)
            ->where('product_name', $motherProduct->product_name)
            ->first();
    }
}
