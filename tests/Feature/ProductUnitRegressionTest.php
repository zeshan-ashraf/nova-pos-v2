<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderDetails;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\PurchaseDetail;
use App\Models\PurchaseReturn;
use App\Models\PurchaseReturnDetail;
use App\Models\SaleReturn;
use App\Models\SaleReturnDetail;
use App\Models\Shop;
use App\Models\StockLog;
use App\Models\Supplier;
use App\Models\User;
use App\Services\Product\ProductUnitLockService;
use App\Services\Stock\StockService;
use App\Support\ProductUnitValidator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class ProductUnitRegressionTest extends TestCase
{
    use RefreshDatabase;

    private Shop $shop;

    private Category $category;

    private User $user;

    private Customer $customer;

    private Supplier $supplier;

    protected function setUp(): void
    {
        parent::setUp();
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->shop = Shop::create([
            'name' => 'Phase 8 Shop',
            'address' => 'Test address',
            'phone' => '03180000001',
            'owner_name' => 'Owner',
            'is_parent' => true,
            'status' => true,
        ]);
        $this->category = Category::create([
            'name' => 'Phase 8 Category',
            'slug' => 'phase-8-category',
            'shop_id' => $this->shop->id,
        ]);
        Permission::firstOrCreate(
            ['name' => 'product.menu', 'guard_name' => 'web'],
            ['group_name' => 'product']
        );
        $this->user = User::create([
            'name' => 'Phase 8 Admin',
            'username' => 'phase8admin',
            'email' => 'phase8admin@example.com',
            'password' => bcrypt('password'),
            'shop_id' => $this->shop->id,
        ]);
        $this->user->givePermissionTo('product.menu');
        $this->customer = Customer::create([
            'name' => 'Phase 8 Customer',
            'phone' => '03180000002',
            'shop_id' => $this->shop->id,
        ]);
        $this->supplier = Supplier::create([
            'name' => 'Phase 8 Supplier',
            'phone' => '03180000003',
            'shop_id' => $this->shop->id,
        ]);
    }

    public function test_quantity_edges_and_exact_decimal_subtraction(): void
    {
        $units = app(ProductUnitValidator::class);

        foreach (['0.001', '0.010', '0.100', '0.750', '1.250', '2.500', '10.000'] as $qty) {
            $this->assertTrue($units->isValidQuantity($qty, Product::UNIT_KG));
            $this->assertSame($qty, $units->formatQuantity($qty));
        }
        foreach (['1', '3', '10'] as $qty) {
            $this->assertTrue($units->isValidQuantity($qty, Product::UNIT_PIECE));
        }
        foreach (['0', '0.0005', '0.7505'] as $qty) {
            $this->assertFalse($units->isValidQuantity($qty, Product::UNIT_KG));
        }
        $this->assertFalse($units->isValidQuantity('1.5', Product::UNIT_PIECE));
        $this->assertSame('0.250', $units->subtract('1.000', '0.750'));
        $this->assertSame('9.999', $units->subtract('10.000', '0.001'));

        $product = $this->makeProduct(['unit' => Product::UNIT_KG, 'product_store' => '10.000', 'buying_price' => '100.0000']);
        app(StockService::class)->sellStock($product, '0.750', 250, 1);
        $this->assertSame('9.250', $product->fresh()->product_store);
        $this->assertSame('100.0000', $product->fresh()->buying_price);
    }

    public function test_kg_purchases_set_expected_wac_and_piece_purchase_stays_whole(): void
    {
        $sugar = $this->makeProduct(['unit' => Product::UNIT_KG, 'product_store' => '0', 'buying_price' => '0']);
        $stock = app(StockService::class);
        $stock->purchaseStock($sugar, '2.500', 100, $this->supplier->id, 1);
        $stock->purchaseStock($sugar->fresh(), '0.750', 200, $this->supplier->id, 2);
        $this->assertSame('3.250', $sugar->fresh()->product_store);
        $this->assertSame('123.0769', $sugar->fresh()->buying_price);

        $soap = $this->makeProduct(['unit' => Product::UNIT_PIECE, 'product_store' => '10', 'buying_price' => '80.0000']);
        $stock->purchaseStock($soap, '3', 90, $this->supplier->id, 3);
        $this->assertSame('13.000', $soap->fresh()->product_store);
        $this->assertFalse($stock === null);
    }

    public function test_unit_is_locked_by_each_history_table_and_open_without_history(): void
    {
        $lock = app(ProductUnitLockService::class);
        $open = $this->makeProduct();
        $this->assertTrue($lock->canChangeUnit($open));

        $sale = $this->makeProduct();
        $order = Order::create([
            'customer_id' => $this->customer->id,
            'shop_id' => $this->shop->id,
            'order_date' => now(),
            'order_status' => 'complete',
            'total_products' => 1,
            'sub_total' => '1',
            'total' => '1',
            'pay' => '1',
            'due' => '0',
        ]);
        OrderDetails::create([
            'order_id' => $order->id,
            'product_id' => $sale->id,
            'quantity' => '1',
            'unit' => Product::UNIT_PIECE,
            'unitcost' => '1',
            'total' => '1',
        ]);
        $this->assertFalse($lock->canChangeUnit($sale));

        $purchaseProduct = $this->makeProduct();
        $purchase = Purchase::create([
            'supplier_id' => $this->supplier->id,
            'shop_id' => $this->shop->id,
            'purchase_date' => now(),
            'purchase_status' => 'received',
            'total_products' => 1,
            'sub_total' => '1',
            'total' => '1',
            'pay' => '1',
            'due' => '0',
        ]);
        PurchaseDetail::create([
            'purchase_id' => $purchase->id,
            'product_id' => $purchaseProduct->id,
            'quantity' => '1',
            'unit' => Product::UNIT_PIECE,
            'unitcost' => '1',
            'total' => '1',
        ]);
        $this->assertFalse($lock->canChangeUnit($purchaseProduct));

        $logged = $this->makeProduct();
        StockLog::create([
            'shop_id' => $this->shop->id,
            'product_id' => $logged->id,
            'supplier_id' => $this->supplier->id,
            'qty' => '1',
            'unit' => Product::UNIT_PIECE,
            'stock_qty' => '1',
            'direction' => 'in',
            'source_type' => 'adjustment',
            'price' => '1',
        ]);
        $this->assertFalse($lock->canChangeUnit($logged));

        $returned = $this->makeProduct(['unit' => Product::UNIT_KG]);
        $returnDetail = OrderDetails::create([
            'order_id' => $order->id,
            'product_id' => $returned->id,
            'quantity' => '2.500',
            'unit' => Product::UNIT_KG,
            'unitcost' => '1',
            'total' => '1',
        ]);
        $saleReturn = SaleReturn::create([
            'order_id' => $order->id,
            'customer_id' => $this->customer->id,
            'shop_id' => $this->shop->id,
            'return_date' => now(),
            'return_status' => 'complete',
            'return_no' => 'SR-P8',
            'total_products' => 1,
            'sub_total' => '1',
            'total' => '1',
        ]);
        SaleReturnDetail::create([
            'return_id' => $saleReturn->id,
            'order_id' => $order->id,
            'order_detail_id' => $returnDetail->id,
            'product_id' => $returned->id,
            'quantity' => '0.750',
            'unit' => Product::UNIT_KG,
            'unitcost' => '1',
            'total' => '1',
        ]);
        $this->assertFalse($lock->canChangeUnit($returned));

        $purchaseReturned = $this->makeProduct();
        $purchaseReturn = PurchaseReturn::create([
            'purchase_id' => $purchase->id,
            'shop_id' => $this->shop->id,
            'return_no' => 'PR-P8',
            'return_date' => now(),
            'total_products' => 1,
            'sub_total' => '1',
            'total' => '1',
            'status' => 'approved',
        ]);
        PurchaseReturnDetail::create([
            'purchase_return_id' => $purchaseReturn->id,
            'product_id' => $purchaseReturned->id,
            'quantity' => '0.750',
            'unit' => Product::UNIT_KG,
            'price' => '1',
            'total' => '1',
        ]);
        $this->assertFalse($lock->canChangeUnit($purchaseReturned));
    }

    public function test_stock_adjustment_accepts_kg_and_rejects_fractional_piece(): void
    {
        $sugar = $this->makeProduct(['unit' => Product::UNIT_KG, 'product_store' => '10.000']);
        $this->actingAs($this->user)->postJson(route('stock.adjust'), $this->adjustPayload($sugar->id, '0.750'))
            ->assertOk();
        $this->assertSame('9.250', $sugar->fresh()->product_store);

        $soap = $this->makeProduct(['unit' => Product::UNIT_PIECE, 'product_store' => '10']);
        $this->actingAs($this->user)->postJson(route('stock.adjust'), $this->adjustPayload($soap->id, '1.5'))
            ->assertStatus(422);
        $this->assertSame('10.000', $soap->fresh()->product_store);

        try {
            app(StockService::class)->adjustStock($sugar->fresh(), '0.0005', 'out', 'too small', now()->toDateTimeString());
            $this->fail('Expected invalid kg quantity.');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('0.001', $e->getMessage());
        }
    }

    public function test_quantity_columns_remain_decimal(): void
    {
        foreach ([
            'products' => ['product_store', 'reserved_stock', 'low_stock_warning'],
            'order_details' => ['quantity'],
            'purchase_details' => ['quantity'],
            'sale_return_details' => ['quantity'],
            'purchase_return_details' => ['quantity'],
            'stock_logs' => ['qty', 'stock_qty'],
        ] as $table => $columns) {
            $found = collect(Schema::getColumns($table))->keyBy('name');
            foreach ($columns as $column) {
                $this->assertStringContainsString('decimal', strtolower((string) $found[$column]['type']), $table.'.'.$column);
            }
        }
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function makeProduct(array $overrides = []): Product
    {
        return Product::create(array_merge([
            'product_name' => 'Phase 8 Product',
            'category_id' => $this->category->id,
            'shop_id' => $this->shop->id,
            'product_code' => 'P8-'.(8000 + Product::withoutGlobalScopes()->count()),
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
     * @return array<string, mixed>
     */
    private function adjustPayload(int $productId, string $qty): array
    {
        return [
            'product_id' => $productId,
            'adjustment_type' => 'manual_remove',
            'qty' => $qty,
            'date' => now()->toDateString(),
            'time' => now()->format('H:i'),
            'reason' => 'Phase 8 regression',
        ];
    }
}
