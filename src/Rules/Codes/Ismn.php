<?php declare(strict_types=1);

namespace Simtabi\Laranail\Validation\Rules\Codes;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * An International Standard Music Number — the 13-digit form (ISO 10957,
 * current since 2008): the `9790` prefix followed by nine digits, with a
 * GTIN-13 check digit. Hyphens and spaces are stripped first, so the printed
 * form (`979-0-060-11561-5`) validates as-is.
 *
 * An ISMN *is* an EAN-13 in the reserved 979-0 block, so the checksum
 * delegates to {@see Gtin} rather than restating it. The pre-2008 10-character
 * `M-` form is not accepted — the standard withdrew it, and every M-number has
 * a defined 13-digit equivalent (replace `M` with `9790`).
 *
 * Pure tier — no IO.
 */
final class Ismn implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || ! self::passes($value)) {
            $fail('laranail/validation::validation.ismn')->translate();
        }
    }

    public static function passes(string $value): bool
    {
        $digits = str_replace(['-', ' '], '', $value);

        return str_starts_with($digits, '9790') && Gtin::passes($digits, [13]);
    }
}
