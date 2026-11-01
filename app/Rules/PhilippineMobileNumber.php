<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class PhilippineMobileNumber implements ValidationRule
{
    /**
     * Run the validation rule.
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if ($value === null || $value === '') {
            return;
        }

        $digits = preg_replace('/[^0-9]/', '', (string) $value);

        // Normalize 10-digit format starting with 9 (e.g. 9171234567 → 09171234567)
        if (preg_match('/^9\d{9}$/', $digits)) {
            $digits = '0' . $digits;
        }

        // Validate: must be exactly 11 digits starting with 09
        if (!preg_match('/^09\d{9}$/', $digits)) {
            $fail('The :attribute must be a valid Philippine mobile number starting with 09 and exactly 11 digits.');
        }
    }
}