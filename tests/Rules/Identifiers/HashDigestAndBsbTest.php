<?php

declare(strict_types=1);

use Simtabi\Laranail\Validation\Rules\Banking\BsbNumber;
use Simtabi\Laranail\Validation\Rules\Identifiers\HashDigest;

// =========================================================================
// HashDigest
// =========================================================================

it('accepts digests of the declared algorithm', function (string $algorithm, string $digest): void {
    expect(ruleAccepts(new HashDigest($algorithm), $digest))->toBeTrue();
})->with([
    ['md5', '4d74b56ed5e9cbd485fd7d7c08c7364a'],
    ['sha256', '775b14201be10df3962d792fdf9570a221e4c694d291bced32a2350c1be89caf'],
    ['crc32b', '4fc80341'],
    ['sha3-256', 'a357e1d82864783929c155e323c77393ee0926d93294c4e2cfaf9b9041f132dd'],
    ['xxh64', '15640581d7c9bf62'],
    ['md5', '4D74B56ED5E9CBD485FD7D7C08C7364A'],   // uppercase hex is the same digest
]);

it('rejects wrong lengths, non-hex and trailing newlines', function (string $algorithm, mixed $digest): void {
    expect(ruleAccepts(new HashDigest($algorithm), $digest))->toBeFalse();
})->with([
    ['md5', '4d74b56ed5e9cbd485fd7d7c08c7364'],     // 31 chars
    ['sha256', '4d74b56ed5e9cbd485fd7d7c08c7364a'], // an md5 offered as sha256
    ['md5', '4d74b56ed5e9cbd485fd7d7c08c7364g'],    // non-hex
    ['md5', "4d74b56ed5e9cbd485fd7d7c08c7364a\n"],  // the D-modifier class
    ['md5', 12345],
    ['md5', null],
]);

it('refuses an unknown algorithm at construction', function (): void {
    new HashDigest('sha42');
})->throws(InvalidArgumentException::class, 'sha42');

it('advertises the digest pattern for the browser', function (): void {
    $rules = new HashDigest('sha256')->clientRules();

    expect($rules)->toHaveCount(1)
        ->and($rules[0]['rule'])->toBe('regex')
        ->and(preg_match($rules[0]['params']['pattern'], hash('sha256', 'x')))->toBe(1);
});

// =========================================================================
// BsbNumber
// =========================================================================

it('accepts Australian BSB numbers with and without the hyphen', function (string $value): void {
    expect(ruleAccepts(new BsbNumber, $value))->toBeTrue();
})->with(['062-000', '062000', '733100']);

it('rejects malformed BSB numbers', function (mixed $value): void {
    expect(ruleAccepts(new BsbNumber, $value))->toBeFalse();
})->with([
    '062-00',      // five digits
    '0620000',     // seven digits
    '062 000',     // space is not the separator
    '06-2000',     // hyphen in the wrong place
    "062-000\n",   // the D-modifier class
    'abc-def',
    62000,
    null,
]);

it('advertises the BSB pattern for the browser', function (): void {
    $rules = new BsbNumber()->clientRules();

    expect($rules)->toHaveCount(1)
        ->and(preg_match($rules[0]['params']['pattern'], '062-000'))->toBe(1)
        ->and(preg_match($rules[0]['params']['pattern'], "062-000\n"))->toBe(0);
});
