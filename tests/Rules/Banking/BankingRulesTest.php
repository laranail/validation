<?php declare(strict_types=1);

use Illuminate\Support\Facades\Validator;
use Simtabi\Laranail\Validation\Rules\Banking\Bic;
use Simtabi\Laranail\Validation\Rules\Banking\Iban;
use Simtabi\Laranail\Validation\Rules\Banking\Isin;
use Simtabi\Laranail\Validation\Rules\Banking\Luhn;

// =========================================================================
// Luhn
// =========================================================================

it('accepts Luhn-valid numbers', function (string $value): void {
    expect(ruleAccepts(new Luhn(), $value))->toBeTrue();
})->with([
    '4111111111111111',   // Visa test number
    '5500005555555559',   // Mastercard test number
    '340000000000009',    // Amex test number
    '79927398713',        // the canonical Wikipedia example
    '0',                  // single digit, sum 0
]);

it('rejects Luhn-invalid numbers', function (mixed $value): void {
    expect(ruleAccepts(new Luhn(), $value))->toBeFalse();
})->with([
    '4111111111111112',   // last digit off by one
    '79927398710',
    '79927398711',
    '79927398712',
    '4111 1111 1111 1111', // separators are the caller's job to strip
    '4111-1111-1111-1111',
    'abcd',
    '411111111111111a',
]);

it('rejects non-string, non-integer values', function (string $ruleClass, mixed $value): void {
    expect(ruleAccepts(new $ruleClass(), $value))->toBeFalse();
})->with([
    'luhn array' => [Luhn::class, ['x']],
    'luhn bool' => [Luhn::class, true],
    'iban array' => [Iban::class, ['x']],
    'iban int' => [Iban::class, 12345],
    'isin array' => [Isin::class, ['x']],
    'bic array' => [Bic::class, ['x']],
]);

it('leaves an empty value to required, as Laravel does', function (): void {
    // Format rules are not implicit: Laravel skips them for '' and null, so a
    // blank field is `required`'s business. Asserting otherwise here would
    // encode a behaviour that differs from every core rule.
    expect(ruleAccepts(new Iban(), ''))->toBeTrue()
        ->and(ruleAccepts(new Bic(), ''))->toBeTrue()
        ->and(Validator::make(['f' => ''], ['f' => ['required', new Iban()]])->passes())->toBeFalse();
});

it('exposes Luhn as a static so other rules can compose it', function (): void {
    expect(Luhn::passes('79927398713'))->toBeTrue()
        ->and(Luhn::passes('79927398710'))->toBeFalse();
});

// =========================================================================
// IBAN
// =========================================================================

it('accepts valid IBANs', function (string $value): void {
    expect(ruleAccepts(new Iban(), $value))->toBeTrue();
})->with([
    'GB82WEST12345698765432',              // the ISO 13616 example
    'DE89370400440532013000',
    'FR1420041010050500013M02606',
    'NL91ABNA0417164300',
    'gb82west12345698765432',              // case-insensitive
    'GB82 WEST 1234 5698 7654 32',         // printed grouping accepted
]);

it('rejects invalid IBANs', function (mixed $value): void {
    expect(ruleAccepts(new Iban(), $value))->toBeFalse();
})->with([
    'GB82WEST12345698765431',   // checksum broken
    'GB81WEST12345698765432',   // check digits altered
    'GB82WEST1234569876543',    // one char short for GB
    'GB82WEST123456987654321',  // one char long for GB
    'ZZ82WEST12345698765432',   // unknown country prefix
    'DE89370400440532013001',
    'GBWEST12345698765432',     // missing check digits
    'not-an-iban',
]);

it('rejects an unknown country even when the checksum would pass', function (): void {
    // The mod-97 step alone cannot catch a wrong-length or bogus country, so
    // the length table has to be authoritative rather than advisory.
    expect(ruleAccepts(new Iban(), 'ZZ82WEST12345698765432'))->toBeFalse();
});

// =========================================================================
// ISIN
// =========================================================================

it('accepts valid ISINs', function (string $value): void {
    expect(ruleAccepts(new Isin(), $value))->toBeTrue();
})->with([
    'US0378331005',   // Apple
    'AU0000XVGZA3',
    'GB0002634946',   // BAE Systems
    'US30303M1027',   // Meta
    'us0378331005',   // case-insensitive
]);

it('rejects invalid ISINs', function (mixed $value): void {
    expect(ruleAccepts(new Isin(), $value))->toBeFalse();
})->with([
    'US0378331006',   // check digit off by one
    'US037833100',    // too short
    'US03783310051',  // too long
    'US0378331Q05',   // letter where the check digit belongs
    '000378331005',   // digits where the country prefix belongs
]);

it('expands letters before the Luhn step', function (): void {
    // Running Luhn over the raw string is the classic ISIN bug: it yields a
    // verdict for every input and wrongly accepts about one in ten. Pin the
    // difference so a "simplification" cannot reintroduce it.
    expect(Luhn::passes('US0378331005'))->toBeFalse()
        ->and(ruleAccepts(new Isin(), 'US0378331005'))->toBeTrue();
});

// =========================================================================
// BIC
// =========================================================================

it('accepts valid BICs', function (string $value): void {
    expect(ruleAccepts(new Bic(), $value))->toBeTrue();
})->with([
    'DEUTDEFF',       // 8-character head-office form
    'DEUTDEFF500',    // with branch
    'NEDSZAJJ',
    'DABADKKK',
    'UNCRIT2B912',
    'deutdeff',       // case-insensitive
]);

it('rejects invalid BICs', function (mixed $value): void {
    expect(ruleAccepts(new Bic(), $value))->toBeFalse();
})->with([
    'DEUTDEFF5',      // 9 chars: neither 8 nor 11
    'DEUTDEFF50',     // 10 chars
    'DEUTDEFF5000',   // 12 chars
    'DEUT1EFF',       // digit in the institution code
    'DEUTDE1F',       // `1` is barred as the first location character
    'DEUTDE0F',       // so is `0`
    'DEUTDEFO',       // letter `O` is barred as the second, to avoid zero
    'NOTABIC',
]);
