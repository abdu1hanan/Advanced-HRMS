<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class StrictEmail implements ValidationRule
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
            $fail('The :attribute must be a valid email address.');
            return;
        }

        if (strlen($value) > 255) {
            $fail('The :attribute may not be greater than 255 characters.');
            return;
        }

        // Disallow dot-less hostnames like 2222@222 or test@test
        if (!filter_var($value, FILTER_VALIDATE_EMAIL)) {
            $fail('The :attribute field must be a valid email address.');
            return;
        }

        // Must contain a domain with a valid TLD of at least 2 characters (e.g. .com, .pk, .co.uk)
        if (!preg_match('/^[a-zA-Z0-9._%+\-]+@[a-zA-Z0-9.\-]+\.[a-zA-Z]{2,}$/', $value)) {
            $fail('The :attribute field must be a valid email address with a valid domain.');
            return;
        }
    }
}
