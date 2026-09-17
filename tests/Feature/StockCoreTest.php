<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\Shop;
use App\Models\Supplier;
use App\Services\Stock\StockService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

class StockCoreTest extends TestCase
{
    use RefreshDatabase;

    private StockService $stock;

    private Shop $shop;

    private Category $category;

    private Supplier $supplier;

    protected function setUp(): void
    {
        parent::setUp();

        $this->stock = app(StockService::class);

        $this->shop = Shop::create([
            'name' => 'Phase 2 Shop',
            'address' => 'Test address',
            'phone' => '03002223330',
            'owner_name' => 'Owner',
            'is_parent' => true,
            'status' => true,
        ]);

        $this->category = Category::create([
            'name' => 'Phase 2 Category',
            'slug' => 'phase-2-category',
            'shop_id' => $this->shop->id,
        ]);

        $this->supplier = Supplier::create([
            'name' => 'Phase 2 Supplier',
            'phone' => '03002223331',
            'shop_id' => $this->shop->id,
        ]);
    }

    public function test_piece_sale_leaves_whole_remaining_stock(): void
    {
        $product = $this->makeProduct([
            'unit' => Product::UNIT_PIECE,
            'product_store' => '10',
        ]);

        $this->stock->sellStock($product, '1', 10.0, 101);
        $this->assertSame('9.000', $product->fresh()->product_store);

        $second = $this->makeProduct([
            'unit' => Product::UNIT_PIECE,
            'product_store' => '10',
            'product_name' => 'Piece Sale 5',
        ]);
        $this->stock->sellStock($second, '5', 10.0, 102);
        $this->assertSame('5.000', $second->fresh()->product_store);
        $this->assertSame(5, (int) $second->fresh()->product_store);
    }

    public function test_piece_purchase_adds_whole_quantity(): void
    {
        $product = $this->makeProduct([
            'unit' => Product::UNIT_PIECE,
            'product_store' => '10',
        ]);

        $this->stock->purchaseStock($product, '5', 20.0, (int) $this->supplier->id, 201);
        $this->assertSame('15.000', $product->fresh()->product_store);
    }

    public function test_piece_rejects_fractional_quantity(): void
    {
        $product = $this->makeProduct([
            'unit' => Product::UNIT_PIECE,
            'product_store' => '10',
        ]);

        $this->expectException(InvalidArgumentException::class);
        $this->stock->sellStock($product, '0.5', 10.0, 103);
    }

    public function test_piece_insufficient_stock_is_rejected(): void
    {
        $product = $this->makeProduct([
            'unit' => Product::UNIT_PIECE,
            'product_store' => '10',
        ]);

        $this->expectException(InvalidArgumentException::class);
        $this->stock->sellStock($product, '11', 10.0, 104);
    }

    public function test_kg_sale_subtracts_decimal_quantity(): void
    {
        $product = $this->makeProduct([
            'unit' => Product::UNIT_KG,
            'product_store' => '100.000',
            'product_name' => 'Kg Sale',
        ]);

        $log = $this->stock->sellStock($product, '2.500', 250.0, 201);

        $this->assertSame('97.500', $product->fresh()->product_store);
        $this->assertSame('2.500', $log->qty);
        $this->assertSame(Product::UNIT_KG, $log->unit);
        $this->assertSame('-2.500', $log->stock_qty);
    }

    public function test_kg_fractional_sale(): void
    {
        $product = $this->makeProduct([
            'unit' => Product::UNIT_KG,
            'product_store' => '10.000',
            'product_name' => 'Kg Fractional',
        ]);

        $this->stock->sellStock($product, '0.750', 250.0, 202);
        $this->assertSame('9.250', $product->fresh()->product_store);
    }

