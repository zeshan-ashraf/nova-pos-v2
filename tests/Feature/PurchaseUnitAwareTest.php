<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\PurchaseDetail;
use App\Models\Shop;
use App\Models\StockLog;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class PurchaseUnitAwareTest extends TestCase
{
    use RefreshDatabase;

    private Shop $shop;

    private Category $category;

    private User $user;

    private Supplier $supplier;

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->shop = Shop::create([
            'name' => 'Phase 5 Shop',
            'address' => 'Test address',
            'phone' => '03150000001',
            'owner_name' => 'Owner',
            'is_parent' => true,
            'status' => true,
        ]);

        $this->category = Category::create([
            'name' => 'Phase 5 Category',
            'slug' => 'phase-5-category',
            'shop_id' => $this->shop->id,
        ]);

        foreach (['purchases.menu' => 'purchases', 'product.menu' => 'product'] as $name => $group) {
            Permission::firstOrCreate(
                ['name' => $name, 'guard_name' => 'web'],
                ['group_name' => $group]
            );
        }

        $this->user = User::create([
            'name' => 'Phase 5 Admin',
            'username' => 'phase5admin',
            'email' => 'phase5admin@example.com',
            'password' => bcrypt('password'),
            'shop_id' => $this->shop->id,
        ]);
        $this->user->givePermissionTo(['purchases.menu', 'product.menu']);

        $this->supplier = Supplier::create([
            'name' => 'Phase 5 Supplier',
            'phone' => '03150000002',
            'shop_id' => $this->shop->id,
        ]);
    }

    public function test_piece_purchase_of_one_succeeds(): void
    {
        $product = $this->makePieceProduct('100', '50.00');

        $this->postPurchase($product, '1', '50.00', '50.00')->assertRedirect(route('purchases.index'));

        $detail = $this->latestDetail($product);
        $this->assertSame('1.000', $detail->quantity);
        $this->assertSame(Product::UNIT_PIECE, $detail->unit);
        $this->assertSame('50.00', $detail->total);
        $this->assertSame('100.000', $product->fresh()->product_store);
    }

    public function test_piece_purchase_of_multiple_and_pricing(): void
    {
        $product = $this->makePieceProduct('100', '50.00');

        $this->postPurchase($product, '10', '50.00', '500.00')->assertRedirect(route('purchases.index'));
        $this->approveAndReceive($this->latestPurchase());

        $this->assertSame('110.000', $product->fresh()->product_store);
        $detail = $this->latestDetail($product);
        $this->assertSame('10.000', $detail->quantity);
        $this->assertSame(Product::UNIT_PIECE, $detail->unit);
        $this->assertSame('500.00', $detail->total);
        $this->assertSame('10 pieces', $detail->quantityWithUnit());
        $this->assertSame('50.00 / piece', $detail->unitPriceWithUnit());
    }

    public function test_fractional_piece_purchase_is_rejected(): void
    {
        $product = $this->makePieceProduct('100', '50.00');

        $this->postPurchase($product, '1.5', '50.00', '75.00')->assertSessionHasErrors();
        $this->assertSame(0, Purchase::count());
        $this->assertSame('100.000', $product->fresh()->product_store);
    }

    public function test_kg_purchase_0_750_scenario_a(): void
    {
        $product = $this->makeKgProduct('10.000', '180.00');

        $this->postPurchase($product, '0.750', '180.00', '135.00')->assertRedirect(route('purchases.index'));
        $this->approveAndReceive($this->latestPurchase());

        $this->assertSame('10.750', $product->fresh()->product_store);
        $detail = $this->latestDetail($product);
        $this->assertSame('0.750', $detail->quantity);
        $this->assertSame(Product::UNIT_KG, $detail->unit);
        $this->assertSame('135.00', $detail->total);
        $this->assertSame('0.750 kg', $detail->quantityWithUnit());
        $this->assertSame('180.00 / kg', $detail->unitPriceWithUnit());

        $log = $this->latestPurchaseLog($product);
        $this->assertSame('0.750', $log->qty);
        $this->assertSame(Product::UNIT_KG, $log->unit);
        $this->assertSame('0.750', $log->stock_qty);
        $this->assertSame('in', $log->direction);
        $this->assertSame('purchase', $log->source_type);
    }

    public function test_kg_purchase_1_500_scenario_b(): void
    {
        $product = $this->makeKgProduct('2.000', '180.00');

        $this->postPurchase($product, '1.500', '180.00', '270.00')->assertRedirect(route('purchases.index'));
        $this->approveAndReceive($this->latestPurchase());

        $this->assertSame('3.500', $product->fresh()->product_store);
        $detail = $this->latestDetail($product);
        $this->assertSame('1.500', $detail->quantity);
        $this->assertSame('270.00', $detail->total);
    }

    public function test_kg_purchase_minimum_0_001(): void
    {
        $product = $this->makeKgProduct('10.000', '180.00');

        $this->postPurchase($product, '0.001', '180.00', '0.18')->assertRedirect(route('purchases.index'));
        $this->approveAndReceive($this->latestPurchase());

        $this->assertSame('10.001', $product->fresh()->product_store);
        $this->assertSame('0.001', $this->latestDetail($product)->quantity);
    }

    public function test_kg_purchase_zero_is_rejected(): void
    {
        $product = $this->makeKgProduct('10.000', '180.00');

        $this->postPurchase($product, '0', '180.00', '0.00')->assertSessionHasErrors();
        $this->postPurchase($product, '0.000', '180.00', '0.00')->assertSessionHasErrors();
        $this->assertSame(0, Purchase::count());
        $this->assertSame('10.000', $product->fresh()->product_store);
    }

    public function test_kg_purchase_more_than_three_decimals_is_rejected(): void
    {
        $product = $this->makeKgProduct('10.000', '180.00');

        $this->postPurchase($product, '0.7505', '180.00', '135.09')->assertSessionHasErrors();
        $this->assertSame(0, Purchase::count());
        $this->assertSame('10.000', $product->fresh()->product_store);
    }

    public function test_kg_wac_scenario_f(): void
    {
        $product = $this->makeKgProduct('2.500', '100.00');
        $product->update(['buying_price' => '100.0000']);

        $this->postPurchase($product, '0.750', '200.00', '150.00')->assertRedirect(route('purchases.index'));
        $this->approveAndReceive($this->latestPurchase());

        $this->assertSame('3.250', $product->fresh()->product_store);
        $this->assertSame('123.0769', $product->fresh()->buying_price);
    }

    public function test_piece_wac_remains_correct(): void
    {
        $product = $this->makePieceProduct('10', '100.00');
        $product->update(['buying_price' => '100.0000']);

        $this->postPurchase($product, '10', '200.00', '2000.00')->assertRedirect(route('purchases.index'));
        $this->approveAndReceive($this->latestPurchase());

        $this->assertSame('20.000', $product->fresh()->product_store);
        $this->assertSame('150.0000', $product->fresh()->buying_price);
    }

    public function test_kg_purchase_locks_product_unit(): void
    {
        $product = $this->makeKgProduct('10.000', '180.00', 'Lock Sugar');
        $this->postPurchase($product, '0.750', '180.00', '135.00')->assertRedirect(route('purchases.index'));

        $this->actingAs($this->user)->put(route('products.update', $product), $this->productUpdatePayload($product, [
            'unit' => Product::UNIT_PIECE,
        ]))->assertSessionHasErrors('unit');

        $this->assertSame(Product::UNIT_KG, $product->fresh()->unit);
    }

    public function test_piece_purchase_locks_product_unit(): void
    {
        $product = $this->makePieceProduct('100', '50.00', 'Lock Piece');
        $this->postPurchase($product, '10', '50.00', '500.00')->assertRedirect(route('purchases.index'));

        $this->actingAs($this->user)->put(route('products.update', $product), $this->productUpdatePayload($product, [
            'unit' => Product::UNIT_KG,
        ]))->assertSessionHasErrors('unit');

        $this->assertSame(Product::UNIT_PIECE, $product->fresh()->unit);
    }

    public function test_purchase_product_search_includes_unit(): void
    {
        $piece = $this->makePieceProduct('100', '50.00', 'Search Piece');
        $kg = $this->makeKgProduct('10.000', '180.00', 'Search Sugar');

        $pieceSearch = $this->actingAs($this->user)->getJson(route('api.purchases.products.search', ['q' => 'Search Piece']));
        $pieceSearch->assertOk();
        $pieceRow = collect($pieceSearch->json('results'))->firstWhere('id', $piece->id);
        $this->assertNotNull($pieceRow);
        $this->assertSame(Product::UNIT_PIECE, $pieceRow['unit']);

        $kgSearch = $this->actingAs($this->user)->getJson(route('api.purchases.products.search', ['q' => 'Search Sugar']));
        $kgSearch->assertOk();
        $kgRow = collect($kgSearch->json('results'))->firstWhere('id', $kg->id);
        $this->assertNotNull($kgRow);
        $this->assertSame(Product::UNIT_KG, $kgRow['unit']);
    }

    public function test_purchase_create_page_quantity_input_is_unit_aware(): void
    {
        $page = $this->actingAs($this->user)->get(route('purchases.create'));
        $page->assertOk();
        $page->assertSee('applyQuantityInputForUnit', false);
        $page->assertSee("step: '0.001'", false);
        $page->assertSee("min: '0.001'", false);
        $page->assertSee('class="product-unit"', false);
        $page->assertSee('Piece quantity must be a whole number.', false);
        $html = $page->getContent();
        $this->assertStringNotContainsString('parseInt($(this).val())', $html);
        $this->assertStringNotContainsString('parseInt(quantity', $html);
    }

    public function test_kg_quantity_survives_edit_and_receive(): void
    {
        $product = $this->makeKgProduct('10.000', '180.00', 'Lifecycle Sugar');
        $this->postPurchase($product, '0.750', '180.00', '135.00')->assertRedirect(route('purchases.index'));
        $purchase = $this->latestPurchase();

        $edit = $this->actingAs($this->user)->get(route('purchases.edit', $purchase->id));
        $edit->assertOk();
        $edit->assertSee('0.750', false);
        $edit->assertSee('step="0.001"', false);

        $this->actingAs($this->user)->put(route('purchases.update', $purchase->id), $this->purchasePayload(
            $product,
            '0.750',
            '180.00',
            '135.00'
        ))->assertRedirect(route('purchases.show', $purchase->id));

        $detail = $this->latestDetail($product);
        $this->assertSame('0.750', $detail->quantity);
        $this->assertSame(Product::UNIT_KG, $detail->unit);
        $this->assertSame('10.000', $product->fresh()->product_store);

        $this->approveAndReceive($purchase->fresh());
        $this->assertSame('10.750', $product->fresh()->product_store);
        $this->assertSame('0.750', $this->latestPurchaseLog($product)->qty);
    }

    public function test_kg_invoice_display_uses_unit_snapshot(): void
    {
        $product = $this->makeKgProduct('10.000', '180.00', 'Invoice Sugar');
        $this->postPurchase($product, '0.750', '180.00', '135.00');
        $purchase = $this->latestPurchase();

        $show = $this->actingAs($this->user)->get(route('purchases.show', $purchase->id));
        $show->assertOk();
        $show->assertSee('0.750 kg', false);
        $show->assertSee('180.00 / kg', false);
        $show->assertSee('135.00', false);
    }

    public function test_received_kg_purchase_delete_reverses_decimal_quantity(): void
    {
        $product = $this->makeKgProduct('10.000', '180.00', 'Delete Sugar');
        $this->postPurchase($product, '0.750', '180.00', '135.00');
        $purchase = $this->latestPurchase();
        $this->approveAndReceive($purchase);
        $this->assertSame('10.750', $product->fresh()->product_store);

        $this->actingAs($this->user)->delete(route('purchases.destroy', $purchase->id))->assertRedirect();
        $this->assertSame('10.000', $product->fresh()->product_store);
    }

    private function postPurchase(Product $product, string $qty, string $unitPrice, string $total)
    {
        return $this->actingAs($this->user)->post(
            route('purchases.store'),
            $this->purchasePayload($product, $qty, $unitPrice, $total)
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function purchasePayload(Product $product, string $qty, string $unitPrice, string $total): array
    {
        return [
            'supplier_id' => $this->supplier->id,
            'purchase_date' => now()->format('Y-m-d'),
            'payment_status' => 'credit',
            'pay' => 0,
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

    private function approveAndReceive(Purchase $purchase): void
    {
        $detail = $purchase->purchaseDetails()->first();
        $this->assertNotNull($detail);

        $this->actingAs($this->user)->post(route('purchases.landed-cost.approve', $purchase->id), [
            'landed_unit_costs' => [
                $detail->id => (string) $detail->unitcost,
            ],
        ])->assertRedirect(route('purchases.show', $purchase->id));

        $this->actingAs($this->user)->put(route('purchases.updateStatus'), [
            'id' => $purchase->id,
        ])->assertRedirect(route('purchases.pending'));
    }

    private function makePieceProduct(string $stock, string $buyingPrice, string $name = 'Item A'): Product
    {
        return $this->makeProduct([
            'product_name' => $name,
            'unit' => Product::UNIT_PIECE,
            'product_store' => $stock,
            'buying_price' => $buyingPrice,
            'selling_price' => '80.00',
        ]);
    }

    private function makeKgProduct(string $stock, string $buyingPrice, string $name = 'Sugar'): Product
    {
        return $this->makeProduct([
            'product_name' => $name,
            'unit' => Product::UNIT_KG,
            'product_store' => $stock,
            'buying_price' => $buyingPrice,
            'selling_price' => '250.00',
        ]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function makeProduct(array $overrides = []): Product
    {
        return Product::create(array_merge([
            'product_name' => 'Phase 5 Product',
            'category_id' => $this->category->id,
            'shop_id' => $this->shop->id,
            'product_code' => 'P5-'.(5000 + Product::count()),
            'product_store' => '0',
            'reserved_stock' => '0',
            'low_stock_warning' => '1',
            'buying_price' => '10.0000',
            'selling_price' => '20.00',
            'status' => 'active',
            'unit' => Product::UNIT_PIECE,
        ], $overrides));
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function productUpdatePayload(Product $product, array $overrides = []): array
    {
        return array_merge([
            'product_name' => $product->product_name,
            'product_code' => $product->product_code,
            'category_id' => $product->category_id,
            'unit' => $product->unit ?: Product::UNIT_PIECE,
            'product_store' => $product->formattedQuantity(),
            'low_stock_warning' => $product->low_stock_warning ?? 10,
            'buying_price' => $product->buying_price,
            'selling_price' => $product->selling_price,
        ], $overrides);
    }

    private function latestPurchase(): Purchase
    {
        $purchase = Purchase::latest('id')->first();
        $this->assertNotNull($purchase);

        return $purchase;
    }

    private function latestDetail(Product $product): PurchaseDetail
    {
        $detail = PurchaseDetail::where('product_id', $product->id)->latest('id')->first();
        $this->assertNotNull($detail);

        return $detail;
    }

    private function latestPurchaseLog(Product $product): StockLog
    {
        $log = StockLog::where('product_id', $product->id)
            ->where('source_type', 'purchase')
            ->latest('id')
            ->first();
        $this->assertNotNull($log);

        return $log;
    }
}
