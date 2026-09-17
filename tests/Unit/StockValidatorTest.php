<?php

namespace Tests\Unit;

use App\Models\Product;
use App\Services\Stock\StockValidator;
use App\Support\ProductUnitValidator;
use InvalidArgumentException;
use Tests\TestCase;

class StockValidatorTest extends TestCase
{
    private StockValidator $validator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->validator = new StockValidator(new ProductUnitValidator());
    }

    public function test_piece_rejects_fractional_and_zero_quantity(): void
    {
        $product = $this->product(Product::UNIT_PIECE, '10.000');

        $this->validator->validateQty('1', $product);
        $this->validator->validateQty('100', $product);

        $this->expectException(InvalidArgumentException::class);
        $this->validator->validateQty('0.5', $product);
    }

    public function test_kg_accepts_three_decimals_and_rejects_four(): void
    {
        $product = $this->product(Product::UNIT_KG, '10.000');

        $this->validator->validateQty('0.001', $product);
        $this->validator->validateQty('1.234', $product);

        $this->expectException(InvalidArgumentException::class);
        $this->validator->validateQty('1.2345', $product);
    }

    public function test_kg_rejects_zero_quantity(): void
    {
        $product = $this->product(Product::UNIT_KG, '10.000');

        $this->expectException(InvalidArgumentException::class);
        $this->validator->validateQty('0', $product);
    }

    public function test_insufficient_stock_is_rejected_for_kg(): void
    {
        $product = $this->product(Product::UNIT_KG, '1.000');

        $this->expectException(InvalidArgumentException::class);
        $this->validator->validateAvailableStock($product, '1.500');
    }

    public function test_available_stock_allows_exact_kg_balance(): void
    {
        $product = $this->product(Product::UNIT_KG, '1.000');
        $this->validator->validateAvailableStock($product, '1.000');
        $this->assertTrue(true);
    }

    private function product(string $unit, string $store): Product
    {
        $product = new Product();
        $product->unit = $unit;
        $product->product_store = $store;
        $product->product_name = 'Validator Product';
        $product->product_code = 'MHB-VAL';

        return $product;
    }
}
