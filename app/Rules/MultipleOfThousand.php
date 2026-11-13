<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Loan amounts must be multiples of ₱1,000 so the floor-to-₱1,000
 * max-principal rule never rejects an amount whose EMI actually fits.
 */
class MultipleOfThousand implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if ($value === null || $value === '') {
            return;
        }

        if (fmod(round((float) $value, 2), 1000.0) !== 0.0) {
            $fail('The :attribute must be a multiple of ₱1,000.');
        }
    }
}
