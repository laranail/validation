<?php declare(strict_types=1);

namespace Simtabi\Laranail\Validation\Rules\Crypto\Support;

/**
 * Bech32 and Bech32m checksum verification (BIP-173 and BIP-350).
 *
 * Native SegWit addresses carry a BCH checksum over GF(32) rather than a
 * truncated hash. It is stronger than Base58Check for its length: it detects
 * any four-character error and, because the alphabet excludes `1`, `b`, `i`
 * and `o`, the errors people actually make are mostly impossible to express.
 *
 * The two constants matter. Witness version 0 uses Bech32 (constant 1);
 * version 1 and above use Bech32m (constant 0x2bc830a3), introduced because
 * Bech32 has a length-extension weakness that Taproot addresses would have
 * been exposed to. Validating a v1 address with the v0 constant accepts
 * addresses that are not spendable, so the version drives the choice.
 *
 * @internal
 */
final class Bech32
{
    private const string CHARSET = 'qpzry9x8gf2tvdw0s3jn54khce6mua7l';

    private const int BECH32_CONST = 1;

    private const int BECH32M_CONST = 0x2BC830A3;

    /** @var list<int> */
    private const array GENERATOR = [0x3B6A57B2, 0x26508E6D, 0x1EA119FA, 0x3D4233DD, 0x2A1462B3];

    /**
     * Whether the value is a valid Bech32/Bech32m string with the given human
     * readable part, and if so what witness version it encodes.
     *
     * Returns null when invalid, so a caller can distinguish "not bech32" from
     * "witness version 0".
     */
    public static function witnessVersion(string $value, string $expectedHrp): ?int
    {
        // Mixed case is explicitly invalid per BIP-173: the checksum is defined
        // over one case, and allowing both would let two different strings
        // claim the same address.
        if ($value !== strtolower($value) && $value !== strtoupper($value)) {
            return null;
        }

        $value = strtolower($value);
        $separator = strrpos($value, '1');

        if ($separator === false || $separator === 0 || $separator + 7 > strlen($value)) {
            return null;
        }

        if (substr($value, 0, $separator) !== $expectedHrp) {
            return null;
        }

        $data = [];
        foreach (str_split(substr($value, $separator + 1)) as $character) {
            $index = strpos(self::CHARSET, $character);

            if ($index === false) {
                return null;
            }

            $data[] = $index;
        }

        $version = $data[0];

        if ($version > 16) {
            return null;
        }

        $constant = $version === 0 ? self::BECH32_CONST : self::BECH32M_CONST;

        if (self::polymod([...self::expandHrp($expectedHrp), ...$data]) !== $constant) {
            return null;
        }

        // The 5-bit data, minus version and the 6 checksum characters,
        // must repack into 20 or 32 bytes.
        $programLength = intdiv((count($data) - 7) * 5, 8);

        if ($version === 0 && $programLength !== 20 && $programLength !== 32) {
            return null;
        }

        return $programLength >= 2 && $programLength <= 40 ? $version : null;
    }

    /** @return list<int> */
    private static function expandHrp(string $hrp): array
    {
        $high = [];
        $low = [];

        foreach (str_split($hrp) as $character) {
            $high[] = ord($character) >> 5;
            $low[] = ord($character) & 31;
        }

        return [...$high, 0, ...$low];
    }

    /** @param  list<int>  $values */
    private static function polymod(array $values): int
    {
        $checksum = 1;

        foreach ($values as $value) {
            $top = $checksum >> 25;
            $checksum = (($checksum & 0x1FFFFFF) << 5) ^ $value;

            for ($i = 0; $i < 5; ++$i) {
                if ((($top >> $i) & 1) === 1) {
                    $checksum ^= self::GENERATOR[$i];
                }
            }
        }

        return $checksum;
    }
}
