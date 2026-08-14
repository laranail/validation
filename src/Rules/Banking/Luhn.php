<?php declare(strict_types=1);

namespace Simtabi\Laranail\Validation\Rules\Banking;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * The Luhn mod-10 checksum (ISO/IEC 7812-1).
 *
 * Used on its own for any Luhn-protected identifier, and as the final step of
 * {@see Isin} and of card-number validation. It is a transcription-error
 * detector, not a proof of existence: a Luhn-valid number is merely
 * well-formed.
 *
 * Pure tier — no IO.
 */
final class Luhn implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! self::passes($value)) {
            $fail('laranail-validation::validation.luhn')->translate();
        }
    }

    public static function passes(mixed $value): bool
    {
        if (! is_string($value) && ! is_int($value)) {
            return false;
        }

        $digits = (string) $value;

        // Reject rather than strip separators. A caller that wants to accept
        // "4111 1111 1111 1111" should normalise first; silently accepting
        // arbitrary punctuation here would also accept "4111-1111_1111.1111".
        // ctype_digit() already rejects the empty string, so no separate
        // emptiness check is needed.
        if (! ctype_digit($digits)) {
            return false;
        }

        $sum = 0;
        $double = false;

        // Right to left: every second digit is doubled, and a doubled value
        // above 9 has its digits summed — equivalent to subtracting 9.
        for ($i = strlen($digits) - 1; $i >= 0; --$i) {
            $digit = (int) $digits[$i];

            if ($double) {
                $digit *= 2;

                if ($digit > 9) {
                    $digit -= 9;
                }
            }

            $sum += $digit;
            $double = ! $double;
        }

        return $sum % 10 === 0;
    }
}
