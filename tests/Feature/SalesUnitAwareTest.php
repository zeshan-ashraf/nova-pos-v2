<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderDetails;
use App\Models\Product;
use App\Models\Shop;
use App\Models\StockLog;
use App\Models\User;
use Gloudemans\Shoppingcart\Facades\Cart;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class SalesUnitAwareTest extends TestCase
{
    use RefreshDatabase;

    private Shop $shop;

    private Category $category;

    private User $user;

    private Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->shop = Shop::create([
            'name' => 'Phase 4 Shop',
            'address' => 'Test address',
            'phone' => '03140000001',
            'owner_name' => 'Owner',
            'is_parent' => true,
            'status' => true,
        ]);

        $this->category = Category::create([
            'name' => 'Phase 4 Category',
            'slug' => 'phase-4-category',
            'shop_id' => $this->shop->id,
        ]);

        foreach (['advance.pos.menu' => 'pos', 'orders.menu' => 'orders', 'pos.menu' => 'pos'] as $name => $group) {
            Permission::firstOrCreate(
                ['name' => $name, 'guard_name' => 'web'],
                ['group_name' => $group]
            );
        }

        $this->user = User::create([
            'name' => 'Phase 4 Admin',
            'username' => 'phase4admin',
            'email' => 'phase4admin@example.com',
            'password' => bcrypt('password'),
            'shop_id' => $this->shop->id,
        ]);
        $this->user->givePermissionTo(['advance.pos.menu', 'orders.menu', 'pos.menu']);

        $this->customer = Customer::create([
            'name' => 'Phase 4 Customer',
            'email' => 'phase4customer@example.com',
            'phone' => '03140000002',
            'shop_id' => $this->shop->id,
            'is_walkin' => false,
        ]);
    }

    public function test_piece_sale_of_one_succeeds(): void
    {
        $product = $this->makePieceProduct('100', '30.00');

        $this->postInvoice($product, '1', '30.00', '30.00')->assertRedirect(route('invoice.create'));

        $this->assertSame('99.000', $product->fresh()->product_store);
        $detail = $this->latestDetail($product);
        $this->assertSame('1.000', $detail->quantity);
        $this->assertSame(Product::UNIT_PIECE, $detail->unit);
        $this->assertSame('30.00', $detail->total);
    }

    public function test_piece_sale_of_multiple_succeeds_and_prices_correctly(): void
    {
        $product = $this->makePieceProduct('100', '30.00');

        $this->postInvoice($product, '3', '30.00', '90.00')->assertRedirect(route('invoice.create'));

        $this->assertSame('97.000', $product->fresh()->product_store);
        $detail = $this->latestDetail($product);
        $this->assertSame('3.000', $detail->quantity);
        $this->assertSame(Product::UNIT_PIECE, $detail->unit);
        $this->assertSame('90.00', $detail->total);
        $this->assertSame('3 pieces', $detail->quantityWithUnit());
        $this->assertSame('30.00 / piece', $detail->unitPriceWithUnit());
    }

    public function test_fractional_piece_quantity_is_rejected(): void
    {
        $product = $this->makePieceProduct('100', '30.00');

        $this->postInvoice($product, '1.5', '30.00', '45.00')->assertSessionHasErrors();
        $this->assertSame('100.000', $product->fresh()->product_store);
        $this->assertSame(0, OrderDetails::where('product_id', $product->id)->count());
    }

    public function test_insufficient_piece_stock_is_rejected(): void
    {
        $product = $this->makePieceProduct('2', '30.00');

        $this->postInvoice($product, '3', '30.00', '90.00')->assertSessionHasErrors('products');
        $this->assertSame('2.000', $product->fresh()->product_store);
    }

    public function test_kg_sale_0_750_scenario_a(): void
    {
        $product = $this->makeKgProduct('10.000', '250.00');

        $this->postInvoice($product, '0.750', '250.00', '187.50')->assertRedirect(route('invoice.create'));

        $this->assertSame('9.250', $product->fresh()->product_store);
        $detail = $this->latestDetail($product);
        $this->assertSame('0.750', $detail->quantity);
        $this->assertSame(Product::UNIT_KG, $detail->unit);
        $this->assertSame('187.50', $detail->total);
        $this->assertSame('0.750 kg', $detail->quantityWithUnit());
        $this->assertSame('250.00 / kg', $detail->unitPriceWithUnit());

        $log = $this->latestSaleLog($product);
        $this->assertSame('0.750', $log->qty);
        $this->assertSame(Product::UNIT_KG, $log->unit);
        $this->assertSame('-0.750', $log->stock_qty);
        $this->assertSame('out', $log->direction);
        $this->assertSame('sale', $log->source_type);
    }

    public function test_kg_sale_1_500_scenario_b(): void
    {
        $product = $this->makeKgProduct('2.000', '250.00');

        $this->postInvoice($product, '1.500', '250.00', '375.00')->assertRedirect(route('invoice.create'));

        $this->assertSame('0.500', $product->fresh()->product_store);
        $detail = $this->latestDetail($product);
        $this->assertSame('1.500', $detail->quantity);
        $this->assertSame(Product::UNIT_KG, $detail->unit);
        $this->assertSame('375.00', $detail->total);
    }

    public function test_kg_sale_minimum_0_001(): void
    {
        $product = $this->makeKgProduct('10.000', '250.00');

        $this->postInvoice($product, '0.001', '250.00', '0.25')->assertRedirect(route('invoice.create'));

        $this->assertSame('9.999', $product->fresh()->product_store);
        $this->assertSame('0.001', $this->latestDetail($product)->quantity);
    }

    public function test_kg_sale_zero_quantity_is_rejected(): void
    {
        $product = $this->makeKgProduct('10.000', '250.00');

        $this->postInvoice($product, '0.000', '250.00', '0.00')->assertSessionHasErrors();
        $this->assertSame('10.000', $product->fresh()->product_store);
        $this->assertSame(0, Order::count());
    }

    public function test_kg_sale_more_than_three_decimals_is_rejected(): void
    {
        $product = $this->makeKgProduct('10.000', '250.00');

        $this->postInvoice($product, '0.7505', '250.00', '187.63')->assertSessionHasErrors();
        $this->assertSame('10.000', $product->fresh()->product_store);
        $this->assertSame(0, OrderDetails::where('product_id', $product->id)->count());
    }

    public function test_insufficient_kg_stock_is_rejected(): void
    {
        $product = $this->makeKgProduct('0.500', '250.00');

        $this->postInvoice($product, '0.750', '250.00', '187.50')->assertSessionHasErrors('products');
        $this->assertSame('0.500', $product->fresh()->product_store);
    }

    public function test_product_search_includes_unit(): void
    {
        $piece = $this->makePieceProduct('100', '30.00', 'Search Piece');
        $kg = $this->makeKgProduct('10.000', '250.00', 'Search Sugar');

        $pieceSearch = $this->actingAs($this->user)->getJson(route('api.products.search', ['q' => 'Search Piece']));
        $pieceSearch->assertOk();
        $pieceRow = collect($pieceSearch->json('results'))->firstWhere('id', $piece->id);
        $this->assertNotNull($pieceRow);
        $this->assertSame(Product::UNIT_PIECE, $pieceRow['unit']);

        $kgSearch = $this->actingAs($this->user)->getJson(route('api.products.search', ['q' => 'Search Sugar']));
        $kgSearch->assertOk();
        $kgRow = collect($kgSearch->json('results'))->firstWhere('id', $kg->id);
        $this->assertNotNull($kgRow);
        $this->assertSame(Product::UNIT_KG, $kgRow['unit']);
        $this->assertEquals(10, $kgRow['stock']);
    }

    public function test_advance_pos_quantity_input_is_unit_aware(): void
    {
        $page = $this->actingAs($this->user)->get(route('invoice.create'));
        $page->assertOk();
        $page->assertSee('applyQuantityInputForUnit', false);
        $page->assertSee("step: '0.001'", false);
        $page->assertSee("min: '0.001'", false);
        $page->assertSee('class="product-unit"', false);
        $page->assertSee('Piece quantity must be a whole number.', false);
        $page->assertSee("return n.toFixed(3) + ' kg';", false);
        $html = $page->getContent();
        $this->assertStringNotContainsString('parseInt($(this).val())', $html);
        $this->assertStringNotContainsString('parseInt(quantity', $html);
    }

    public function test_kg_hold_quantity_survives_restore(): void
    {
        $product = $this->makeKgProduct('10.000', '250.00', 'Hold Sugar');

        $this->actingAs($this->user)->post(route('invoice.hold'), $this->invoicePayload(
            $product,
            '0.750',
            '250.00',
            '187.50'
        ))->assertRedirect(route('invoice.create'));

        $this->assertSame('9.250', $product->fresh()->product_store);
        $order = Order::latest('id')->first();
        $this->assertSame('hold', $order->order_status);
        $detail = $this->latestDetail($product);
        $this->assertSame('0.750', $detail->quantity);
        $this->assertSame(Product::UNIT_KG, $detail->unit);

        $reload = $this->actingAs($this->user)->get(route('order.reload', $order->id));
        $reload->assertOk();
        $reload->assertSee('0.750', false);
        $payload = $reload->viewData('invoiceReloadPayload');
        $this->assertSame('0.750', (string) $payload['order_details'][0]['quantity']);
        $this->assertSame(Product::UNIT_KG, $payload['order_details'][0]['unit']);
    }

    public function test_kg_invoice_displays_quantity_and_unit_price(): void
    {
        $product = $this->makeKgProduct('10.000', '250.00', 'Invoice Sugar');
        $this->postInvoice($product, '0.750', '250.00', '187.50')->assertRedirect(route('invoice.create'));
        $order = Order::latest('id')->first();

        $a4 = $this->actingAs($this->user)->get(route('order.printA4', $order->id));
        $a4->assertOk();
        $a4->assertSee('0.750 kg', false);
        $a4->assertSee('250.00 / kg', false);
        $a4->assertSee('187.50', false);

        $receipt = $this->actingAs($this->user)->get(route('order.printReceipt', $order->id));
        $receipt->assertOk();
        $receipt->assertSee('0.750 kg', false);

        $details = $this->actingAs($this->user)->get(route('order.orderDetails', $order->id));
        $details->assertOk();
        $details->assertSee('0.750 kg', false);
        $details->assertSee('250.00 / kg', false);
    }

    public function test_piece_invoice_display_remains_correct(): void
    {
        $product = $this->makePieceProduct('100', '30.00', 'Invoice Item A');
        $this->postInvoice($product, '3', '30.00', '90.00')->assertRedirect(route('invoice.create'));
        $order = Order::latest('id')->first();

        $a4 = $this->actingAs($this->user)->get(route('order.printA4', $order->id));
        $a4->assertOk();
        $a4->assertSee('3 pieces', false);
        $a4->assertSee('30.00 / piece', false);
        $a4->assertSee('90.00', false);
    }

    public function test_pos_cart_preserves_kg_decimal_quantity(): void
    {
        $product = $this->makeKgProduct('10.000', '250.00', 'POS Sugar');

        $this->actingAs($this->user)->post(route('pos.addCart'), [
            'id' => $product->id,
            'name' => $product->product_name,
            'price' => $product->selling_price,
        ])->assertRedirect();

        $rowId = Cart::content()->first()->rowId;
        $this->assertSame(Product::UNIT_KG, Cart::content()->first()->options->unit);

        $this->actingAs($this->user)->post(route('pos.updateCart', $rowId), [
            'qty' => '0.750',
        ])->assertRedirect();

        $item = Cart::get($rowId);
        $this->assertEquals(0.75, (float) $item->qty);
        $this->assertSame(Product::UNIT_KG, $item->options->unit);

        $page = $this->actingAs($this->user)->get(route('pos.index'));
        $page->assertOk();
        $page->assertSee('step="0.001"', false);
        $page->assertSee('min="0.001"', false);
        $page->assertSee('0.75', false);
        $page->assertSee('10.000 kg', false);

        $preview = $this->actingAs($this->user)->post(route('pos.createInvoice'), [
            'customer_id' => $this->customer->id,
        ]);
        $preview->assertOk();
        $preview->assertSee('0.750 kg', false);
    }

    public function test_pos_cart_rejects_fractional_piece_quantity(): void
    {
        $product = $this->makePieceProduct('100', '30.00', 'POS Piece');

        $this->actingAs($this->user)->post(route('pos.addCart'), [
            'id' => $product->id,
            'name' => $product->product_name,
            'price' => $product->selling_price,
        ])->assertRedirect();

        $rowId = Cart::content()->first()->rowId;
        $this->actingAs($this->user)->post(route('pos.updateCart', $rowId), [
            'qty' => '1.5',
        ])->assertSessionHasErrors('qty');

        $this->assertEquals(1, (float) Cart::get($rowId)->qty);
    }

    public function test_classic_pos_store_order_sells_kg_without_truncation(): void
    {
        $product = $this->makeKgProduct('10.000', '250.00', 'Classic POS Sugar');

        $this->actingAs($this->user)->post(route('pos.addCart'), [
            'id' => $product->id,
            'name' => $product->product_name,
            'price' => $product->selling_price,
        ]);
        $rowId = Cart::content()->first()->rowId;
        $this->actingAs($this->user)->post(route('pos.updateCart', $rowId), ['qty' => '0.750']);

        $this->actingAs($this->user)->post(route('pos.storeOrder'), [
            'customer_id' => $this->customer->id,
            'payment_status' => 'HandCash',
            'pay' => Cart::total(),
        ])->assertRedirect(route('dashboard'));

        $this->assertSame('9.250', $product->fresh()->product_store);
        $detail = $this->latestDetail($product);
        $this->assertSame('0.750', $detail->quantity);
        $this->assertSame(Product::UNIT_KG, $detail->unit);
    }

    public function test_invoice_uses_order_detail_unit_snapshot_not_current_product_unit_for_display(): void
    {
        $product = $this->makeKgProduct('10.000', '250.00', 'Snapshot Sugar');
        $this->postInvoice($product, '0.750', '250.00', '187.50');

        $detail = $this->latestDetail($product);
        $this->assertSame(Product::UNIT_KG, $detail->snapshotUnit());
        $this->assertSame('0.750 kg', $detail->quantityWithUnit());
    }

    private function postInvoice(Product $product, string $qty, string $unitPrice, string $total)
    {
        return $this->actingAs($this->user)->post(
            route('invoice.store'),
            $this->invoicePayload($product, $qty, $unitPrice, $total)
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function invoicePayload(Product $product, string $qty, string $unitPrice, string $total): array
    {
        return [
            'customer_id' => $this->customer->id,
            'order_date' => now()->format('Y-m-d H:i:s'),
            'payment_method_1' => 'cash',
            'pay_1' => $total,
            'vat' => 0,
            'invoice_discount' => 0,
            'products' => [
                [
                    'product_id' => $product->id,
                    'quantity' => $qty,
                    'unit_price' => $unitPrice,
                    'total' => $total,
                    'item_discount' => 0,
                ],
            ],
        ];
    }

    private function makePieceProduct(string $stock, string $sellingPrice, string $name = 'Item A'): Product
    {
        return $this->makeProduct([
            'product_name' => $name,
            'unit' => Product::UNIT_PIECE,
            'product_store' => $stock,
            'selling_price' => $sellingPrice,
            'buying_price' => '20.0000',
        ]);
    }

    private function makeKgProduct(string $stock, string $sellingPrice, string $name = 'Sugar'): Product
    {
        return $this->makeProduct([
            'product_name' => $name,
            'unit' => Product::UNIT_KG,
            'product_store' => $stock,
            'selling_price' => $sellingPrice,
            'buying_price' => '200.0000',
        ]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function makeProduct(array $overrides = []): Product
    {
        return Product::create(array_merge([
            'product_name' => 'Phase 4 Product',
            'category_id' => $this->category->id,
            'shop_id' => $this->shop->id,
            'product_code' => 'P4-'.(4000 + Product::count()),
            'product_store' => '0',
            'reserved_stock' => '0',
            'low_stock_warning' => '1',
            'buying_price' => '10.0000',
            'selling_price' => '20.00',
            'status' => 'active',
            'unit' => Product::UNIT_PIECE,
        ], $overrides));
    }

    private function latestDetail(Product $product): OrderDetails
    {
        $detail = OrderDetails::where('product_id', $product->id)->latest('id')->first();
        $this->assertNotNull($detail);

        return $detail;
    }

    private function latestSaleLog(Product $product): StockLog
    {
        $log = StockLog::where('product_id', $product->id)
            ->where('source_type', 'sale')
            ->latest('id')
            ->first();
        $this->assertNotNull($log);

        return $log;
    }
}
