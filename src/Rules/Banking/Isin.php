<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Validation\Rules\Banking;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * An International Securities Identification Number (ISO 6166).
 *
 * Twelve characters: a two-letter prefix (an ISO 3166-1 country code, or `XS`
 * for international issues), nine alphanumerics, and a check digit.
 *
 * The check digit is Luhn — but only after every letter is expanded to its
 * two-digit ordinal (A=10 … Z=35). Running Luhn over the raw string is a
 * common and silent mistake: it produces a number for every input and
 * accepts roughly one in ten invalid ISINs.
 *
 * Pure tier — no IO.
 */
final class Isin implements ValidationRule
{
    private const string PATTERN = '/^[A-Z]{2}[A-Z0-9]{9}[0-9]$/D';

    public static function passes(mixed $value): bool
    {
        if (! is_string($value)) {
            return false;
        }

        $isin = strtoupper($value);

        if (preg_match(self::PATTERN, $isin) !== 1) {
            return false;
        }

        $expanded = '';
        foreach (str_split($isin) as $character) {
            $expanded .= ctype_alpha($character)
                ? (string) (ord($character) - 55)
                : $character;
        }

        return Luhn::passes($expanded);
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! self::passes($value)) {
            $fail('laranail/validation::validation.isin')->translate();
        }
    }
}
