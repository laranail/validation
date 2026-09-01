<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Validation\Rules\Fiscal;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * A national identification number, in a particular country's scheme.
 *
 *     new NationalIdentifier(NationalIdentifier::NL)   // BSN, 11-proef
 *     'laranail_national_identifier:br'                // CPF
 *
 * One rule parameterised by country rather than a class per scheme: the field
 * always means "this person's national id", and which scheme applies is a
 * property of the country, not of the field.
 *
 * Where a scheme has a checksum it is computed, not pattern-matched — the
 * whole value of these numbers is that a transposed pair fails arithmetic
 * rather than sailing through a regex. Where a scheme has NO checksum (the US
 * SSN does not), that is stated rather than faked.
 *
 * **These identify people.** Storing one makes the record personal data under
 * GDPR and equivalents; validating one does not make it safe to log. Nothing
 * here writes the value anywhere, and the failure messages never echo it.
 *
 * Pure tier — no IO. No rule here can tell you the number was ISSUED, only
 * that it is well-formed; that requires the issuing authority.
 */
final readonly class NationalIdentifier implements ValidationRule
{
    /** Netherlands — burgerservicenummer, validated by the 11-proef. */
    public const string NL = 'nl';

    /** Brazil — Cadastro de Pessoas Físicas, two mod-11 check digits. */
    public const string BR = 'br';

    /** United States — Social Security Number. Format and reserved ranges only. */
    public const string US = 'us';

    /** United Kingdom — National Insurance number. Format and reserved prefixes. */
    public const string GB = 'gb';

    /** France — NIR / numéro de sécurité sociale, mod-97 key. */
    public const string FR = 'fr';

    /** Vietnam — CCCD citizen identification. Structure only: NO checksum exists. */
    public const string VN = 'vn';

    public function __construct(private string $country) {}

    public static function passes(mixed $value, string $country): bool
    {
        if (! is_string($value)) {
            return false;
        }

        return match (mb_strtolower(trim($country))) {
            self::NL => self::dutchBsn($value),
            self::BR => self::brazilianCpf($value),
            self::US => self::americanSsn($value),
            self::GB => self::britishNino($value),
            self::FR => self::frenchNir($value),
            self::VN => self::vietnameseCccd($value),
            default => false,
        };
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! self::passes($value, $this->country)) {
            $fail('laranail/validation::validation.national_identifier')
                ->translate(['country' => mb_strtoupper(trim($this->country))]);
        }
    }

    /**
     * The 11-proef: digits weighted 9..2, the last weighted -1, and the total
     * divisible by 11. A BSN is 8 or 9 digits; the 8-digit form is the same
     * number with a leading zero, so it is padded rather than rejected.
     */
    private static function dutchBsn(string $value): bool
    {
        $digits = self::digits($value);

        if (strlen($digits) === 8) {
            $digits = '0'.$digits;
        }

        if (strlen($digits) !== 9 || $digits === '000000000') {
            return false;
        }

        $sum = 0;

        for ($i = 0; $i < 9; $i++) {
            // The final digit carries weight -1, which is what makes this a
            // check rather than a plain weighted sum.
            $sum += (int) $digits[$i] * ($i === 8 ? -1 : 9 - $i);
        }

        return $sum % 11 === 0;
    }

    /**
     * Two mod-11 check digits over the preceding 9 and 10 digits. A remainder
     * below 2 yields a check digit of 0, not 11 minus it.
     */
    private static function brazilianCpf(string $value): bool
    {
        $digits = self::digits($value);

        if (strlen($digits) !== 11) {
            return false;
        }

        // 11111111111 and friends satisfy the arithmetic but are not issued;
        // every published implementation rejects them explicitly.
        if (preg_match('/^(\d)\1{10}$/D', $digits) === 1) {
            return false;
        }

        foreach ([9, 10] as $length) {
            $sum = 0;

            for ($i = 0; $i < $length; $i++) {
                $sum += (int) $digits[$i] * ($length + 1 - $i);
            }

            $remainder = $sum % 11;
            $check = $remainder < 2 ? 0 : 11 - $remainder;

            if ((int) $digits[$length] !== $check) {
                return false;
            }
        }

        return true;
    }

    /**
     * An SSN has NO check digit — there is nothing to verify arithmetically.
     * What can be checked is the ranges the SSA has never issued.
     */
    private static function americanSsn(string $value): bool
    {
        $value = trim($value);

        if (preg_match('/^(\d{3})-?(\d{2})-?(\d{4})$/D', $value, $m) !== 1) {
            return false;
        }

        [, $area, $group, $serial] = $m;

        // 666 is unissued; 900-999 is reserved for ITINs; 000 never issued.
        if ($area === '000' || $area === '666' || (int) $area >= 900) {
            return false;
        }

        return $group !== '00' && $serial !== '0000';
    }

    /**
     * Format plus the prefixes the scheme reserves. No checksum exists.
     */
    private static function britishNino(string $value): bool
    {
        $value = mb_strtoupper(preg_replace('/\s+/', '', trim($value)) ?? '');

        // D, F, I, Q, U and V are never used in either prefix letter; O is
        // never the second. The final letter is A-D, or absent.
        if (preg_match('/^[ABCEGHJ-PRSTW-Z][ABCEGHJ-NPRSTW-Z]\d{6}[A-D]?$/D', $value) !== 1) {
            return false;
        }

        return ! in_array(substr($value, 0, 2), ['BG', 'GB', 'NK', 'KN', 'TN', 'NT', 'ZZ'], true);
    }

    /**
     * Thirteen digits plus a two-digit key, where key = 97 - (number mod 97).
     *
     * Corsica is the wrinkle: its department is written 2A or 2B, which are
     * not digits. The published rule substitutes 19 and 18 respectively before
     * the modulo.
     */
    private static function frenchNir(string $value): bool
    {
        $value = mb_strtoupper(preg_replace('/\s+/', '', trim($value)) ?? '');

        if (preg_match('/^([12]\d{2}(?:0[1-9]|1[0-2]|[2-9]\d)(?:2[AB]|\d{2})\d{6})(\d{2})$/D', $value, $m) !== 1) {
            return false;
        }

        [, $number, $key] = $m;

        $number = str_replace(['2A', '2B'], ['19', '18'], $number);

        if (preg_match('/^\d{13}$/D', $number) !== 1) {
            return false;
        }

        return (int) $key === 97 - ((int) $number % 97);
    }

    /**
     * Twelve digits: a 001-096 province code, a gender/century digit, the
     * two-digit birth year, and six serial digits. The scheme publishes no
     * check digit, so structure is all that CAN be verified — stated here
     * rather than faked with an invented checksum.
     */
    private static function vietnameseCccd(string $value): bool
    {
        return preg_match('/^0(?:0[1-9]|[1-8]\d|9[0-6])\d{9}$/D', $value) === 1;
    }

    private static function digits(string $value): string
    {
        return preg_replace('/\D/', '', $value) ?? '';
    }
}
