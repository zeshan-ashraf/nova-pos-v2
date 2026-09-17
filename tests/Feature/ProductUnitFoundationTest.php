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
use App\Rules\AllowedProductUnit;
use App\Services\Product\ProductUnitLockService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use InvalidArgumentException;
use Tests\TestCase;

class ProductUnitFoundationTest extends TestCase
{
    use RefreshDatabase;

    private Shop $shop;

    private Category $category;

    protected function setUp(): void
    {
        parent::setUp();

        $this->shop = Shop::create([
            'name' => 'Phase 1 Shop',
            'address' => 'Test address',
            'phone' => '03001112220',
            'owner_name' => 'Owner',
            'is_parent' => true,
            'status' => true,
        ]);

        $this->category = Category::create([
            'name' => 'Phase 1 Category',
            'slug' => 'phase-1-category',
            'shop_id' => $this->shop->id,
        ]);
    }

    public function test_piece_and_kg_units_are_supported_on_product(): void
    {
        $piece = $this->makeProduct(['unit' => Product::UNIT_PIECE]);
        $kg = $this->makeProduct(['unit' => Product::UNIT_KG, 'product_name' => 'Kg Product']);

        $this->assertTrue($piece->isPiece());
        $this->assertSame(Product::UNIT_PIECE, $piece->fresh()->unit);
        $this->assertTrue($kg->isKg());
        $this->assertSame(Product::UNIT_KG, $kg->fresh()->unit);
    }

    public function test_invalid_product_unit_is_rejected(): void
    {
        $validator = Validator::make(
            ['unit' => 'dozen'],
            ['unit' => [new AllowedProductUnit()]]
        );
        $this->assertTrue($validator->fails());

        $this->expectException(InvalidArgumentException::class);
        $this->makeProduct(['unit' => 'box']);
    }

    public function test_new_product_defaults_to_piece(): void
    {
        $created = Product::create([
            'product_name' => 'Default Unit Product',
            'category_id' => $this->category->id,
            'shop_id' => $this->shop->id,
            'product_store' => '0',
            'status' => 'active',
        ]);

        $this->assertSame(Product::UNIT_PIECE, $created->unit);
        $this->assertDatabaseHas('products', [
            'id' => $created->id,
            'unit' => Product::UNIT_PIECE,
        ]);
    }

    /**
     * @dataProvider kgQuantitiesProvider
     */
    public function test_schema_stores_kg_quantities_without_truncation(string $quantity): void
    {
        $product = $this->makeProduct([
            'unit' => Product::UNIT_KG,
            'product_store' => $quantity,
            'low_stock_warning' => $quantity,
            'reserved_stock' => $quantity,
            'product_name' => 'Qty '.$quantity,
        ]);

        $product->refresh();

        $this->assertSame($this->decimal3($quantity), $product->product_store);
        $this->assertSame($this->decimal3($quantity), $product->low_stock_warning);
        $this->assertSame($this->decimal3($quantity), $product->reserved_stock);

        $raw = DB::table('products')->where('id', $product->id)->first();
        $this->assertSame($this->decimal3($quantity), $this->decimal3($raw->product_store));
    }

    public static function kgQuantitiesProvider(): array
    {
        return [
            ['0.001'],
            ['0.250'],
            ['0.750'],
            ['1.000'],
            ['2.500'],
            ['10.125'],
        ];
    }

    /**
     * @dataProvider pieceQuantitiesProvider
     */
    public function test_piece_quantities_still_behave_as_whole_quantities(string $quantity): void
    {
        $product = $this->makeProduct([
            'unit' => Product::UNIT_PIECE,
            'product_store' => $quantity,
            'product_name' => 'Piece '.$quantity,
        ]);

        $product->refresh();

        $this->assertSame($this->decimal3($quantity), $product->product_store);
        $this->assertMatchesRegularExpression('/^\d+(\.0+)?$/', (string) $product->product_store);
        $this->assertSame((int) $quantity, (int) $product->product_store);
    }

    public static function pieceQuantitiesProvider(): array
    {
        return [
            ['1'],
            ['5'],
            ['100'],
        ];
    }

