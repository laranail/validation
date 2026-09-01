<?php

declare(strict_types=1);

use Simtabi\Laranail\Validation\Rules\Crypto\BitcoinAddress;
use Simtabi\Laranail\Validation\Rules\Crypto\EthereumAddress;

// =========================================================================
// Bitcoin — every format carries a checksum, and every one is verified
// =========================================================================

it('accepts real Bitcoin addresses of each format', function (string $address): void {
    expect(ruleAccepts(new BitcoinAddress, $address))->toBeTrue();
})->with([
    'P2PKH (genesis)' => '1A1zP1eP5QGefi2DMPTfTL5SLmv7DivfNa',
    'P2PKH' => '1BvBMSEYstWetqTFn5Au4m4GFg7xJaNVN2',
    'P2SH' => '3J98t1WpEZ73CNmQviecrnyiWrnqRhWNLy',
    'P2WPKH (bech32 v0)' => 'bc1qw508d6qejxtdg4y5r3zarvary0c5xw7kv8f3t4',
    'P2WSH (bech32 v0)' => 'bc1qrp33g0q5c5txsp9arysrx4k6zdkfs4nce4xj0gdcccefvpysxf3qccfmv3',
    'P2TR (bech32m v1)' => 'bc1p5cyxnuxmeuwuvkwfem96lqzszd02n6xdcjrs20cac6yqjjwudpxqkedrcr',
    'uppercase bech32' => 'BC1QW508D6QEJXTDG4Y5R3ZARVARY0C5XW7KV8F3T4',
]);

it('rejects a single mistyped character, which a regex would accept', function (string $address): void {
    // The whole reason for verifying checksums rather than matching a shape.
    // Each of these differs from a real address by one character and is the
    // right length and alphabet throughout.
    expect(ruleAccepts(new BitcoinAddress, $address))->toBeFalse();
})->with([
    'P2PKH last char' => '1A1zP1eP5QGefi2DMPTfTL5SLmv7Divfna',
    'P2SH last char' => '3J98t1WpEZ73CNmQviecrnyiWrnqRhWNLx',
    'bech32 last char' => 'bc1qw508d6qejxtdg4y5r3zarvary0c5xw7kv8f3t5',
    'bech32m last char' => 'bc1p5cyxnuxmeuwuvkwfem96lqzszd02n6xdcjrs20cac6yqjjwudpxqkedrcq',
]);

it('rejects malformed Bitcoin addresses', function (string $address): void {
    expect(ruleAccepts(new BitcoinAddress, $address))->toBeFalse();
})->with([
    'truncated' => '1A1zP1eP5QGefi2DMPTfTL5SLmv7Divf',
    'chars outside base58' => '0OIl1A1zP1eP5QGefi2DMPTfTL5SLmv7',
    'mixed-case bech32' => 'bc1Qw508d6qejxtdg4y5r3zarvary0c5xw7kv8f3t4',
    'wrong hrp' => 'ltc1qw508d6qejxtdg4y5r3zarvary0c5xw7kv8f3t4',
    'not an address' => 'not-an-address',
    'ethereum address' => '0x52908400098527886E0F7030069857D2E4169EE7',
]);

it('rejects testnet addresses on mainnet and the reverse', function (): void {
    $testnetBech32 = 'tb1qw508d6qejxtdg4y5r3zarvary0c5xw7kxpjzsx';
    $mainnetBech32 = 'bc1qw508d6qejxtdg4y5r3zarvary0c5xw7kv8f3t4';

    expect(ruleAccepts(new BitcoinAddress, $testnetBech32))->toBeFalse()
        ->and(ruleAccepts(new BitcoinAddress(testnet: true), $testnetBech32))->toBeTrue()
        ->and(ruleAccepts(new BitcoinAddress(testnet: true), $mainnetBech32))->toBeFalse();
});

it('distinguishes bech32 from bech32m by witness version', function (): void {
    // v0 uses constant 1, v1+ uses 0x2bc830a3. Validating a v1 address with
    // the v0 constant accepts addresses that are not spendable, so getting
    // this wrong is silent rather than loud.
    $v0 = 'bc1qw508d6qejxtdg4y5r3zarvary0c5xw7kv8f3t4';
    $v1 = 'bc1p5cyxnuxmeuwuvkwfem96lqzszd02n6xdcjrs20cac6yqjjwudpxqkedrcr';

    expect(ruleAccepts(new BitcoinAddress, $v0))->toBeTrue()
        ->and(ruleAccepts(new BitcoinAddress, $v1))->toBeTrue();
});

// =========================================================================
// Ethereum — shape only, and the tests say so
// =========================================================================

it('accepts well-formed Ethereum addresses', function (string $address): void {
    expect(ruleAccepts(new EthereumAddress, $address))->toBeTrue();
})->with([
    'all lowercase' => '0x52908400098527886e0f7030069857d2e4169ee7',
    'all uppercase' => '0x52908400098527886E0F7030069857D2E4169EE7',
    'EIP-55 mixed case' => '0x5aAeb6053F3E94C9b9A09f33669435E7Ef1BeAed',
    'zero address' => '0x0000000000000000000000000000000000000000',
]);

it('rejects malformed Ethereum addresses', function (string $address): void {
    expect(ruleAccepts(new EthereumAddress, $address))->toBeFalse();
})->with([
    'no prefix' => '52908400098527886E0F7030069857D2E4169EE7',
    'too short' => '0x52908400098527886E0F7030069857D2E4169EE',
    'too long' => '0x52908400098527886E0F7030069857D2E4169EE77',
    'non-hex' => '0x52908400098527886E0F7030069857D2E4169EEZ',
    'uppercase prefix' => '0X52908400098527886E0F7030069857D2E4169EE7',
    'bitcoin address' => '1A1zP1eP5QGefi2DMPTfTL5SLmv7DivfNa',
]);

it('does not verify the EIP-55 checksum, and a typo therefore passes', function (): void {
    // Documenting the limit where someone will actually read it. Verifying
    // EIP-55 needs Keccak-256 — the original, not SHA-3, which is what PHP
    // ships — so this is a shape check. Contrast BitcoinAddress, where the
    // equivalent typo IS caught.
    $valid = '0x5aAeb6053F3E94C9b9A09f33669435E7Ef1BeAed';
    $checksumBroken = '0x5AAeb6053F3E94C9b9A09f33669435E7Ef1BeAed';   // one case flipped

    expect(ruleAccepts(new EthereumAddress, $valid))->toBeTrue()
        ->and(ruleAccepts(new EthereumAddress, $checksumBroken))->toBeTrue()
        ->and(in_array('keccak256', hash_algos(), true))->toBeFalse();
});
