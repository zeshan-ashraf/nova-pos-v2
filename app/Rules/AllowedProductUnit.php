<?php

namespace App\Rules;

use App\Models\Product;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Phase 1 reusable rule: product unit must be piece or kg.
 */
class AllowedProductUnit implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! in_array($value, Product::allowedUnits(), true)) {
            $fail('The :attribute must be one of: '.implode(', ', Product::allowedUnits()).'.');
        }
    }
}
