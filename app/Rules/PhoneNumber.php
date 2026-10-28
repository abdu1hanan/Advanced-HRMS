<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class PhoneNumber implements ValidationRule
{
    /**
     * Run the validation rule.
     *
     * @param  \Closure(string, ?string=): \Illuminate\Translation\PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if ($value === null || $value === '') {
            return;
        }

        if (!is_string($value)) {
            $fail('The :attribute must be a valid phone number string.');
            return;
        }

        // Allow optional leading +, digits, spaces, hyphens, and parentheses
        if (!preg_match('/^\+?[0-9\s\-()]{7,20}$/', $value)) {
            $fail('The :attribute format is invalid. Please enter a valid phone number (e.g. +92 300 1234567).');
            return;
        }

        // Extract digits only and verify standard digit count (7 to 15 digits)
        $digits = preg_replace('/\D/', '', $value);
        if (strlen($digits) < 7 || strlen($digits) > 15) {
            $fail('The :attribute must contain between 7 and 15 digits.');
        }
    }
}