    public function test_kg_insufficient_stock_is_rejected_and_does_not_go_negative(): void
    {
        $product = $this->makeProduct([
            'unit' => Product::UNIT_KG,
            'product_store' => '1.000',
            'product_name' => 'Kg Short',
        ]);

        try {
            $this->stock->sellStock($product, '1.500', 250.0, 203);
            $this->fail('Expected insufficient stock to be rejected.');
        } catch (InvalidArgumentException $e) {
            $this->assertSame('1.000', $product->fresh()->product_store);
            $this->assertStringContainsString('Insufficient stock', $e->getMessage());
        }
    }

    public function test_kg_minimum_quantity_0_001_is_valid(): void
    {
        $product = $this->makeProduct([
            'unit' => Product::UNIT_KG,
            'product_store' => '1.000',
            'product_name' => 'Kg Min',
        ]);

        $this->stock->sellStock($product, '0.001', 10.0, 204);
        $this->assertSame('0.999', $product->fresh()->product_store);
    }

    public function test_kg_zero_quantity_is_invalid(): void
    {
        $product = $this->makeProduct([
            'unit' => Product::UNIT_KG,
            'product_store' => '1.000',
            'product_name' => 'Kg Zero',
        ]);

        $this->expectException(InvalidArgumentException::class);
        $this->stock->sellStock($product, '0', 10.0, 205);
    }

    public function test_kg_three_decimal_places_are_valid_and_four_are_rejected(): void
    {
        $product = $this->makeProduct([
            'unit' => Product::UNIT_KG,
            'product_store' => '10.000',
            'product_name' => 'Kg Precision',
        ]);

        $this->stock->sellStock($product, '1.234', 10.0, 206);
        $this->assertSame('8.766', $product->fresh()->product_store);

        $this->expectException(InvalidArgumentException::class);
        $this->stock->sellStock($product->fresh(), '1.2345', 10.0, 207);
    }

    public function test_kg_purchase_adds_decimal_quantity_without_truncation(): void
    {
        $product = $this->makeProduct([
            'unit' => Product::UNIT_KG,
            'product_store' => '100.000',
            'product_name' => 'Kg Purchase',
        ]);

        $log = $this->stock->purchaseStock($product, '2.500', 100.0, (int) $this->supplier->id, 301);

        $this->assertSame('102.500', $product->fresh()->product_store);
        $this->assertSame('2.500', $log->qty);
        $this->assertSame(Product::UNIT_KG, $log->unit);
        $this->assertSame('2.500', $log->stock_qty);
    }

    public function test_piece_wac_regression(): void
    {
        $product = $this->makeProduct([
            'unit' => Product::UNIT_PIECE,
            'product_store' => '10',
            'buying_price' => '100.0000',
            'product_name' => 'Piece WAC',
        ]);

        $this->stock->purchaseStock($product, '10', 200.0, (int) $this->supplier->id, 401);
        $this->assertSame('150.0000', $product->fresh()->buying_price);
        $this->assertSame('20.000', $product->fresh()->product_store);
    }

    public function test_kg_wac_uses_decimal_quantities(): void
    {
        $product = $this->makeProduct([
            'unit' => Product::UNIT_KG,
            'product_store' => '10.000',
            'buying_price' => '100.0000',
            'product_name' => 'Kg WAC equal',
        ]);

        $this->stock->purchaseStock($product, '10.000', 200.0, (int) $this->supplier->id, 402);
        $this->assertSame('150.0000', $product->fresh()->buying_price);
        $this->assertSame('20.000', $product->fresh()->product_store);
    }

    public function test_kg_wac_with_uneven_decimal_quantities(): void
    {
        $product = $this->makeProduct([
            'unit' => Product::UNIT_KG,
            'product_store' => '2.500',
            'buying_price' => '100.0000',
            'product_name' => 'Kg WAC uneven',
        ]);

        $this->stock->purchaseStock($product, '0.750', 200.0, (int) $this->supplier->id, 403);

        // ((2.500 * 100) + (0.750 * 200)) / 3.250 = 123.0769
        $this->assertSame('123.0769', $product->fresh()->buying_price);
        $this->assertSame('3.250', $product->fresh()->product_store);
    }

