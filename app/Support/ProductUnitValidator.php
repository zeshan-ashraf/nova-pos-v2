<?php

namespace App\Support;

use App\Models\Product;
use InvalidArgumentException;

/**
 * Phase 1 reusable unit/quantity validation. Controllers are not rewritten yet.
 *
 * Database can store DECIMAL(12,3). Business rules:
 * - piece: whole numbers
 * - kg: minimum 0.001, maximum 3 decimal places
 */
class ProductUnitValidator
{
    public const DECIMAL_PLACES = 3;

    public const MAX_QUANTITY = '999999999.999';

    public const KG_MIN_QUANTITY = '0.001';

    public function isValidUnit(?string $unit): bool
    {
        return $unit !== null && in_array($unit, Product::allowedUnits(), true);
    }

    public function validateUnit(?string $unit): void
    {
        if (! $this->isValidUnit($unit)) {
            throw new InvalidArgumentException(
                'Invalid product unit ['.($unit ?? 'null').']. Allowed: '.implode(', ', Product::allowedUnits()).'.'
            );
        }
    }

    /**
     * Laravel validation rules for a unit field. For future POS/purchase/product forms.
     *
     * @return array<int, mixed>
     */
    public function unitRules(): array
    {
        return ['required', 'string', 'in:'.implode(',', Product::allowedUnits())];
    }

    /**
     * Laravel validation rules for a quantity field given a unit.
     *
     * @return array<int, mixed>
     */
    public function quantityRules(string $unit, bool $allowZero = false): array
    {
        $this->validateUnit($unit);

        $rules = ['required', 'numeric', 'min:'.($allowZero ? '0' : ($unit === Product::UNIT_KG ? self::KG_MIN_QUANTITY : '1'))];

        if ($unit === Product::UNIT_PIECE) {
            $rules[] = 'integer';
        }

        $rules[] = 'decimal:0,'.self::DECIMAL_PLACES;
        $rules[] = 'max:'.self::MAX_QUANTITY;

        return $rules;
    }

    public function isValidQuantity(mixed $quantity, string $unit, bool $allowZero = false): bool
    {
        if (! $this->isValidUnit($unit)) {
            return false;
        }

        $normalized = $this->normalizeQuantity($quantity);
        if ($normalized === null) {
            return false;
        }

        if ($this->decimalPlaces($normalized) > self::DECIMAL_PLACES) {
            return false;
        }

        if ($this->compareDecimal($normalized, '0') < 0) {
            return false;
        }

        if ($this->compareDecimal($normalized, self::MAX_QUANTITY) > 0) {
            return false;
        }

        if ($unit === Product::UNIT_PIECE) {
            if (! $this->isWholeNumber($normalized)) {
                return false;
            }

            return $allowZero
                ? $this->compareDecimal($normalized, '0') >= 0
                : $this->compareDecimal($normalized, '1') >= 0;
        }

        if ($allowZero && $this->compareDecimal($normalized, '0') === 0) {
            return true;
        }

        return $this->compareDecimal($normalized, self::KG_MIN_QUANTITY) >= 0;
    }

    public function validateQuantity(mixed $quantity, string $unit, bool $allowZero = false): void
    {
        $this->validateUnit($unit);

        if (! $this->isValidQuantity($quantity, $unit, $allowZero)) {
            throw new InvalidArgumentException(
                $this->invalidQuantityMessage($quantity, $unit, $allowZero)
            );
        }
    }

    public function invalidQuantityMessage(mixed $quantity, string $unit, bool $allowZero = false): string
    {
        if ($unit === Product::UNIT_PIECE) {
            return $allowZero
                ? 'Piece quantity must be a whole number greater than or equal to 0.'
                : 'Piece quantity must be a whole number greater than or equal to 1.';
        }

        return $allowZero
            ? 'Kg quantity must be 0 or at least 0.001 with at most 3 decimal places.'
            : 'Kg quantity must be at least 0.001 with at most 3 decimal places.';
    }

    /**
     * Compare two quantities at 3-decimal precision. Returns -1, 0, or 1.
     */
    public function compare(mixed $left, mixed $right): int
    {
        $leftNormalized = $this->normalizeQuantity($left);
        $rightNormalized = $this->normalizeQuantity($right);
        if ($leftNormalized === null || $rightNormalized === null) {
            throw new InvalidArgumentException('Invalid quantity.');
        }

        return $this->compareDecimal($leftNormalized, $rightNormalized);
    }

    public function add(mixed $left, mixed $right): string
    {
        return $this->fromThousandths(
            $this->toThousandths($this->requireNormalized($left))
            + $this->toThousandths($this->requireNormalized($right))
        );
    }

    public function subtract(mixed $left, mixed $right): string
    {
        return $this->fromThousandths(
            $this->toThousandths($this->requireNormalized($left))
            - $this->toThousandths($this->requireNormalized($right))
        );
    }

    /**
     * Canonical DECIMAL(12,3) string, e.g. 0.75 → 0.750.
     */
    public function formatQuantity(mixed $quantity): string
    {
        return $this->fromThousandths($this->toThousandths($this->requireNormalized($quantity)));
    }

    public function normalizeQuantity(mixed $quantity): ?string
    {
        if (is_int($quantity)) {
            return (string) $quantity;
        }

        // Incoming floats are formatted to 3 dp as strings; callers should prefer strings.
        if (is_float($quantity)) {
            if (! is_finite($quantity)) {
                return null;
            }

            return number_format($quantity, self::DECIMAL_PLACES, '.', '');
        }

        $value = trim((string) $quantity);
        if ($value === '' || ! preg_match('/^-?\d+(\.\d+)?$/', $value)) {
            return null;
        }

        return $value;
    }

    private function requireNormalized(mixed $quantity): string
    {
        $normalized = $this->normalizeQuantity($quantity);
        if ($normalized === null) {
            throw new InvalidArgumentException('Invalid quantity.');
        }

        return $normalized;
    }

    private function fromThousandths(int $scaled): string
    {
        $negative = $scaled < 0;
        $scaled = abs($scaled);
        $whole = intdiv($scaled, 1000);
        $fraction = str_pad((string) ($scaled % 1000), self::DECIMAL_PLACES, '0', STR_PAD_LEFT);

        return ($negative ? '-' : '').$whole.'.'.$fraction;
    }

    private function decimalPlaces(string $quantity): int
    {
        $dot = strpos($quantity, '.');

        return $dot === false ? 0 : strlen(substr($quantity, $dot + 1));
    }

    private function isWholeNumber(string $quantity): bool
    {
        return (bool) preg_match('/^-?\d+(\.0+)?$/', $quantity);
    }

    /**
     * Compare two decimal strings at 3-place precision without float arithmetic.
     */
    private function compareDecimal(string $left, string $right): int
    {
        $leftScaled = $this->toThousandths($left);
        $rightScaled = $this->toThousandths($right);

        return $leftScaled <=> $rightScaled;
    }

    private function toThousandths(string $quantity): int
    {
        $negative = str_starts_with($quantity, '-');
        $quantity = ltrim($quantity, '+-');
        [$whole, $fraction] = array_pad(explode('.', $quantity, 2), 2, '');
        $fraction = str_pad(substr($fraction, 0, self::DECIMAL_PLACES), self::DECIMAL_PLACES, '0');
        $scaled = (int) ($whole.$fraction);

        return $negative ? -$scaled : $scaled;
    }
}
