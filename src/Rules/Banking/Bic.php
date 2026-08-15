<?php declare(strict_types=1);

namespace Simtabi\Laranail\Validation\Rules\Banking;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * A BIC / SWIFT business identifier code (ISO 9362).
 *
 * Structure is fixed-width and unambiguous:
 *   AAAA  institution   4 letters
 *   BB    country       2 letters, ISO 3166-1 alpha-2
 *   C     location 1    a letter, or a digit 2-9 — `0` and `1` are excluded,
 *                       being reserved for test and reverse-billing codes
 *   D     location 2    alphanumeric except the letter `O`, which is barred
 *                       to stop it being confused with zero
 *   EEE   branch        optional 3 alphanumerics; `XXX` means head office
 *
 * The pattern is fully anchored with only bounded quantifiers, so there is no
 * backtracking to exploit.
 *
 * Pure tier — no IO.
 */
final class Bic implements ValidationRule
{
    private const string PATTERN = '/^[A-Z]{4}[A-Z]{2}[A-Z2-9][A-NP-Z0-9]([A-Z0-9]{3})?$/';

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || preg_match(self::PATTERN, strtoupper($value)) !== 1) {
            $fail('laranail-validation::validation.bic')->translate();
        }
    }
}