    public function test_wac_first_purchase_on_zero_stock(): void
    {
        $product = $this->makeProduct([
            'unit' => Product::UNIT_KG,
            'product_store' => '0',
            'buying_price' => '0',
            'product_name' => 'Kg WAC first',
        ]);

        $this->stock->purchaseStock($product, '5.000', 80.1234, (int) $this->supplier->id, 404);
        $this->assertSame('80.1234', $product->fresh()->buying_price);
        $this->assertSame('5.000', $product->fresh()->product_store);
    }

    public function test_wac_multiple_purchases(): void
    {
        $product = $this->makeProduct([
            'unit' => Product::UNIT_KG,
            'product_store' => '0',
            'buying_price' => '0',
            'product_name' => 'Kg WAC multi',
        ]);

        $this->stock->purchaseStock($product, '1.000', 100.0, (int) $this->supplier->id, 405);
        $this->stock->purchaseStock($product->fresh(), '1.000', 200.0, (int) $this->supplier->id, 406);
        $this->assertSame('150.0000', $product->fresh()->buying_price);
        $this->assertSame('2.000', $product->fresh()->product_store);
    }

    public function test_piece_stock_log_preserves_whole_quantity_and_unit(): void
    {
        $product = $this->makeProduct([
            'unit' => Product::UNIT_PIECE,
            'product_store' => '10',
            'product_name' => 'Piece Log',
        ]);

        $log = $this->stock->sellStock($product, '5', 10.0, 501);
        $log->refresh();

        $this->assertSame('5.000', $log->qty);
        $this->assertSame(Product::UNIT_PIECE, $log->unit);
        $this->assertSame(5, (int) $log->qty);
    }

    public function test_sequential_oversell_cannot_make_stock_negative(): void
    {
        // True parallel PHPUnit concurrency is not available on this Windows/MySQL setup.
        // This deterministic test covers the lock + negative-stock guard: two 60 kg
        // deductions against 100 kg must never yield -20.
        $product = $this->makeProduct([
            'unit' => Product::UNIT_KG,
            'product_store' => '100.000',
            'product_name' => 'Kg Concurrent',
        ]);

        $this->stock->sellStock($product, '60.000', 10.0, 601);
        $this->assertSame('40.000', $product->fresh()->product_store);

        try {
            $this->stock->sellStock($product->fresh(), '60.000', 10.0, 602);
            $this->fail('Second 60 kg sale should be rejected.');
        } catch (InvalidArgumentException $e) {
            $this->assertSame('40.000', $product->fresh()->product_store);
            $this->assertGreaterThanOrEqual(0, (float) $product->fresh()->product_store);
        }
    }

    public function test_sale_does_not_change_wac(): void
    {
        $product = $this->makeProduct([
            'unit' => Product::UNIT_KG,
            'product_store' => '10.000',
            'buying_price' => '100.1234',
            'product_name' => 'Kg COGS',
        ]);

        $log = $this->stock->sellStock($product, '2.500', 250.0, 701, 100.1234);
        $this->assertSame('100.1234', $product->fresh()->buying_price);
        $this->assertSame('100.1234', $log->cost_per_unit);
        $this->assertSame('7.500', $product->fresh()->product_store);
    }

    public function test_adjust_stock_supports_kg_decimal_qty(): void
    {
        $product = $this->makeProduct([
            'unit' => Product::UNIT_KG,
            'product_store' => '5.000',
            'product_name' => 'Kg Adjust',
        ]);

        $this->stock->adjustStock($product, '0.750', 'out', 'damaged sample', now()->toDateTimeString(), 'loss');
        $this->assertSame('4.250', $product->fresh()->product_store);
    }

    private function makeProduct(array $overrides): Product
    {
        static $n = 0;
        $n++;

        return Product::create(array_merge([
            'product_name' => 'Phase 2 Product '.$n,
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
}
