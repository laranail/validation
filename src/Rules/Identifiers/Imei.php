<?php declare(strict_types=1);

namespace Simtabi\Laranail\Validation\Rules\Identifiers;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Simtabi\Laranail\Validation\Rules\Banking\Luhn;

/**
 * An International Mobile Equipment Identity (3GPP TS 23.003).
 *
 * Fifteen digits: an eight-digit Type Allocation Code, a six-digit serial, and
 * a Luhn check digit.
 *
 * The 16-digit IMEISV is deliberately NOT accepted. It replaces the check
 * digit with a two-digit software version, so it carries no checksum at all —
 * accepting both widths would silently drop the only integrity check the
 * format has.
 *
 * Pure tier — no IO.
 */
final class Imei implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! self::passes($value)) {
            $fail('laranail-validation::validation.imei')->translate();
        }
    }

    public static function passes(mixed $value): bool
    {
        if (! is_string($value) && ! is_int($value)) {
            return false;
        }

        $imei = (string) $value;

        if (! ctype_digit($imei) || strlen($imei) !== 15) {
            return false;
        }

        return Luhn::passes($imei);
    }
}
