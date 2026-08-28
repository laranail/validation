<?php

declare(strict_types=1);

use Simtabi\Laranail\Validation\Rules\Codes\Asin;
use Simtabi\Laranail\Validation\Rules\Codes\Ismn;
use Simtabi\Laranail\Validation\Rules\Codes\UpcE;

// =========================================================================
// ISMN — 13-digit, 9790 prefix, GTIN-13 checksum
// =========================================================================

it('accepts valid ISMNs, bare and formatted', function (string $value): void {
    expect(ruleAccepts(new Ismn, $value))->toBeTrue();
})->with([
    '9790060115615',        // a real published ISMN
    '979-0-060-11561-5',
    '979 0 060 11561 5',
    '9790123456785',
]);

it('rejects ISMNs with the wrong prefix, length or check digit', function (mixed $value): void {
    expect(ruleAccepts(new Ismn, $value))->toBeFalse();
})->with([
    '9790060115614',   // bad check digit
    '9780060115616',   // 978 is ISBN territory, not ISMN
    '979006011561',    // 12 digits
    '97900601156155',  // 14 digits
    '979006011561a',   // non-digit smuggled where (int) casts would hide it
    9790060115615,     // integers are not accepted: barcodes are strings
    null,
]);

// =========================================================================
// ASIN — B-prefixed Amazon id, or a checksum-valid ISBN-10
// =========================================================================

it('accepts modern B-prefixed ASINs and ISBN-10 ASINs', function (string $value): void {
    expect(ruleAccepts(new Asin, $value))->toBeTrue();
})->with([
    'B01LYCLS24',
    'B000002L5R',
    '0306406152',   // valid ISBN-10 — books keep their ISBN as ASIN
    '097522980X',   // ISBN-10 with the X check digit
]);

it('rejects lowercase, wrong-length and checksum-failing ASINs', function (mixed $value): void {
    // The legacy rule accepted any 10 alphanumerics; the ISBN-10 branch has a
    // checksum and the B branch is uppercase — both are enforced now.
    expect(ruleAccepts(new Asin, $value))->toBeFalse();
})->with([
    'b01lycls24',   // lowercase
    'B01LYCLS2',    // 9 chars
    'B01LYCLS245',  // 11 chars
    '0306406153',   // ISBN-10 checksum fails
    'A123456789',   // neither B-prefixed nor an ISBN-10
    'B01LYC S24',   // embedded space
    12345,
    null,
]);

// =========================================================================
// UPC-E — 8 digits, number system 0/1, checksum of the EXPANDED UPC-A
// =========================================================================

it('accepts valid UPC-E codes across the expansion patterns', function (string $value): void {
    expect(ruleAccepts(new UpcE, $value))->toBeTrue();
})->with([
    '04252614',  // the textbook pair: expands to UPC-A 042100005264
    '01234531',  // last-digit-3 expansion pattern
    '17654346',  // last-digit-4 pattern, number system 1
    '05555550',  // last-digit-5..9 pattern
]);

it('rejects UPC-E codes the legacy checksum would have passed', function (mixed $value): void {
    // The check digit belongs to the EXPANDED UPC-A, not to the 8 compressed
    // digits — checksumming the compressed form (as the legacy rule did) both
    // accepts invalid codes and rejects valid ones.
    expect(ruleAccepts(new UpcE, $value))->toBeFalse();
})->with([
    '04252615',   // bad check digit
    '24252614',   // number system 2 does not exist in UPC-E
    '0425261',    // 7 digits
    '042526144',  // 9 digits
    '0425261a',
    4252614,
    null,
]);
