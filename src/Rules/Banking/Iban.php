<?php declare(strict_types=1);

namespace Simtabi\Laranail\Validation\Rules\Banking;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * An International Bank Account Number (ISO 13616).
 *
 * Three checks, in order of cost:
 *   1. shape — two letters, two check digits, then alphanumerics;
 *   2. length — exact, and country-specific; a wrong length is the most
 *      common transcription error and mod-97 alone does not catch it;
 *   3. the ISO 7064 MOD-97-10 checksum.
 *
 * An unknown country prefix fails rather than falling through to a
 * length-agnostic checksum. Accepting `ZZ` because its length is unknown
 * would defeat the point of the table.
 *
 * Pure tier — no IO.
 */
final class Iban implements ValidationRule
{
    /**
     * Total IBAN length per ISO 3166-1 alpha-2 country code, from the SWIFT
     * IBAN Registry. Add a row when a country joins; do not guess a length.
     *
     * @var array<string, int>
     */
    private const LENGTHS = [
        'AD' => 24, 'AE' => 23, 'AL' => 28, 'AT' => 20, 'AZ' => 28,
        'BA' => 20, 'BE' => 16, 'BG' => 22, 'BH' => 22, 'BI' => 27,
        'BR' => 29, 'BY' => 28, 'CH' => 21, 'CR' => 22, 'CY' => 28,
        'CZ' => 24, 'DE' => 22, 'DJ' => 27, 'DK' => 18, 'DO' => 28,
        'EE' => 20, 'EG' => 29, 'ES' => 24, 'FI' => 18, 'FO' => 18,
        'FR' => 27, 'GB' => 22, 'GE' => 22, 'GI' => 23, 'GL' => 18,
        'GR' => 27, 'GT' => 28, 'HR' => 21, 'HU' => 28, 'IE' => 22,
        'IL' => 23, 'IQ' => 23, 'IS' => 26, 'IT' => 27, 'JO' => 30,
        'KW' => 30, 'KZ' => 20, 'LB' => 28, 'LC' => 32, 'LI' => 21,
        'LT' => 20, 'LU' => 20, 'LV' => 21, 'LY' => 25, 'MC' => 27,
        'MD' => 24, 'ME' => 22, 'MK' => 19, 'MR' => 27, 'MT' => 31,
        'MU' => 30, 'NL' => 18, 'NO' => 15, 'PK' => 24, 'PL' => 28,
        'PS' => 29, 'PT' => 25, 'QA' => 29, 'RO' => 24, 'RS' => 22,
        'RU' => 33, 'SA' => 24, 'SC' => 31, 'SD' => 18, 'SE' => 24,
        'SI' => 19, 'SK' => 24, 'SM' => 27, 'SO' => 23, 'ST' => 25,
        'SV' => 28, 'TL' => 23, 'TN' => 24, 'TR' => 26, 'UA' => 29,
        'VA' => 22, 'VG' => 24, 'XK' => 20,
    ];

    private const PATTERN = '/^[A-Z]{2}[0-9]{2}[A-Z0-9]+$/';

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! self::passes($value)) {
            $fail('laranail-validation::validation.iban')->translate();
        }
    }

    public static function passes(mixed $value): bool
    {
        if (! is_string($value)) {
            return false;
        }

        // Printed IBANs are grouped in fours for legibility; the canonical
        // electronic form has no spaces. Both are accepted.
        $iban = strtoupper(str_replace(' ', '', $value));

        if (preg_match(self::PATTERN, $iban) !== 1) {
            return false;
        }

        $country = substr($iban, 0, 2);

        if (! isset(self::LENGTHS[$country]) || strlen($iban) !== self::LENGTHS[$country]) {
            return false;
        }

        return self::checksumIsValid($iban);
    }

    /**
     * ISO 7064 MOD-97-10: move the first four characters to the end, map each
     * letter to two digits (A=10 … Z=35), and require a remainder of 1.
     */
    private static function checksumIsValid(string $iban): bool
    {
        $rearranged = substr($iban, 4) . substr($iban, 0, 4);

        $numeric = '';
        foreach (str_split($rearranged) as $character) {
            $numeric .= ctype_alpha($character)
                ? (string) (ord($character) - 55)
                : $character;
        }

        // The rearranged value far exceeds PHP's integer range, so the modulo
        // is taken piecewise. bcmath would work too, but it is an optional
        // extension and this keeps the rule dependency-free.
        $remainder = 0;
        foreach (str_split($numeric, 7) as $chunk) {
            // At most two carried digits plus a seven-digit chunk, so the
            // concatenation always fits an int and the cast is exact.
            $remainder = ((int) ($remainder . $chunk)) % 97;
        }

        return $remainder === 1;
    }
}
