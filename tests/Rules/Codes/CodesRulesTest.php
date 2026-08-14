<?php declare(strict_types=1);

use Simtabi\Laranail\Validation\Rules\Codes\Ean;
use Simtabi\Laranail\Validation\Rules\Codes\Gtin;
use Simtabi\Laranail\Validation\Rules\Codes\Isbn;
use Simtabi\Laranail\Validation\Rules\Codes\Issn;

// `ruleAccepts()` lives in tests/Rules/Banking/BankingRulesTest.php.

// =========================================================================
// GTIN — one checksum, four widths
// =========================================================================

it('accepts valid GTINs of every width', function (string $value): void {
    expect(ruleAccepts(new Gtin(), $value))->toBeTrue();
})->with([
    '96385074',        // GTIN-8
    '036000291452',    // GTIN-12 / UPC-A
    '4006381333931',   // GTIN-13 / EAN-13
    '00012345600012',  // GTIN-14
]);

it('rejects GTINs with a bad check digit', function (string $value): void {
    expect(ruleAccepts(new Gtin(), $value))->toBeFalse();
})->with([
    '96385075',
    '036000291453',
    '4006381333932',
    '00012345600013',
]);

it('rejects GTINs of an impossible width', function (string $value): void {
    // 9, 10 and 11 digits are not GTIN widths at all, however the digits add up.
    expect(ruleAccepts(new Gtin(), $value))->toBeFalse();
})->with(['9638507', '963850749', '0360002914', '03600029145']);

it('can be narrowed to specific widths', function (): void {
    $thirteenOnly = new Gtin([13]);

    expect(ruleAccepts($thirteenOnly, '4006381333931'))->toBeTrue()
        ->and(ruleAccepts($thirteenOnly, '96385074'))->toBeFalse()
        ->and(ruleAccepts(new Gtin(), '96385074'))->toBeTrue();
});

it('weights the checksum from the right, not the left', function (): void {
    // Weighting from the left gives the same answer for even-length bodies and
    // the wrong one for odd — GTIN-12 and GTIN-14 have odd bodies. Pin both so
    // a "tidier" left-to-right loop cannot pass on the 13-digit case alone.
    expect(Gtin::checkDigit('9638507'))->toBe(4)          // GTIN-8, odd body
        ->and(Gtin::checkDigit('03600029145'))->toBe(2)   // GTIN-12, odd body
        ->and(Gtin::checkDigit('400638133393'))->toBe(1)  // GTIN-13, even body
        ->and(Gtin::checkDigit('0001234560001'))->toBe(2); // GTIN-14, odd body
});

// =========================================================================
// EAN — the retail subset of GTIN
// =========================================================================

it('accepts EAN-8 and EAN-13', function (string $value): void {
    expect(ruleAccepts(new Ean(), $value))->toBeTrue();
})->with(['96385074', '4006381333931']);

it('rejects GTIN widths that are not retail EANs', function (string $value): void {
    // Valid GTINs, but a UPC-A and a shipping carton are not EANs. Accepting
    // them would let a carton code through where a retail code is meant.
    expect(ruleAccepts(new Gtin(), $value))->toBeTrue()
        ->and(ruleAccepts(new Ean(), $value))->toBeFalse();
})->with(['036000291452', '00012345600012']);

// =========================================================================
// ISBN — two different algorithms sharing a name
// =========================================================================

it('accepts valid ISBN-10s', function (string $value): void {
    expect(ruleAccepts(new Isbn(), $value))->toBeTrue();
})->with([
    '0306406152',
    '0-306-40615-2',   // hyphenated
    '0 306 40615 2',   // spaced
    '080442957X',      // check digit X
    '155860832X',
]);

it('accepts valid ISBN-13s', function (string $value): void {
    expect(ruleAccepts(new Isbn(), $value))->toBeTrue();
})->with([
    '9780306406157',
    '978-0-306-40615-7',
    '9791234567896',   // the 979 prefix is equally valid
]);

it('rejects invalid ISBNs', function (string $value): void {
    expect(ruleAccepts(new Isbn(), $value))->toBeFalse();
})->with([
    '0306406153',      // ISBN-10 check off by one
    '9780306406158',   // ISBN-13 check off by one
    '030640615',       // too short
    '03064061522',     // 11 characters is neither edition
    '0X0640615X',      // X is only legal as the final character
    '9770306406158',   // valid GTIN-13, but 977 is serials, not books
]);

it('can be narrowed to one edition', function (): void {
    expect(ruleAccepts(new Isbn([Isbn::EDITION_13]), '9780306406157'))->toBeTrue()
        ->and(ruleAccepts(new Isbn([Isbn::EDITION_13]), '0306406152'))->toBeFalse()
        ->and(ruleAccepts(new Isbn([Isbn::EDITION_10]), '0306406152'))->toBeTrue()
        ->and(ruleAccepts(new Isbn([Isbn::EDITION_10]), '9780306406157'))->toBeFalse();
});

it('requires a book prefix for ISBN-13', function (): void {
    // 977 is the serials prefix. The GS1 checksum passes, so only the prefix
    // check distinguishes an ISSN-derived barcode from an ISBN.
    expect(Gtin::passes('9770306406158', [13]))->toBeTrue()
        ->and(ruleAccepts(new Isbn(), '9770306406158'))->toBeFalse();
});

// =========================================================================
// ISSN
// =========================================================================

it('accepts valid ISSNs', function (string $value): void {
    expect(ruleAccepts(new Issn(), $value))->toBeTrue();
})->with([
    '0378-5955',
    '03785955',        // hyphen is optional
    '2049-3630',
    '0317-8471',
    '1050-124X',       // check digit X
    '1050-124x',       // case-insensitive
]);

it('rejects invalid ISSNs', function (string $value): void {
    expect(ruleAccepts(new Issn(), $value))->toBeFalse();
})->with([
    '0378-5954',       // check off by one
    '0378595',         // too short
    '0378-59555',      // too long
    '037X-5955',       // X is only legal as the final character
    'abcd-efgh',
]);
