<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Validation\Rules\Codes;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * A GS1 Global Trade Item Number.
 *
 * One numbering system with four widths, all sharing a single check-digit
 * algorithm:
 *
 *   GTIN-8    EAN-8, for small packaging
 *   GTIN-12   UPC-A, the North American retail barcode
 *   GTIN-13   EAN-13, the international retail barcode
 *   GTIN-14   a shipping-carton grouping of an inner GTIN
 *
 * By default any of the four is accepted; pass explicit lengths to narrow it.
 * {@see Ean} and {@see Isbn} both delegate here rather than restating the
 * checksum.
 *
 * Pure tier — no IO.
 */
final readonly class Gtin implements ValidationRule
{
    private const array VALID_LENGTHS = [8, 12, 13, 14];

    /** @var list<int> */
    private array $lengths;

    /**
     * @param  list<int>  $lengths  Widths to accept; defaults to all four.
     */
    public function __construct(array $lengths = self::VALID_LENGTHS)
    {
        $this->lengths = $lengths === [] ? self::VALID_LENGTHS : $lengths;
    }

    /**
     * @param  list<int>  $lengths
     */
    public static function passes(mixed $value, array $lengths = self::VALID_LENGTHS): bool
    {
        if (! is_string($value) && ! is_int($value)) {
            return false;
        }

        $digits = (string) $value;

        if (! ctype_digit($digits) || ! in_array(strlen($digits), $lengths, true)) {
            return false;
        }

        return self::checkDigit(substr($digits, 0, -1)) === (int) $digits[strlen($digits) - 1];
    }

    /**
     * The GS1 mod-10 check digit for a body (the code without its check digit).
     *
     * Weights alternate 3 and 1 from the RIGHT of the body, so which positions
     * get the 3 depends on the body's length — this is why the same routine
     * serves all four widths, and why weighting from the left silently breaks
     * on odd-length bodies.
     */
    public static function checkDigit(string $body): int
    {
        $sum = 0;
        $weight = 3;

        for ($i = strlen($body) - 1; $i >= 0; $i--) {
            $sum += ((int) $body[$i]) * $weight;
            $weight = $weight === 3 ? 1 : 3;
        }

        return (10 - ($sum % 10)) % 10;
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! self::passes($value, $this->lengths)) {
            $fail('laranail/validation::validation.gtin')->translate();
        }
    }
}
