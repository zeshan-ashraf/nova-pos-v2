<?php

namespace Tests\Unit;

use App\Models\Product;
use App\Support\ProductUnitValidator;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class ProductUnitValidatorTest extends TestCase
{
    private ProductUnitValidator $validator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->validator = new ProductUnitValidator();
    }

    public function test_piece_and_kg_are_supported(): void
    {
        $this->assertTrue($this->validator->isValidUnit(Product::UNIT_PIECE));
        $this->assertTrue($this->validator->isValidUnit(Product::UNIT_KG));
        $this->assertSame([Product::UNIT_PIECE, Product::UNIT_KG], Product::allowedUnits());
    }

    public function test_invalid_unit_is_rejected(): void
    {
        $this->assertFalse($this->validator->isValidUnit('gram'));
        $this->assertFalse($this->validator->isValidUnit('dozen'));
        $this->assertFalse($this->validator->isValidUnit(''));
        $this->assertFalse($this->validator->isValidUnit(null));

        $this->expectException(InvalidArgumentException::class);
        $this->validator->validateUnit('box');
    }

    /**
     * @dataProvider kgQuantitiesProvider
     */
    public function test_kg_quantities_are_accepted(string $quantity): void
    {
        $this->assertTrue($this->validator->isValidQuantity($quantity, Product::UNIT_KG));
        $this->validator->validateQuantity($quantity, Product::UNIT_KG);
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
    public function test_piece_quantities_remain_whole_numbers(string $quantity): void
    {
        $this->assertTrue($this->validator->isValidQuantity($quantity, Product::UNIT_PIECE));
        $this->validator->validateQuantity($quantity, Product::UNIT_PIECE);
    }

    public static function pieceQuantitiesProvider(): array
    {
        return [
            ['1'],
            ['5'],
            ['100'],
            ['1.000'],
        ];
    }

    public function test_piece_rejects_fractional_quantity(): void
    {
        $this->assertFalse($this->validator->isValidQuantity('0.750', Product::UNIT_PIECE));
        $this->assertFalse($this->validator->isValidQuantity('1.5', Product::UNIT_PIECE));
    }

    public function test_kg_rejects_more_than_three_decimal_places(): void
    {
        $this->assertFalse($this->validator->isValidQuantity('0.0001', Product::UNIT_KG));
        $this->assertFalse($this->validator->isValidQuantity('1.1234', Product::UNIT_KG));
    }

    public function test_kg_rejects_below_minimum_unless_zero_allowed(): void
    {
        $this->assertFalse($this->validator->isValidQuantity('0', Product::UNIT_KG));
        $this->assertTrue($this->validator->isValidQuantity('0', Product::UNIT_KG, true));
        $this->assertTrue($this->validator->isValidQuantity('0.000', Product::UNIT_KG, true));
    }

    public function test_piece_zero_is_allowed_only_when_requested(): void
    {
        $this->assertFalse($this->validator->isValidQuantity('0', Product::UNIT_PIECE));
        $this->assertTrue($this->validator->isValidQuantity('0', Product::UNIT_PIECE, true));
    }

    public function test_decimal_quantity_arithmetic_does_not_use_truncation(): void
    {
        $this->assertSame('97.500', $this->validator->subtract('100.000', '2.500'));
        $this->assertSame('9.250', $this->validator->subtract('10.000', '0.750'));
        $this->assertSame('102.500', $this->validator->add('100.000', '2.500'));
        $this->assertSame(0, $this->validator->compare('0.750', '0.750'));
        $this->assertSame('0.750', $this->validator->formatQuantity('0.75'));
    }
}
