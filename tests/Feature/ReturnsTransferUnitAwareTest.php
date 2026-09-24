<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderDetails;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\PurchaseDetail;
use App\Models\PurchaseReturnDetail;
use App\Models\SaleReturnDetail;
use App\Models\Shop;
use App\Models\ShopPurchaseRequest;
use App\Models\StockLog;
use App\Models\User;
use App\Support\ActiveShop;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class ReturnsTransferUnitAwareTest extends TestCase
{
    use RefreshDatabase;

    private Shop $mother;

    private Shop $child;

    private Category $category;

    private User $motherUser;

    private User $childUser;

    private Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->mother = Shop::create([
            'name' => 'Phase 6 Mother',
            'address' => 'Mother address',
            'phone' => '03160000001',
            'owner_name' => 'Mother',
            'is_parent' => true,
            'status' => true,
        ]);

        $this->child = Shop::create([
            'name' => 'Phase 6 Child',
            'address' => 'Child address',
            'phone' => '03160000002',
            'owner_name' => 'Child',
            'is_parent' => false,
            'parent_shop_id' => $this->mother->id,
            'status' => true,
        ]);

        $this->category = Category::create([
            'name' => 'Phase 6 Category',
            'slug' => 'phase-6-category',
            'shop_id' => $this->mother->id,
        ]);

        foreach ([
            'advance.pos.menu' => 'pos',
            'orders.menu' => 'orders',
            'sale-returns.menu' => 'sale-returns',
            'purchases.menu' => 'purchases',
            'purchase_returns.create' => 'purchase-returns',
            'purchase_returns.view' => 'purchase-returns',
            'transfer_returns.approve' => 'transfer-returns',
        ] as $name => $group) {
            Permission::firstOrCreate(
                ['name' => $name, 'guard_name' => 'web'],
                ['group_name' => $group]
            );
        }

        $this->motherUser = User::create([
            'name' => 'Phase 6 Mother',
            'username' => 'phase6mother',
            'email' => 'phase6mother@example.com',
            'password' => bcrypt('password'),
            'shop_id' => $this->mother->id,
        ]);
        $this->motherUser->givePermissionTo([
            'advance.pos.menu',
            'orders.menu',
            'sale-returns.menu',
            'purchases.menu',
            'transfer_returns.approve',
        ]);

        $this->childUser = User::create([
            'name' => 'Phase 6 Child',
            'username' => 'phase6child',
            'email' => 'phase6child@example.com',
            'password' => bcrypt('password'),
            'shop_id' => $this->child->id,
        ]);
        $this->childUser->givePermissionTo([
            'purchases.menu',
            'purchase_returns.create',
            'purchase_returns.view',
            'orders.menu',
        ]);

        $this->customer = Customer::create([
            'name' => 'Phase 6 Customer',
            'email' => 'phase6customer@example.com',
            'phone' => '03160000003',
            'shop_id' => $this->mother->id,
            'is_walkin' => false,
        ]);
    }

    public function test_piece_sale_return_restores_whole_quantity(): void
    {
        $product = $this->makeProduct($this->mother, Product::UNIT_PIECE, '10', '30.00');
        $this->sell($product, '3', '30.00', '90.00');
        $detail = $this->latestOrderDetail($product);

        $this->actingAs($this->motherUser)->post(route('sale-returns.store'), $this->saleReturnPayload($detail, '2', '60.00'))
            ->assertRedirect(route('sale-returns.index'));

        $this->assertSame('9.000', $product->fresh()->product_store);
        $returnDetail = SaleReturnDetail::where('order_detail_id', $detail->id)->first();
        $this->assertNotNull($returnDetail);
        $this->assertSame('2.000', $returnDetail->quantity);
        $this->assertSame(Product::UNIT_PIECE, $returnDetail->unit);
        $this->assertSame('2 pieces', $returnDetail->quantityWithUnit());
    }

    public function test_kg_sale_return_0_750_increases_stock_and_snapshots_unit(): void
    {
        $product = $this->makeProduct($this->mother, Product::UNIT_KG, '10.000', '250.00');
        $this->sell($product, '0.750', '250.00', '187.50');
        $this->assertSame('9.250', $product->fresh()->product_store);
        $detail = $this->latestOrderDetail($product);

        $this->actingAs($this->motherUser)->post(route('sale-returns.store'), $this->saleReturnPayload($detail, '0.750', '187.50'))
            ->assertRedirect(route('sale-returns.index'));

        $this->assertSame('10.000', $product->fresh()->product_store);
        $returnDetail = SaleReturnDetail::where('order_detail_id', $detail->id)->first();
        $this->assertSame('0.750', $returnDetail->quantity);
        $this->assertSame(Product::UNIT_KG, $returnDetail->unit);
        $this->assertSame('0.750 kg', $returnDetail->quantityWithUnit());

        $log = StockLog::where('product_id', $product->id)->where('source_type', 'sale_return')->first();
        $this->assertNotNull($log);
        $this->assertSame('0.750', $log->qty);
        $this->assertSame(Product::UNIT_KG, $log->unit);
        $this->assertSame('0.750', $log->stock_qty);
        $this->assertSame('in', $log->direction);
    }

    public function test_kg_sale_return_1_500_works(): void
    {
        $product = $this->makeProduct($this->mother, Product::UNIT_KG, '10.000', '250.00');
        $this->sell($product, '2.500', '250.00', '625.00');
        $detail = $this->latestOrderDetail($product);

        $this->actingAs($this->motherUser)->post(route('sale-returns.store'), $this->saleReturnPayload($detail, '1.500', '375.00'))
            ->assertRedirect(route('sale-returns.index'));

        $this->assertSame('9.000', $product->fresh()->product_store);
        $this->assertSame('1.500', SaleReturnDetail::where('product_id', $product->id)->first()->quantity);
    }

    public function test_fractional_piece_sale_return_is_rejected(): void
    {
        $product = $this->makeProduct($this->mother, Product::UNIT_PIECE, '10', '30.00');
        $this->sell($product, '3', '30.00', '90.00');
        $detail = $this->latestOrderDetail($product);

        $this->actingAs($this->motherUser)->post(route('sale-returns.store'), $this->saleReturnPayload($detail, '1.5', '45.00'))
            ->assertSessionHasErrors();

        $this->assertSame('7.000', $product->fresh()->product_store);
        $this->assertSame(0, SaleReturnDetail::count());
    }

    public function test_kg_sale_return_more_than_three_decimals_is_rejected(): void
    {
        $product = $this->makeProduct($this->mother, Product::UNIT_KG, '10.000', '250.00');
        $this->sell($product, '2.000', '250.00', '500.00');
        $detail = $this->latestOrderDetail($product);

        $this->actingAs($this->motherUser)->post(route('sale-returns.store'), $this->saleReturnPayload($detail, '0.7505', '187.63'))
            ->assertSessionHasErrors();

        $this->assertSame('8.000', $product->fresh()->product_store);
        $this->assertSame(0, SaleReturnDetail::count());
    }

    public function test_sale_return_above_returnable_quantity_is_rejected(): void
    {
        $product = $this->makeProduct($this->mother, Product::UNIT_KG, '10.000', '250.00');
        $this->sell($product, '2.500', '250.00', '625.00');
        $detail = $this->latestOrderDetail($product);

        $this->actingAs($this->motherUser)->post(route('sale-returns.store'), $this->saleReturnPayload($detail, '0.750', '187.50'))
            ->assertRedirect(route('sale-returns.index'));

        $this->actingAs($this->motherUser)->post(route('sale-returns.store'), $this->saleReturnPayload($detail, '2.000', '500.00'))
            ->assertSessionHasErrors();

        $this->assertSame('8.250', $product->fresh()->product_store);
        $this->assertSame(1, SaleReturnDetail::where('order_detail_id', $detail->id)->count());
    }

    public function test_piece_purchase_return_stores_piece_snapshot(): void
    {
        $motherProduct = $this->makeProduct($this->mother, Product::UNIT_PIECE, '20', '40.00', 'Piece Transfer');
        $order = $this->completeTransfer($motherProduct, '4', '40.00', '160.00');
        $purchaseDetail = $this->childPurchaseDetail($order);

        ActiveShop::set($this->child->id);
        $this->actingAs($this->childUser)->post(route('purchase-returns.store'), $this->purchaseReturnPayload($purchaseDetail, '2', '80.00'))
            ->assertRedirect(route('purchase-returns.index'));

        $returnDetail = PurchaseReturnDetail::where('product_id', $purchaseDetail->product_id)->first();
        $this->assertNotNull($returnDetail);
        $this->assertSame('2.000', $returnDetail->quantity);
        $this->assertSame(Product::UNIT_PIECE, $returnDetail->unit);
        $this->assertSame('pending', $returnDetail->purchaseReturn->status);
    }

    public function test_kg_purchase_return_and_transfer_return_move_exact_decimals(): void
    {
        $motherProduct = $this->makeProduct($this->mother, Product::UNIT_KG, '10.000', '250.00', 'Sugar Transfer');
        $order = $this->completeTransfer($motherProduct, '0.750', '250.00', '187.50', '5.000');

        $this->assertSame('9.250', $motherProduct->fresh()->product_store);
        $child = $this->childProduct($motherProduct);
        $this->assertSame('5.750', $child->fresh()->product_store);
        $this->assertSame(Product::UNIT_KG, $child->unit);

        $orderDetail = $this->latestOrderDetail($motherProduct);
        $this->assertSame('0.750', $orderDetail->quantity);
        $this->assertSame(Product::UNIT_KG, $orderDetail->unit);

        $purchaseDetail = $this->childPurchaseDetail($order);
        $this->assertSame('0.750', $purchaseDetail->quantity);
        $this->assertSame(Product::UNIT_KG, $purchaseDetail->unit);

        $motherLog = StockLog::where('product_id', $motherProduct->id)->where('source_type', 'mother_sale')->first();
        $this->assertSame('0.750', $motherLog->qty);
        $this->assertSame(Product::UNIT_KG, $motherLog->unit);
        $this->assertSame('-0.750', $motherLog->stock_qty);

        $childLog = StockLog::where('product_id', $child->id)->where('source_type', 'purchase')->first();
        $this->assertSame('0.750', $childLog->qty);
        $this->assertSame(Product::UNIT_KG, $childLog->unit);

        ActiveShop::set($this->child->id);
        $this->actingAs($this->childUser)->post(route('purchase-returns.store'), $this->purchaseReturnPayload($purchaseDetail, '0.750', '187.50'))
            ->assertRedirect(route('purchase-returns.index'));

        $returnDetail = PurchaseReturnDetail::where('product_id', $child->id)->first();
        $this->assertSame('0.750', $returnDetail->quantity);
        $this->assertSame(Product::UNIT_KG, $returnDetail->unit);
        $this->assertSame('5.750', $child->fresh()->product_store);

        ActiveShop::set($this->mother->id);
        $this->actingAs($this->motherUser)->post(route('transfer-returns.approve', $returnDetail->purchase_return_id))
            ->assertRedirect(route('transfer-returns.pending'));

        $this->assertSame('5.000', $child->fresh()->product_store);
        $this->assertSame('10.000', $motherProduct->fresh()->product_store);

        $out = StockLog::where('product_id', $child->id)->where('source_type', 'purchase_return')->first();
        $this->assertNotNull($out);
        $this->assertSame('0.750', $out->qty);
        $this->assertSame('-0.750', $out->stock_qty);
        $this->assertSame(Product::UNIT_KG, $out->unit);
        $this->assertSame('out', $out->direction);

        $in = StockLog::where('product_id', $motherProduct->id)->where('source_type', 'sale_return')->first();
        $this->assertSame('0.750', $in->qty);
        $this->assertSame(Product::UNIT_KG, $in->unit);
        $this->assertSame('in', $in->direction);
    }

    public function test_kg_purchase_return_1_500_is_accepted_when_eligible(): void
    {
        $motherProduct = $this->makeProduct($this->mother, Product::UNIT_KG, '10.000', '250.00', 'Sugar 1500');
        $order = $this->completeTransfer($motherProduct, '1.500', '250.00', '375.00');
        $purchaseDetail = $this->childPurchaseDetail($order);

        ActiveShop::set($this->child->id);
        $this->actingAs($this->childUser)->post(route('purchase-returns.store'), $this->purchaseReturnPayload($purchaseDetail, '1.500', '375.00'))
            ->assertRedirect(route('purchase-returns.index'));

        $this->assertSame('1.500', PurchaseReturnDetail::where('product_id', $purchaseDetail->product_id)->first()->quantity);
    }

    public function test_fractional_piece_purchase_return_is_rejected(): void
    {
        $motherProduct = $this->makeProduct($this->mother, Product::UNIT_PIECE, '20', '40.00', 'Piece Reject');
        $order = $this->completeTransfer($motherProduct, '4', '40.00', '160.00');
        $purchaseDetail = $this->childPurchaseDetail($order);

        ActiveShop::set($this->child->id);
        $this->actingAs($this->childUser)->post(route('purchase-returns.store'), $this->purchaseReturnPayload($purchaseDetail, '1.5', '60.00'))
            ->assertSessionHasErrors();

        $this->assertSame(0, PurchaseReturnDetail::count());
    }

    public function test_kg_purchase_return_more_than_three_decimals_is_rejected(): void
    {
        $motherProduct = $this->makeProduct($this->mother, Product::UNIT_KG, '10.000', '250.00', 'Sugar Reject');
        $order = $this->completeTransfer($motherProduct, '2.000', '250.00', '500.00');
        $purchaseDetail = $this->childPurchaseDetail($order);

        ActiveShop::set($this->child->id);
        $this->actingAs($this->childUser)->post(route('purchase-returns.store'), $this->purchaseReturnPayload($purchaseDetail, '0.7505', '187.63'))
            ->assertSessionHasErrors();

        $this->assertSame(0, PurchaseReturnDetail::count());
    }

    public function test_purchase_return_above_eligible_quantity_is_rejected(): void
    {
        $motherProduct = $this->makeProduct($this->mother, Product::UNIT_KG, '10.000', '250.00', 'Sugar Limit');
        $order = $this->completeTransfer($motherProduct, '5.000', '250.00', '1250.00');
        $purchaseDetail = $this->childPurchaseDetail($order);

        ActiveShop::set($this->child->id);
        $this->actingAs($this->childUser)->post(route('purchase-returns.store'), $this->purchaseReturnPayload($purchaseDetail, '1.250', '312.50'))
            ->assertRedirect(route('purchase-returns.index'));

        ActiveShop::set($this->child->id);
        $this->actingAs($this->childUser)->post(route('purchase-returns.store'), $this->purchaseReturnPayload($purchaseDetail, '4.000', '1000.00'))
            ->assertSessionHasErrors();

        $this->assertSame(1, PurchaseReturnDetail::where('product_id', $purchaseDetail->product_id)->count());
    }

    public function test_fractional_piece_transfer_is_rejected(): void
    {
        $product = $this->makeProduct($this->mother, Product::UNIT_PIECE, '20', '40.00', 'Piece Bad Transfer');

        $this->actingAs($this->motherUser)->post(route('invoice.store'), $this->transferPayload($product, '1.5', '40.00', '60.00'))
            ->assertSessionHasErrors();

        $this->assertSame('20.000', $product->fresh()->product_store);
        $this->assertSame(0, Order::count());
    }

    public function test_kg_transfer_more_than_three_decimals_is_rejected(): void
    {
        $product = $this->makeProduct($this->mother, Product::UNIT_KG, '10.000', '250.00', 'Sugar Bad Transfer');

        $this->actingAs($this->motherUser)->post(route('invoice.store'), $this->transferPayload($product, '0.7505', '250.00', '187.63'))
            ->assertSessionHasErrors();

        $this->assertSame('10.000', $product->fresh()->product_store);
        $this->assertSame('0.000', $product->fresh()->reserved_stock);
        $this->assertSame(0, Order::count());
    }

    public function test_piece_transfer_moves_whole_quantity(): void
    {
        $motherProduct = $this->makeProduct($this->mother, Product::UNIT_PIECE, '20', '40.00', 'Piece Ok Transfer');
        $this->completeTransfer($motherProduct, '3', '40.00', '120.00');

        $this->assertSame('17.000', $motherProduct->fresh()->product_store);
        $child = $this->childProduct($motherProduct);
        $this->assertSame('3.000', $child->fresh()->product_store);
        $this->assertSame(Product::UNIT_PIECE, $child->unit);
        $this->assertSame(Product::UNIT_PIECE, $this->latestOrderDetail($motherProduct)->unit);
    }

    private function sell(Product $product, string $qty, string $price, string $total): void
    {
        $this->actingAs($this->motherUser)->post(route('invoice.store'), [
            'customer_id' => $this->customer->id,
            'order_date' => now()->format('Y-m-d H:i:s'),
            'payment_method_1' => 'credit',
            'pay_1' => 0,
            'vat' => 0,
            'invoice_discount' => 0,
            'products' => [[
                'product_id' => $product->id,
                'quantity' => $qty,
                'unit_price' => $price,
                'total' => $total,
                'item_discount' => 0,
            ]],
        ])->assertRedirect(route('invoice.create'));
    }

    private function completeTransfer(Product $motherProduct, string $qty, string $price, string $total, ?string $childOpeningStock = null): Order
    {
        $this->actingAs($this->motherUser)->post(route('invoice.store'), $this->transferPayload($motherProduct, $qty, $price, $total))
            ->assertRedirect(route('invoice.create'));

        if ($childOpeningStock !== null) {
            $this->childProduct($motherProduct)->update(['product_store' => $childOpeningStock]);
        }

        $order = Order::where('shop_id', $this->mother->id)->latest('id')->first();
        $this->assertNotNull($order);

        $request = ShopPurchaseRequest::where('mother_shop_sale_id', $order->id)->first();
        $this->assertNotNull($request);

        ActiveShop::set($this->child->id);
        $this->actingAs($this->childUser)->post(route('shop-purchase-requests.approve', $request->id))
            ->assertRedirect(route('shop-purchase-requests.show', $request->id));

        ActiveShop::set($this->mother->id);
        $this->actingAs($this->motherUser)->post(route('order.interShop.complete', $order->id))
            ->assertRedirect(route('order.orderDetails', $order->id));

        return $order->fresh();
    }

    /**
     * @return array<string, mixed>
     */
    private function transferPayload(Product $product, string $qty, string $price, string $total): array
    {
        return [
            'shop_id' => $this->child->id,
            'order_date' => now()->format('Y-m-d H:i:s'),
            'payment_method_1' => 'credit',
            'pay_1' => 0,
            'vat' => 0,
            'invoice_discount' => 0,
            'products' => [[
                'product_id' => $product->id,
                'quantity' => $qty,
                'unit_price' => $price,
                'total' => $total,
                'item_discount' => 0,
            ]],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function saleReturnPayload(OrderDetails $detail, string $qty, string $total): array
    {
        return [
            'order_id' => $detail->order_id,
            'return_date' => now()->format('Y-m-d H:i:s'),
            'vat' => 0,
            'invoice_discount' => 0,
            'products' => [[
                'order_detail_id' => $detail->id,
                'product_id' => $detail->product_id,
                'quantity' => $qty,
                'unit_price' => $detail->unitcost,
                'item_discount' => 0,
                'total' => $total,
            ]],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function purchaseReturnPayload(PurchaseDetail $detail, string $qty, string $total): array
    {
        return [
            'purchase_id' => $detail->purchase_id,
            'return_date' => now()->format('Y-m-d H:i:s'),
            'items' => [[
                'product_id' => $detail->product_id,
                'quantity' => $qty,
                'price' => $detail->unitcost,
                'total' => $total,
            ]],
        ];
    }

    private function makeProduct(Shop $shop, string $unit, string $stock, string $price, string $name = 'Phase 6 Item'): Product
    {
        return Product::create([
            'product_name' => $name,
            'category_id' => $this->category->id,
            'shop_id' => $shop->id,
            'product_code' => 'P6-'.(6000 + Product::withoutGlobalScope('shop')->count()),
            'product_store' => $stock,
            'reserved_stock' => '0',
            'low_stock_warning' => '1',
            'buying_price' => '10.0000',
            'selling_price' => $price,
            'status' => 'active',
            'unit' => $unit,
        ]);
    }

    private function latestOrderDetail(Product $product): OrderDetails
    {
        $detail = OrderDetails::where('product_id', $product->id)->latest('id')->first();
        $this->assertNotNull($detail);

        return $detail;
    }

    private function childProduct(Product $motherProduct): Product
    {
        $child = Product::withoutGlobalScope('shop')
            ->where('shop_id', $this->child->id)
            ->where('parent_product_id', $motherProduct->id)
            ->first();
        $this->assertNotNull($child);

        return $child;
    }

    private function childPurchaseDetail(Order $order): PurchaseDetail
    {
        $purchase = Purchase::withoutGlobalScope('shop')->where('source_sale_id', $order->id)->first();
        $this->assertNotNull($purchase);
        $detail = PurchaseDetail::where('purchase_id', $purchase->id)->first();
        $this->assertNotNull($detail);

        return $detail;
    }
}
