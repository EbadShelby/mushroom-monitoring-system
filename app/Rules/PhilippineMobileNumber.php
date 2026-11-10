<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Translation\PotentiallyTranslatedString;

class PhilippineMobileNumber implements ValidationRule
{
    /**
     * Normalize 09XXXXXXXXX, 639XXXXXXXXX, +639XXXXXXXXX or 9XXXXXXXXX
     * (spaces, dashes and parentheses allowed) to 09XXXXXXXXX.
     * Returns the trimmed input untouched when it is not a valid mobile number.
     */
    public static function normalize(?string $value): ?string
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        $digits = preg_replace('/[\s\-().]/', '', trim($value));

        if (preg_match('/^(?:\+?63|0)?(9\d{9})$/', $digits, $matches) === 1) {
            return '0'.$matches[1];
        }

        return trim($value);
    }

    /**
     * Run the validation rule.
     *
     * @param  Closure(string, ?string=): PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || preg_match('/^09\d{9}$/', $value) !== 1) {
            $fail('The :attribute must be a valid Philippine mobile number (e.g. 09123456789 or +639123456789).');
        }
    }
}
