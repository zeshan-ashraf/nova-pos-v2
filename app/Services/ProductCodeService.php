<?php

namespace App\Services;

use App\Models\Product;

/**
 * Generates unique 4-character product codes.
 * Format: 1 capital letter (A–Z) + 3 digits (0–9). Unique across all products.
 */
class ProductCodeService
{
    private const FIRST_CHARS = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
    private const REST_CHARS = '0123456789';
    private const MAX_ATTEMPTS = 100;

    /**
     * Generate a unique 4-character product code (1 capital letter + 3 digits).
     * Throws if unable to generate a unique code after MAX_ATTEMPTS.
     */
    public function generateUniqueCode(?array $excludeCodes = null): string
    {
        $exclude = $excludeCodes ?? [];
        $attempts = 0;

        do {
            $code = $this->generateOne();
            if (!Product::where('product_code', $code)->exists() && !in_array($code, $exclude, true)) {
                return $code;
            }
            $attempts++;
        } while ($attempts < self::MAX_ATTEMPTS);

        throw new \RuntimeException('Unable to generate a unique product code. Please try again.');
    }

    /**
     * Generate one 4-char code: first char = A–Z, next 3 = digits 0–9.
     */
    private function generateOne(): string
    {
        $first = self::FIRST_CHARS[random_int(0, strlen(self::FIRST_CHARS) - 1)];
        $rest = '';
        $len = strlen(self::REST_CHARS) - 1;
        for ($i = 0; $i < 3; $i++) {
            $rest .= self::REST_CHARS[random_int(0, $len)];
        }
        return $first . $rest;
    }
}
