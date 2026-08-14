<?php declare(strict_types=1);

namespace Simtabi\Laranail\Validation\Rules\Codes;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * A European Article Number — the retail barcode, in its 8 or 13 digit form.
 *
 * EAN is the subset of {@see Gtin} that appears on consumer packaging, so the
 * checksum is GTIN's. The rule exists separately because "EAN" is what the
 * domain calls it, and because accepting a 12- or 14-digit GTIN where an EAN
 * is meant lets a shipping-carton code through as a retail one.
 *
 * Pure tier — no IO.
 */
final class Ean implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! Gtin::passes($value, [8, 13])) {
            $fail('laranail-validation::validation.ean')->translate();
        }
    }
}
