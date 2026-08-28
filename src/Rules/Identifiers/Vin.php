<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Validation\Rules\Identifiers;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * A Vehicle Identification Number (ISO 3779).
 *
 * Seventeen characters. `I`, `O` and `Q` are excluded throughout, because they
 * are too easily confused with `1` and `0` on a stamped plate.
 *
 * The ninth character is a check digit, computed by transliterating letters to
 * values, weighting by position and taking mod 11, with `X` standing for 10.
 * That check is mandated in North America and merely conventional elsewhere,
 * so it is **opt-in**: a European or Japanese VIN is structurally valid and
 * routinely fails it. Constructing the rule with `checkDigit: true` enforces
 * it, which is what you want for a US/Canada dataset.
 *
 * Pure tier — no IO.
 */
final readonly class Vin implements ValidationRule
{
    /** I, O and Q never appear in a VIN. */
    private const string PATTERN = '/^[A-HJ-NPR-Z0-9]{17}$/D';

    /**
     * Letter values for the check-digit sum. There is no arithmetic shortcut:
     * the sequence restarts at J and skips I, O and Q, so it is a table.
     *
     * @var array<string, int>
     */
    private const array TRANSLITERATION = [
        'A' => 1, 'B' => 2, 'C' => 3, 'D' => 4, 'E' => 5, 'F' => 6, 'G' => 7, 'H' => 8,
        'J' => 1, 'K' => 2, 'L' => 3, 'M' => 4, 'N' => 5, 'P' => 7, 'R' => 9,
        'S' => 2, 'T' => 3, 'U' => 4, 'V' => 5, 'W' => 6, 'X' => 7, 'Y' => 8, 'Z' => 9,
    ];

    /** Positional weights; the check digit's own position carries zero. */
    private const array WEIGHTS = [8, 7, 6, 5, 4, 3, 2, 10, 0, 9, 8, 7, 6, 5, 4, 3, 2];

    public function __construct(private bool $checkDigit = false) {}

    public static function passes(mixed $value, bool $checkDigit = false): bool
    {
        if (! is_string($value)) {
            return false;
        }

        $vin = strtoupper($value);

        if (preg_match(self::PATTERN, $vin) !== 1) {
            return false;
        }

        return ! $checkDigit || self::checkDigitIsValid($vin);
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! self::passes($value, $this->checkDigit)) {
            $fail('laranail/validation::validation.vin')->translate();
        }
    }

    private static function checkDigitIsValid(string $vin): bool
    {
        $sum = 0;

        foreach (str_split($vin) as $position => $character) {
            $value = ctype_digit($character)
                ? (int) $character
                : self::TRANSLITERATION[$character];

            $sum += $value * self::WEIGHTS[$position];
        }

        $remainder = $sum % 11;

        return ($remainder === 10 ? 'X' : (string) $remainder) === $vin[8];
    }
}
