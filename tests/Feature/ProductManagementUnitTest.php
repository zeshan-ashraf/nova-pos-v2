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
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class ProductManagementUnitTest extends TestCase
{
    use RefreshDatabase;

    private Shop $shop;

    private Category $category;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->shop = Shop::create([
            'name' => 'Phase 3 Shop',
            'address' => 'Test address',
            'phone' => '03003334440',
            'owner_name' => 'Owner',
            'is_parent' => true,
            'status' => true,
        ]);

        $this->category = Category::create([
            'name' => 'Phase 3 Category',
            'slug' => 'phase-3-category',
            'shop_id' => $this->shop->id,
        ]);

        Permission::firstOrCreate(
            ['name' => 'product.menu', 'guard_name' => 'web'],
            ['group_name' => 'product']
        );
        Permission::firstOrCreate(
            ['name' => 'orders.menu', 'guard_name' => 'web'],
            ['group_name' => 'orders']
        );

        $this->user = User::create([
            'name' => 'Phase 3 Admin',
            'username' => 'phase3admin',
            'email' => 'phase3admin@example.com',
            'password' => bcrypt('password'),
            'shop_id' => $this->shop->id,
        ]);
        $this->user->givePermissionTo(['product.menu', 'orders.menu']);
    }

    public function test_factory_defaults_to_piece_and_can_create_kg(): void
    {
        $piece = Product::factory()->make(['category_id' => $this->category->id]);
        $kg = Product::factory()->kg()->make(['category_id' => $this->category->id]);

        $this->assertSame(Product::UNIT_PIECE, $piece->unit);
        $this->assertSame(Product::UNIT_KG, $kg->unit);
    }

    public function test_piece_product_can_be_created_with_whole_stock(): void
    {
        $response = $this->actingAs($this->user)->post(route('products.store'), $this->createPayload([
            'product_name' => 'Egg',
            'unit' => Product::UNIT_PIECE,
            'product_store' => '100',
            'selling_price' => '30.00',
            'buying_price' => '20.00',
        ]));

        $response->assertRedirect(route('products.index'));
        $product = Product::where('product_name', 'Egg')->first();
        $this->assertNotNull($product);
        $this->assertSame(Product::UNIT_PIECE, $product->unit);
        $this->assertSame('100.000', $product->product_store);
        $this->assertSame('30.00', $product->selling_price);
        $this->assertSame('100', $product->formattedQuantity());
    }

    public function test_kg_product_can_be_created_with_fractional_stock(): void
    {
        $response = $this->actingAs($this->user)->post(route('products.store'), $this->createPayload([
            'product_name' => 'Sugar',
            'unit' => Product::UNIT_KG,
            'product_store' => '25.500',
            'selling_price' => '250.00',
            'buying_price' => '220.00',
        ]));

        $response->assertRedirect(route('products.index'));
        $product = Product::where('product_name', 'Sugar')->first();
        $this->assertNotNull($product);
        $this->assertSame(Product::UNIT_KG, $product->unit);
        $this->assertSame('25.500', $product->product_store);
        $this->assertSame('250.00', $product->selling_price);
        $this->assertSame('25.500', $product->formattedQuantity());
    }

    public function test_kg_precision_0_750_persists_and_1_2345_is_rejected(): void
    {
        $ok = $this->actingAs($this->user)->post(route('products.store'), $this->createPayload([
            'product_name' => 'Flour',
            'unit' => Product::UNIT_KG,
            'product_store' => '0.750',
            'selling_price' => '100.00',
            'buying_price' => '80.00',
        ]));
        $ok->assertRedirect(route('products.index'));
        $this->assertSame('0.750', Product::where('product_name', 'Flour')->value('product_store'));

        $bad = $this->actingAs($this->user)->post(route('products.store'), $this->createPayload([
            'product_name' => 'Bad Flour',
            'unit' => Product::UNIT_KG,
            'product_store' => '1.2345',
            'selling_price' => '100.00',
            'buying_price' => '80.00',
        ]));
        $bad->assertSessionHasErrors('product_store');
        $this->assertNull(Product::where('product_name', 'Bad Flour')->first());
    }

    public function test_piece_fractional_stock_is_rejected(): void
    {
        $response = $this->actingAs($this->user)->post(route('products.store'), $this->createPayload([
            'product_name' => 'Fractional Egg',
            'unit' => Product::UNIT_PIECE,
            'product_store' => '1.5',
            'selling_price' => '30.00',
            'buying_price' => '20.00',
        ]));

        $response->assertSessionHasErrors('product_store');
        $this->assertNull(Product::where('product_name', 'Fractional Egg')->first());
    }

    public function test_invalid_unit_is_rejected(): void
    {
        $response = $this->actingAs($this->user)->post(route('products.store'), $this->createPayload([
            'product_name' => 'Milk',
            'unit' => 'liter',
            'product_store' => '10',
            'selling_price' => '50.00',
            'buying_price' => '40.00',
        ]));

        $response->assertSessionHasErrors('unit');
        $this->assertNull(Product::where('product_name', 'Milk')->first());
    }

    public function test_create_defaults_to_piece_when_unit_is_omitted(): void
    {
        $payload = $this->createPayload([
            'product_name' => 'Default Unit Product',
            'product_store' => '10',
            'selling_price' => '15.00',
            'buying_price' => '10.00',
        ]);
        unset($payload['unit']);

        $response = $this->actingAs($this->user)->post(route('products.store'), $payload);

        $response->assertRedirect(route('products.index'));
        $this->assertSame(Product::UNIT_PIECE, Product::where('product_name', 'Default Unit Product')->value('unit'));
    }

    public function test_zero_opening_stock_is_still_allowed(): void
    {
        $response = $this->actingAs($this->user)->post(route('products.store'), $this->createPayload([
            'product_name' => 'Zero Stock',
            'unit' => Product::UNIT_PIECE,
            'product_store' => '0',
            'selling_price' => '10.00',
            'buying_price' => '5.00',
        ]));

        $response->assertRedirect(route('products.index'));
        $this->assertSame('0.000', Product::where('product_name', 'Zero Stock')->value('product_store'));
    }

    public function test_unit_can_change_when_product_has_no_transaction_history(): void
    {
        $product = $this->makeProduct(['unit' => Product::UNIT_PIECE, 'product_name' => 'Unlocked Product']);

        $response = $this->actingAs($this->user)->put(route('products.update', $product), $this->updatePayload($product, [
            'unit' => Product::UNIT_KG,
            'product_store' => '100',
        ]));

        $response->assertRedirect(route('products.index'));
        $fresh = $product->fresh();
        $this->assertSame(Product::UNIT_KG, $fresh->unit);
        $this->assertSame('100.000', $fresh->product_store);
    }

    public function test_unit_cannot_change_when_order_history_exists(): void
    {
        $product = $this->makeProduct(['unit' => Product::UNIT_PIECE, 'product_name' => 'Locked Piece']);
        $this->addOrderHistory($product);

        $response = $this->actingAs($this->user)->put(route('products.update', $product), $this->updatePayload($product, [
            'unit' => Product::UNIT_KG,
            'product_store' => '5',
        ]));

        $response->assertSessionHasErrors('unit');
        $this->assertSame(Product::UNIT_PIECE, $product->fresh()->unit);
    }

    public function test_kg_unit_cannot_change_to_piece_when_history_exists(): void
    {
        $product = $this->makeProduct([
            'unit' => Product::UNIT_KG,
            'product_name' => 'Locked Kg',
            'product_store' => '10.000',
        ]);
        $this->addOrderHistory($product, '1.000', Product::UNIT_KG);

        $response = $this->actingAs($this->user)->put(route('products.update', $product), $this->updatePayload($product, [
            'unit' => Product::UNIT_PIECE,
            'product_store' => '10',
        ]));

        $response->assertSessionHasErrors('unit');
        $this->assertSame(Product::UNIT_KG, $product->fresh()->unit);
    }

    public function test_same_unit_update_succeeds_when_history_exists(): void
    {
        $product = $this->makeProduct([
            'unit' => Product::UNIT_KG,
            'product_name' => 'Editable Kg',
            'product_store' => '12.500',
            'selling_price' => '200.00',
        ]);
        $this->addOrderHistory($product, '1.000', Product::UNIT_KG);

        $response = $this->actingAs($this->user)->put(route('products.update', $product), $this->updatePayload($product, [
            'product_name' => 'Editable Kg Updated',
            'unit' => Product::UNIT_KG,
            'selling_price' => '210.00',
            'product_store' => '12.500',
        ]));

        $response->assertRedirect(route('products.index'));
        $fresh = $product->fresh();
        $this->assertSame(Product::UNIT_KG, $fresh->unit);
        $this->assertSame('Editable Kg Updated', $fresh->product_name);
        $this->assertSame('210.00', $fresh->selling_price);
        $this->assertSame('12.500', $fresh->product_store);
    }

    public function test_stock_log_history_also_locks_unit(): void
    {
        $product = $this->makeProduct(['unit' => Product::UNIT_PIECE, 'product_name' => 'Stock Log Locked']);
        StockLog::create([
            'shop_id' => $this->shop->id,
            'product_id' => $product->id,
            'qty' => '1.000',
            'unit' => Product::UNIT_PIECE,
            'stock_qty' => '1.000',
            'direction' => 'in',
            'source_type' => 'opening',
            'price' => '0.00',
        ]);

        $response = $this->actingAs($this->user)->put(route('products.update', $product), $this->updatePayload($product, [
            'unit' => Product::UNIT_KG,
        ]));

        $response->assertSessionHasErrors('unit');
        $this->assertSame(Product::UNIT_PIECE, $product->fresh()->unit);
    }

    public function test_ajax_create_includes_unit_in_json(): void
    {
        $response = $this->actingAs($this->user)
            ->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->post(route('products.store'), $this->createPayload([
                'product_name' => 'Ajax Kg',
                'unit' => Product::UNIT_KG,
                'product_store' => '2.500',
                'selling_price' => '50.00',
                'buying_price' => '40.00',
            ]));

        $response->assertOk()->assertJsonPath('product.unit', Product::UNIT_KG);
        $this->assertSame('2.500', Product::where('product_name', 'Ajax Kg')->value('product_store'));
    }

    public function test_product_search_json_includes_unit_additively(): void
    {
        $product = $this->makeProduct([
            'unit' => Product::UNIT_KG,
            'product_name' => 'Search Sugar',
            'status' => 'active',
            'selling_price' => '250.00',
        ]);

        $response = $this->actingAs($this->user)->getJson(route('api.products.search', ['q' => 'Search Sugar']));

        $response->assertOk();
        $row = collect($response->json('results'))->firstWhere('id', $product->id);
        $this->assertNotNull($row);
        $this->assertSame(Product::UNIT_KG, $row['unit']);
        $this->assertArrayHasKey('name', $row);
        $this->assertArrayHasKey('stock', $row);
        $this->assertArrayHasKey('price', $row);
        $this->assertArrayHasKey('code', $row);
    }

    public function test_cloned_child_product_preserves_mother_unit_without_converting_qty(): void
    {
        $mother = $this->makeProduct([
            'unit' => Product::UNIT_KG,
            'product_name' => 'Mother Sugar',
            'product_store' => '25.500',
        ]);

        $childShop = Shop::create([
            'name' => 'Child Shop',
            'address' => 'Child address',
            'phone' => '03003334441',
            'owner_name' => 'Child Owner',
            'is_parent' => false,
            'parent_shop_id' => $this->shop->id,
            'status' => true,
        ]);
        $childCategory = Category::create([
            'name' => 'Child Category',
            'slug' => 'child-category',
            'shop_id' => $childShop->id,
        ]);

        $child = Product::create([
            'product_name' => $mother->product_name,
            'category_id' => $childCategory->id,
            'shop_id' => $childShop->id,
            'parent_product_id' => $mother->id,
            'product_code' => $mother->product_code,
            'product_store' => 0,
            'reserved_stock' => 0,
            'buying_price' => $mother->buying_price,
            'selling_price' => $mother->selling_price,
            'status' => $mother->status,
            'unit' => $mother->unit ?: Product::UNIT_PIECE,
        ]);

        $this->assertSame(Product::UNIT_KG, $child->fresh()->unit);
        $this->assertSame('0.000', $child->fresh()->product_store);
        $this->assertSame('25.500', $mother->fresh()->product_store);
    }

    public function test_create_and_show_pages_include_unit_controls(): void
    {
        $create = $this->actingAs($this->user)->get(route('products.create'));
        $create->assertOk();
        $create->assertSee('Piece');
        $create->assertSee('Kg');

        $product = $this->makeProduct([
            'unit' => Product::UNIT_KG,
            'product_name' => 'Show Sugar',
            'product_store' => '25.500',
            'selling_price' => '250.00',
        ]);

        $show = $this->actingAs($this->user)->get(route('products.show', $product));
        $show->assertOk();
        $show->assertSee('25.500 kg');
        $show->assertSee('/ kg');
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function createPayload(array $overrides = []): array
    {
        return array_merge([
            'product_name' => 'Test Product',
            'category_id' => $this->category->id,
            'unit' => Product::UNIT_PIECE,
            'product_store' => '0',
            'selling_price' => '10.00',
            'buying_price' => '8.00',
        ], $overrides);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function updatePayload(Product $product, array $overrides = []): array
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

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function makeProduct(array $overrides = []): Product
    {
        return Product::create(array_merge([
            'product_name' => 'Phase 3 Product',
            'category_id' => $this->category->id,
            'shop_id' => $this->shop->id,
            'product_code' => 'MHB-'.(3000 + Product::count()),
            'product_store' => '0',
            'reserved_stock' => '0',
            'low_stock_warning' => '10',
            'buying_price' => '10.0000',
            'selling_price' => '20.00',
            'status' => 'active',
            'unit' => Product::UNIT_PIECE,
        ], $overrides));
    }

    private function addOrderHistory(Product $product, string $qty = '5', string $unit = Product::UNIT_PIECE): void
    {
        $customer = Customer::create([
            'name' => 'Phase 3 Customer',
            'phone' => '03003334442',
            'shop_id' => $this->shop->id,
        ]);

        $order = Order::create([
            'customer_id' => $customer->id,
            'shop_id' => $this->shop->id,
            'order_date' => now(),
            'order_status' => 'complete',
            'total_products' => 1,
            'sub_total' => '0.00',
            'total' => '0.00',
        ]);

        OrderDetails::create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'quantity' => $qty,
            'unit' => $unit,
            'unitcost' => '10.00',
            'total' => '10.00',
        ]);
    }
}
