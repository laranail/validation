<?php declare(strict_types=1);

namespace Simtabi\Laranail\Validation\Rules\Codes;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * An International Standard Serial Number (ISO 3297).
 *
 * Eight characters, conventionally printed as two groups of four separated by
 * a hyphen. The final character is a mod-11 check where a remainder of 10 is
 * written `X` — the same escape ISBN-10 uses, and the reason the value cannot
 * be treated as a plain integer.
 *
 * Pure tier — no IO.
 */
final class Issn implements ValidationRule
{
    private const string PATTERN = '/^[0-9]{4}-?[0-9]{3}[0-9X]$/D';

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! self::passes($value)) {
            $fail('laranail-validation::validation.issn')->translate();
        }
    }

    public static function passes(mixed $value): bool
    {
        if (! is_string($value)) {
            return false;
        }

        $issn = strtoupper($value);

        if (preg_match(self::PATTERN, $issn) !== 1) {
            return false;
        }

        $digits = str_replace('-', '', $issn);

        // Weights run 8 down to 2 across the first seven characters.
        $sum = 0;
        for ($i = 0; $i < 7; ++$i) {
            $sum += ((int) $digits[$i]) * (8 - $i);
        }

        $remainder = $sum % 11;
        $check = $remainder === 0 ? '0' : (string) (11 - $remainder);

        return ($check === '10' ? 'X' : $check) === $digits[7];
    }
}