    public function test_transaction_unit_snapshot_is_independent_of_current_product_unit(): void
    {
        $product = $this->makeProduct(['unit' => Product::UNIT_KG, 'product_name' => 'Snapshot Product']);
        $customer = $this->makeCustomer();
        $order = $this->makeOrder($customer);

        $line = OrderDetails::create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'quantity' => '2.500',
            'unit' => Product::UNIT_KG,
            'unitcost' => '250.00',
            'total' => '625.00',
        ]);

        // Direct column update bypasses ProductUnitLockService — enforcement is Phase 3.
        DB::table('products')->where('id', $product->id)->update(['unit' => Product::UNIT_PIECE]);

        $line->refresh();
        $product->refresh();

        $this->assertSame(Product::UNIT_PIECE, $product->unit);
        $this->assertSame(Product::UNIT_KG, $line->unit);
        $this->assertSame('2.500', $line->quantity);
    }

    public function test_all_movement_tables_can_store_piece_and_kg_snapshots(): void
    {
        $product = $this->makeProduct(['unit' => Product::UNIT_KG, 'product_name' => 'Movement Product']);
        $customer = $this->makeCustomer();
        $supplier = $this->makeSupplier();
        $order = $this->makeOrder($customer);
        $purchase = $this->makePurchase($supplier);

        $orderDetail = OrderDetails::create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'quantity' => '1.250',
            'unit' => Product::UNIT_KG,
            'unitcost' => '10.00',
            'total' => '12.50',
        ]);

        PurchaseDetail::create([
            'purchase_id' => $purchase->id,
            'product_id' => $product->id,
            'quantity' => '3.000',
            'unit' => Product::UNIT_KG,
            'unitcost' => '8.00',
            'total' => '24.00',
        ]);

        $saleReturn = SaleReturn::create([
            'order_id' => $order->id,
            'customer_id' => $customer->id,
            'shop_id' => $this->shop->id,
            'return_date' => now(),
            'return_no' => 'SR-PHASE1-1',
            'total_products' => 1,
            'sub_total' => 0,
            'total' => 0,
        ]);

        SaleReturnDetail::create([
            'return_id' => $saleReturn->id,
            'order_id' => $order->id,
            'order_detail_id' => $orderDetail->id,
            'product_id' => $product->id,
            'quantity' => '0.250',
            'unit' => Product::UNIT_KG,
            'unitcost' => '10.00',
            'total' => '2.50',
        ]);

        $purchaseReturn = PurchaseReturn::create([
            'purchase_id' => $purchase->id,
            'shop_id' => $this->shop->id,
            'return_no' => 'PR-PHASE1-1',
            'return_date' => now(),
            'total_products' => 1,
            'sub_total' => 0,
            'total' => 0,
            'status' => 'pending',
        ]);

        PurchaseReturnDetail::create([
            'purchase_return_id' => $purchaseReturn->id,
            'product_id' => $product->id,
            'quantity' => '0.500',
            'unit' => Product::UNIT_KG,
            'price' => '8.00',
            'total' => '4.00',
        ]);

        $pieceLog = StockLog::create([
            'shop_id' => $this->shop->id,
            'product_id' => $product->id,
            'qty' => '5',
            'unit' => Product::UNIT_PIECE,
            'stock_qty' => '5',
            'direction' => 'in',
            'source_type' => 'opening',
        ]);

        $kgLog = StockLog::create([
            'shop_id' => $this->shop->id,
            'product_id' => $product->id,
            'qty' => '0.750',
            'unit' => Product::UNIT_KG,
            'stock_qty' => '0.750',
            'direction' => 'in',
            'source_type' => 'opening',
        ]);

        $this->assertSame(Product::UNIT_KG, OrderDetails::find($orderDetail->id)->unit);
        $this->assertSame(Product::UNIT_KG, PurchaseDetail::first()->unit);
        $this->assertSame(Product::UNIT_KG, SaleReturnDetail::first()->unit);
        $this->assertSame(Product::UNIT_KG, PurchaseReturnDetail::first()->unit);
        $this->assertSame(Product::UNIT_PIECE, $pieceLog->fresh()->unit);
        $this->assertSame(Product::UNIT_KG, $kgLog->fresh()->unit);
        $this->assertSame('0.750', $kgLog->fresh()->qty);
    }

    public function test_unit_may_be_changed_when_product_has_no_transaction_history(): void
    {
        $product = $this->makeProduct(['unit' => Product::UNIT_PIECE, 'product_name' => 'Unlocked Product']);
        $lock = app(ProductUnitLockService::class);

        $this->assertFalse($lock->hasTransactionHistory($product));
        $this->assertTrue($lock->canChangeUnit($product));

        $product->update(['unit' => Product::UNIT_KG]);

        $this->assertSame(Product::UNIT_KG, $product->fresh()->unit);
    }

    public function test_unit_is_considered_locked_when_transaction_history_exists(): void
    {
        $product = $this->makeProduct(['unit' => Product::UNIT_PIECE, 'product_name' => 'Locked Product']);
        $customer = $this->makeCustomer();
        $order = $this->makeOrder($customer);

        OrderDetails::create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'quantity' => '1',
            'unit' => Product::UNIT_PIECE,
            'unitcost' => '10.00',
            'total' => '10.00',
        ]);

        $lock = app(ProductUnitLockService::class);

        $this->assertTrue($lock->hasTransactionHistory($product));
        $this->assertFalse($lock->canChangeUnit($product));

        // Phase 1 does not enforce this on ProductController update. Direct save still works:
        $product->update(['unit' => Product::UNIT_KG]);
        $this->assertSame(Product::UNIT_KG, $product->fresh()->unit);
    }

    public function test_quantity_and_money_column_types(): void
    {
        $this->assertSame('decimal(12,3)', $this->mysqlType('products', 'product_store'));
        $this->assertSame('decimal(12,3)', $this->mysqlType('products', 'reserved_stock'));
        $this->assertSame('decimal(12,3)', $this->mysqlType('products', 'low_stock_warning'));
        $this->assertSame('decimal(16,4)', $this->mysqlType('products', 'buying_price'));
        $this->assertSame('decimal(12,2)', $this->mysqlType('products', 'selling_price'));
        $this->assertSame('varchar(16)', $this->mysqlType('products', 'unit'));

        $this->assertSame('decimal(12,3)', $this->mysqlType('order_details', 'quantity'));
        $this->assertSame('varchar(16)', $this->mysqlType('order_details', 'unit'));
        $this->assertSame('decimal(12,2)', $this->mysqlType('order_details', 'unitcost'));
        $this->assertSame('decimal(12,2)', $this->mysqlType('order_details', 'total'));
        $this->assertSame('decimal(16,4)', $this->mysqlType('order_details', 'cost_per_unit'));

        $this->assertSame('decimal(12,3)', $this->mysqlType('purchase_details', 'quantity'));
        $this->assertSame('varchar(16)', $this->mysqlType('purchase_details', 'unit'));
        $this->assertSame('decimal(12,2)', $this->mysqlType('purchase_details', 'unitcost'));
        $this->assertSame('decimal(12,2)', $this->mysqlType('purchase_details', 'total'));

        $this->assertSame('decimal(12,3)', $this->mysqlType('stock_logs', 'qty'));
        $this->assertSame('decimal(12,3)', $this->mysqlType('stock_logs', 'stock_qty'));
        $this->assertSame('varchar(16)', $this->mysqlType('stock_logs', 'unit'));
        $this->assertSame('decimal(16,4)', $this->mysqlType('stock_logs', 'cost_per_unit'));

        $this->assertSame('decimal(12,3)', $this->mysqlType('sale_return_details', 'quantity'));
        $this->assertSame('varchar(16)', $this->mysqlType('sale_return_details', 'unit'));
        $this->assertSame('decimal(12,3)', $this->mysqlType('purchase_return_details', 'quantity'));
        $this->assertSame('varchar(16)', $this->mysqlType('purchase_return_details', 'unit'));

        $this->assertSame('decimal(12,2)', $this->mysqlType('orders', 'total'));
        $this->assertSame('decimal(12,2)', $this->mysqlType('purchases', 'total'));
    }

    public function test_fractional_line_money_is_stored(): void
    {
        $product = $this->makeProduct([
            'unit' => Product::UNIT_KG,
            'selling_price' => '250.00',
            'product_name' => 'Money Product',
        ]);
        $customer = $this->makeCustomer();
        $order = $this->makeOrder($customer, [
            'sub_total' => '187.50',
            'total' => '187.50',
        ]);

        $line = OrderDetails::create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'quantity' => '0.750',
            'unit' => Product::UNIT_KG,
            'unitcost' => '250.00',
            'total' => '187.50',
        ]);

        $this->assertSame('250.00', $product->fresh()->selling_price);
        $this->assertSame('187.50', $line->fresh()->total);
        $this->assertSame('187.50', $order->fresh()->total);
    }

    private function makeProduct(array $overrides): Product
    {
        return Product::create(array_merge([
            'product_name' => 'Phase 1 Product',
            'category_id' => $this->category->id,
            'shop_id' => $this->shop->id,
            'product_store' => '0',
            'reserved_stock' => '0',
            'low_stock_warning' => '10',
            'buying_price' => '0',
            'selling_price' => '0',
            'status' => 'active',
            'unit' => Product::UNIT_PIECE,
        ], $overrides));
    }

    private function makeCustomer(): Customer
    {
        return Customer::create([
            'name' => 'Phase 1 Customer',
            'phone' => '03001112221',
            'shop_id' => $this->shop->id,
        ]);
    }

    private function makeSupplier(): Supplier
    {
        return Supplier::create([
            'name' => 'Phase 1 Supplier',
            'phone' => '03001112222',
            'shop_id' => $this->shop->id,
        ]);
    }

    private function makeOrder(Customer $customer, array $overrides = []): Order
    {
        return Order::create(array_merge([
            'customer_id' => $customer->id,
            'shop_id' => $this->shop->id,
            'order_date' => now(),
            'order_status' => 'complete',
            'total_products' => 1,
            'sub_total' => '0.00',
            'total' => '0.00',
        ], $overrides));
    }

    private function makePurchase(Supplier $supplier): Purchase
    {
        return Purchase::create([
            'supplier_id' => $supplier->id,
            'shop_id' => $this->shop->id,
            'purchase_date' => now()->toDateString(),
            'purchase_status' => 'complete',
            'total_products' => 1,
            'sub_total' => '0.00',
            'total' => '0.00',
        ]);
    }

    private function mysqlType(string $table, string $column): string
    {
        $row = DB::selectOne("SHOW COLUMNS FROM `{$table}` WHERE Field = '{$column}'");

        return strtolower((string) ($row->Type ?? ''));
    }

    private function decimal3(string $quantity): string
    {
        if (! str_contains($quantity, '.')) {
            return $quantity.'.000';
        }

        [$whole, $fraction] = explode('.', $quantity, 2);

        return $whole.'.'.str_pad(substr($fraction, 0, 3), 3, '0');
    }
}
