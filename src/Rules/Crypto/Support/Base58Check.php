<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Validation\Rules\Crypto\Support;

/**
 * Base58Check decoding and verification, as used by legacy Bitcoin addresses.
 *
 * Base58 is Base64 with the characters that look alike removed — `0`, `O`,
 * `I` and `l` are all absent — and the trailing four bytes are a checksum:
 * the first four bytes of `sha256(sha256(payload))`. That checksum is the
 * entire reason to do this properly rather than with a pattern. A regex
 * accepts a single mistyped character; the checksum rejects it with
 * overwhelming probability, and for an address that means the difference
 * between a failed form and irrecoverable funds.
 *
 * The decode uses byte-wise carry arithmetic rather than a bignum, so it
 * needs neither GMP nor BCMath — both are optional extensions and a
 * validation rule should not require either.
 *
 * @internal
 */
final class Base58Check
{
    private const string ALPHABET = '123456789ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz';

    /**
     * Verify the payload and return its version byte, or null if the string is
     * not valid Base58Check.
     */
    public static function versionByte(string $value): ?int
    {
        $decoded = self::decode($value);

        if ($decoded === null || strlen($decoded) < 5) {
            return null;
        }

        $payload = substr($decoded, 0, -4);
        $checksum = substr($decoded, -4);

        $expected = substr(hash('sha256', hash('sha256', $payload, true), true), 0, 4);

        if (! hash_equals($expected, $checksum)) {
            return null;
        }

        return ord($payload[0]);
    }

    /**
     * Decode Base58 to raw bytes, or null if the input contains a character
     * outside the alphabet.
     */
    private static function decode(string $value): ?string
    {
        if ($value === '') {
            return null;
        }

        $bytes = [0];

        foreach (str_split($value) as $character) {
            $carry = strpos(self::ALPHABET, $character);

            if ($carry === false) {
                return null;
            }

            for ($i = count($bytes) - 1; $i >= 0; $i--) {
                $carry += $bytes[$i] * 58;
                $bytes[$i] = $carry & 0xFF;
                $carry >>= 8;
            }

            while ($carry > 0) {
                array_unshift($bytes, $carry & 0xFF);
                $carry >>= 8;
            }
        }

        // Each leading '1' encodes one leading zero byte, which the arithmetic
        // above cannot represent — 0 has no digits.
        foreach (str_split($value) as $character) {
            if ($character !== '1') {
                break;
            }

            array_unshift($bytes, 0);
        }

        return implode('', array_map(chr(...), $bytes));
    }
}
