<?php

declare(strict_types=1);

use Simtabi\Laranail\Validation\Rules\Fiscal\VatNumber;
use Simtabi\Laranail\Validation\Rules\Fiscal\NationalIdentifier;

// =========================================================================
// VatNumber — checksum countries
// =========================================================================

it('accepts VAT numbers whose national checksum holds', function (string $value): void {
    // Each vector minted from the published algorithm, independently of the
    // rule's implementation. NL traced by hand: 1x9+2x8+3x7+4x6+5x5+6x4+7x3
    // +8x2 = 156, 156 mod 11 = 2 = the ninth digit.
    expect(ruleAccepts(new VatNumber, $value))->toBeTrue();
})->with([
    'NL123456782B01',
    'BE0423456765',
    'DE123456788',
    'IT12345678903',
    'SE123456789701',
    'EL123456783',
    'LU12345613',
    'FR32123456789',
]);

it('rejects the same numbers with one digit off', function (string $value): void {
    // A pattern-only port would pass every one of these.
    expect(ruleAccepts(new VatNumber, $value))->toBeFalse();
})->with([
    'NL123456783B01',
    'BE0423456766',
    'DE123456789',
    'IT12345678904',
    'SE123456789801',
    'EL123456784',
    'LU12345614',
    'FR33123456789',
]);

it('accepts pattern-only countries and punctuation people actually type', function (string $value): void {
    expect(ruleAccepts(new VatNumber, $value))->toBeTrue();
})->with([
    'ATU12345678',
    'PL1234567890',
    'ESA1234567B',
    'BE 0423.456.765',    // spaces and dots stripped
    'nl123456782b01',     // case folded
]);

it('rejects unknown prefixes and malformed tails', function (mixed $value): void {
    expect(ruleAccepts(new VatNumber, $value))->toBeFalse();
})->with([
    'XX123456789',
    'NL123456782',      // missing the B-suffix
    'DE12345678',       // eight digits
    'ATU1234567Z9',
    '123456789',        // no country at all
    12345,
    null,
]);

it('can be restricted to named countries', function (): void {
    $benelux = new VatNumber(['NL', 'BE', 'LU']);

    expect(ruleAccepts($benelux, 'NL123456782B01'))->toBeTrue()
        ->and(ruleAccepts($benelux, 'DE123456788'))->toBeFalse();
});

// =========================================================================
// NationalIdentifier — the VN scheme
// =========================================================================

it('accepts well-formed Vietnamese CCCD numbers', function (string $value): void {
    expect(ruleAccepts(new NationalIdentifier(NationalIdentifier::VN), $value))->toBeTrue();
})->with([
    '001204012345',    // Hanoi, male born 20xx
    '096199123456',    // the highest assigned province code
]);

it('rejects CCCD numbers outside the scheme', function (mixed $value): void {
    expect(ruleAccepts(new NationalIdentifier(NationalIdentifier::VN), $value))->toBeFalse();
})->with([
    '000204012345',    // province 000 is not assigned
    '097204012345',    // beyond the assigned range
    '00120401234',     // eleven digits
    '0012040123456',   // thirteen
    '001204O12345',    // letter smuggled in
    12,
    null,
]);
