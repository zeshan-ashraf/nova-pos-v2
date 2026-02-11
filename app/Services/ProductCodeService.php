<?php

namespace App\Services;

use App\Models\Product;
use Illuminate\Support\Facades\DB;

/**
 * MHB-XXXX product codes: global sequence, unique system-wide.
 * Format: MHB-1001, MHB-1002, ... (length not restricted to 4).
 * "Generate code" button shows next available WITHOUT consuming; code is consumed only on save (lock at insert).
 */
class ProductCodeService
{
    public const PREFIX = 'MHB-';
    public const FIRST_NUMBER = 1001;

    /**
     * Return the next available code (preview only). Does NOT increment the sequence.
     * Same code is shown until a product is saved with it (or another user consumes it).
     */
    public function getNextAvailableCode(): string
    {
        $row = DB::table('product_code_sequence')->where('id', 1)->first();
        $last = $row ? (int) $row->last_number : 1000;
        return self::PREFIX . ($last + 1);
    }

    /**
     * Generate and consume the next product code (lock + increment). Use only at insert/save time.
     */
    public function generateNextCode(): string
    {
        return DB::transaction(function () {
            $row = DB::table('product_code_sequence')
                ->where('id', 1)
                ->lockForUpdate()
                ->first();

            if (!$row) {
                throw new \RuntimeException('Product code sequence not initialized. Run migrations.');
            }

            $next = (int) $row->last_number + 1;
            DB::table('product_code_sequence')->where('id', 1)->update(['last_number' => $next]);

            return self::PREFIX . $next;
        });
    }

    /**
     * After assigning a code to a product, sync sequence so that number is not suggested again.
     */
    public function syncSequenceAfterAssign(int $numericPart): void
    {
        $row = DB::table('product_code_sequence')->where('id', 1)->first();
        if (!$row) {
            return;
        }
        $current = (int) $row->last_number;
        $new = max($current, $numericPart);
        if ($new > $current) {
            DB::table('product_code_sequence')->where('id', 1)->update(['last_number' => $new]);
        }
    }

    /**
     * Parse numeric part from code (e.g. MHB-1001 → 1001). Returns null if invalid.
     */
    public function parseNumericPart(?string $code): ?int
    {
        if ($code === null || $code === '') {
            return null;
        }
        $code = trim($code);
        if (stripos($code, self::PREFIX) !== 0) {
            return null;
        }
        $num = substr($code, strlen(self::PREFIX));
        if ($num === '' || !ctype_digit($num)) {
            return null;
        }
        $n = (int) $num;
        return $n >= 1 ? $n : null;
    }

    /**
     * Check if code matches format MHB- + digits (e.g. MHB-1001, MHB-10001).
     */
    public function isValidFormat(?string $code): bool
    {
        return $this->parseNumericPart($code) !== null;
    }

    /**
     * Validate code: format MHB- + digits and unique in system (excluding product id when editing).
     */
    public function validateCode(?string $code, ?int $excludeProductId = null): void
    {
        if ($code === null || trim((string) $code) === '') {
            return;
        }
        $code = trim($code);
        if (!$this->isValidFormat($code)) {
            throw new \InvalidArgumentException('Product code must start with ' . self::PREFIX . ' followed by digits (e.g. ' . self::PREFIX . '1001).');
        }
        $query = Product::query()->where('product_code', $code);
        if ($excludeProductId !== null) {
            $query->where('id', '!=', $excludeProductId);
        }
        if ($query->exists()) {
            throw new \InvalidArgumentException('Product code is already in use.');
        }
    }

    /**
     * Ensure product has a code: use existing if valid and unique, otherwise generate next (with lock).
     */
    public function ensureCode(?string $code, ?int $excludeProductId = null): string
    {
        $code = $code === null ? '' : trim($code);
        if ($code !== '' && $this->isValidFormat($code)) {
            $query = Product::query()->where('product_code', $code);
            if ($excludeProductId !== null) {
                $query->where('id', '!=', $excludeProductId);
            }
            if (!$query->exists()) {
                return $code;
            }
        }
        return $this->generateNextCode();
    }
}
