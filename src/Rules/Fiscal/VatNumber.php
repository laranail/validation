<?php declare(strict_types=1);

namespace Simtabi\Laranail\Validation\Rules\Fiscal;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * A VAT identification number, country-aware: the two-letter prefix picks
 * the national format, and where the country defines a checksum it is
 * COMPUTED — NL's 11-proef (or the 2020 mod-97 form), BE's mod-97
 * complement, DE's ISO 7064 MOD 11,10, IT's Luhn variant, SE's Luhn+01,
 * EL's power-of-two sum, LU's mod-89, FR's numeric key. A transposed pair
 * fails arithmetic instead of sailing through a regex, which is the whole
 * point of these digits.
 *
 * Countries whose scheme is letter-juggling without a published single
 * checksum (ES's CIF/NIF menagerie, FR keys containing letters, GB) are
 * validated by format and SAID to be — the docblock is the contract.
 * Spaces, dots and hyphens are stripped and case is folded first, because
 * `BE 0423.456.765` is how the number appears on an invoice.
 *
 * Format validity is not registration: only VIES (or the national
 * registry) can say a number is issued and active, and that is a network
 * question this pure-tier rule deliberately does not ask.
 */
final readonly class VatNumber implements ValidationRule
{
    /** National tail formats, keyed by prefix. */
    private const array PATTERNS = [
        'AT' => 'U[A-Z0-9]{8}',
        'BE' => '[01]\d{9}',
        'BG' => '\d{9,10}',
        'CY' => '\d{8}[A-Z]',
        'CZ' => '\d{8,10}',
        'DE' => '\d{9}',
        'DK' => '\d{8}',
        'EE' => '\d{9}',
        'EL' => '\d{9}',
        'ES' => '[A-Z]\d{7}[A-Z]|\d{8}[A-Z]|[A-Z]\d{8}',
        'FI' => '\d{8}',
        'FR' => '[A-HJ-NP-Z0-9]{2}\d{9}',
        'GB' => '\d{9}|\d{12}|(?:GD|HA)\d{3}',
        'HR' => '\d{11}',
        'HU' => '\d{8}',
        'IE' => '[A-Z0-9]{8}|[A-Z0-9]{9}',
        'IT' => '\d{11}',
        'LT' => '\d{9}|\d{12}',
        'LU' => '\d{8}',
        'LV' => '\d{11}',
        'MT' => '\d{8}',
        'NL' => '\d{9}B\d{2}',
        'PL' => '\d{10}',
        'PT' => '\d{9}',
        'RO' => '\d{2,10}',
        'SE' => '\d{12}',
        'SI' => '\d{8}',
        'SK' => '\d{10}',
    ];

    /**
     * @param  list<string>|null  $countries  Accepted prefixes; null accepts every known one.
     */
    public function __construct(private ?array $countries = null) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || ! $this->passes($value)) {
            $fail('laranail-validation::validation.vat_number')->translate();
        }
    }

    private function passes(string $value): bool
    {
        $normalised = strtoupper((string) preg_replace('/[\s.\-]+/', '', $value));
        $country = substr($normalised, 0, 2);
        $tail = substr($normalised, 2);

        if (! isset(self::PATTERNS[$country])) {
            return false;
        }

        if ($this->countries !== null
            && ! in_array($country, array_map(strtoupper(...), $this->countries), true)) {
            return false;
        }

        if (preg_match('/^(?:' . self::PATTERNS[$country] . ')$/D', $tail) !== 1) {
            return false;
        }

        return match ($country) {
            'NL' => $this->dutch($normalised, $tail),
            'BE' => (int) substr($tail, 8, 2) === 97 - ((int) substr($tail, 0, 8) % 97),
            'DE' => $this->germanMod11x10($tail),
            'IT' => $this->italianLuhn($tail),
            'SE' => str_ends_with($tail, '01') && $this->luhnPasses(substr($tail, 0, 10)),
            'EL' => $this->greekPowerSum($tail),
            'LU' => (int) substr($tail, 6, 2) === (int) substr($tail, 0, 6) % 89,
            'FR' => $this->frenchKey($tail),
            default => true,
        };
    }

    /** The 11-proef, or the 2020 sole-proprietor mod-97 form. */
    private function dutch(string $full, string $tail): bool
    {
        $sum = 0;

        for ($i = 0; $i < 8; ++$i) {
            $sum += ((int) $tail[$i]) * (9 - $i);
        }

        if ($sum % 11 === (int) $tail[8]) {
            return true;
        }

        // Mod-97 over the whole identifier with letters as values (A=10…).
        $converted = '';

        foreach (str_split($full) as $character) {
            $converted .= ctype_digit($character) ? $character : (string) (ord($character) - 55);
        }

        $remainder = 0;

        foreach (str_split($converted) as $digit) {
            $remainder = ($remainder * 10 + (int) $digit) % 97;
        }

        return $remainder === 1;
    }

    /** ISO 7064 MOD 11,10 over the nine digits. */
    private function germanMod11x10(string $digits): bool
    {
        $product = 10;

        for ($i = 0; $i < 8; ++$i) {
            $sum = (((int) $digits[$i]) + $product) % 10;

            if ($sum === 0) {
                $sum = 10;
            }

            $product = (2 * $sum) % 11;
        }

        $check = 11 - $product;

        return ($check === 10 ? 0 : $check) === (int) $digits[8];
    }

    /** Odd positions as-is, even doubled with the 9-fold — total mod 10 is zero. */
    private function italianLuhn(string $digits): bool
    {
        $sum = 0;

        foreach (str_split($digits) as $index => $digit) {
            $digit = (int) $digit;

            if (($index + 1) % 2 === 0) {
                $digit *= 2;

                if ($digit > 9) {
                    $digit -= 9;
                }
            }

            $sum += $digit;
        }

        return $sum % 10 === 0;
    }

    private function greekPowerSum(string $digits): bool
    {
        $sum = 0;

        for ($i = 0; $i < 8; ++$i) {
            $sum += ((int) $digits[$i]) * (2 ** (8 - $i));
        }

        return ($sum % 11) % 10 === (int) $digits[8];
    }

    /** The numeric control key; keys containing letters pass on format alone. */
    private function frenchKey(string $tail): bool
    {
        $key = substr($tail, 0, 2);

        if (! ctype_digit($key)) {
            return true;
        }

        return (int) $key === (12 + 3 * ((int) substr($tail, 2) % 97)) % 97;
    }

    private function luhnPasses(string $digits): bool
    {
        $sum = 0;
        $double = false;

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
