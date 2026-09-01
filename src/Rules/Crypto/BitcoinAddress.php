<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Validation\Rules\Crypto;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Simtabi\Laranail\Validation\Rules\Crypto\Support\Base58Check;
use Simtabi\Laranail\Validation\Rules\Crypto\Support\Bech32;

/**
 * A Bitcoin address, checksum-verified rather than pattern-matched.
 *
 * Every address format Bitcoin uses carries a checksum, and this rule checks
 * it. That distinction matters more here than anywhere else in the library:
 * a mistyped postcode is a failed delivery, a mistyped Bitcoin address is
 * irrecoverable funds. A regex over `[13][a-km-zA-HJ-NP-Z1-9]{25,34}` accepts
 * a single transposed character; the checksum rejects it.
 *
 * Four formats are recognised, all mainnet by default:
 *
 *   P2PKH    `1…`      Base58Check, version byte 0x00
 *   P2SH     `3…`      Base58Check, version byte 0x05
 *   P2WPKH   `bc1q…`   Bech32, witness version 0
 *   P2TR     `bc1p…`   Bech32m, witness version 1
 *
 * Pure tier — no IO. Nothing here asks whether the address has a balance or
 * has ever been used.
 */
final readonly class BitcoinAddress implements ValidationRule
{
    private const int MAINNET_P2PKH = 0x00;

    private const int MAINNET_P2SH = 0x05;

    private const int TESTNET_P2PKH = 0x6F;

    private const int TESTNET_P2SH = 0xC4;

    public function __construct(private bool $testnet = false) {}

    public static function passes(mixed $value, bool $testnet = false): bool
    {
        if (! is_string($value) || $value === '') {
            return false;
        }

        $version = Bech32::witnessVersion($value, $testnet ? 'tb' : 'bc');

        if ($version !== null) {
            return true;
        }

        $versionByte = Base58Check::versionByte($value);

        if ($versionByte === null) {
            return false;
        }

        return in_array(
            $versionByte,
            $testnet
                ? [self::TESTNET_P2PKH, self::TESTNET_P2SH]
                : [self::MAINNET_P2PKH, self::MAINNET_P2SH],
            true,
        );
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! self::passes($value, $this->testnet)) {
            $fail('laranail/validation::validation.bitcoin_address')->translate();
        }
    }
}
