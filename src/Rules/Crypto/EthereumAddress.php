<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Validation\Rules\Crypto;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Simtabi\Laranail\Validation\Contracts\ClientCheckable;

/**
 * An Ethereum address: `0x` followed by 40 hexadecimal characters.
 *
 * **The EIP-55 checksum is NOT verified, and that limit is worth stating
 * plainly rather than leaving implied.** Ethereum encodes a checksum in the
 * capitalisation of a mixed-case address, and verifying it requires Keccak-256
 * — the original submission, not the standardised SHA-3. PHP ships `sha3-256`,
 * which uses different padding and produces a different digest, so it cannot
 * stand in. There is no Keccak-256 in PHP core or in any commonly installed
 * extension.
 *
 * The practical consequence: a single mistyped character in an Ethereum
 * address will pass this rule. Unlike {@see BitcoinAddress}, where the
 * checksum is verified and a typo is caught, this is a shape check only. If
 * an application moves funds on the strength of a user-supplied address, it
 * needs a Keccak-256 implementation and an EIP-55 check on top of this — or,
 * better, a confirmation step the user can read.
 *
 * Mixed-case input is accepted rather than rejected. A mixed-case address
 * *claims* an EIP-55 checksum, and refusing what cannot be verified would
 * reject the overwhelming majority of correctly-formed addresses in the wild.
 *
 * Pure tier — no IO.
 */
final class EthereumAddress implements ClientCheckable, ValidationRule
{
    private const string PATTERN = '/^0x[0-9a-fA-F]{40}$/D';

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || preg_match(self::PATTERN, $value) !== 1) {
            $fail('laranail/validation::validation.ethereum_address')->translate();
        }
    }

    /**
     * The whole check is this pattern, so the browser can run the same one
     * rather than a hand-written twin that would drift from it.
     */
    /**
     * @return list<array{rule: string, params: array<array-key, string>}>
     */
    public function clientRules(): array
    {
        return [['rule' => 'regex', 'params' => ['pattern' => self::PATTERN]]];
    }
}
