<?php

declare(strict_types=1);

use Illuminate\Contracts\Translation\Translator;
use Illuminate\Translation\PotentiallyTranslatedString;
use Simtabi\Laranail\Validation\Rules\Fiscal\NationalIdentifier as Id;

/**
 * Every vector here is either a published example or was DERIVED from the
 * scheme's own algorithm, never invented to fit the implementation. The
 * French ones in particular: a guessed key was wrong on the first attempt,
 * and computing them is what caught it.
 */

// =========================================================================
// Netherlands — BSN, 11-proef
// =========================================================================

it('accepts a BSN that satisfies the 11-proef', function (string $value): void {
    expect(Id::passes($value, Id::NL))->toBeTrue();
})->with([
    '111222333',   // the canonical test BSN
    '123456782',   // documented valid
    '10000008',    // 8 digits: the same number with the leading zero implied
    '010000008',   // and written out
]);

it('rejects a BSN that does not', function (string $value): void {
    expect(Id::passes($value, Id::NL))->toBeFalse();
})->with([
    'wrong check' => '111222334',
    'all zeroes' => '000000000',
    'too short' => '1234567',
    'too long' => '1112223334',
    'not digits' => 'abcdefghi',
]);

it('weights the final BSN digit negatively', function (): void {
    // This is what makes the 11-proef a check rather than a weighted sum: a
    // plain sum would accept a different final digit.
    expect(Id::passes('111222333', Id::NL))->toBeTrue()
        ->and(Id::passes('111222344', Id::NL))->toBeFalse();
});

// =========================================================================
// Brazil — CPF, two mod-11 check digits
// =========================================================================

it('accepts a CPF punctuated or not', function (string $value): void {
    expect(Id::passes($value, Id::BR))->toBeTrue();
})->with(['111.444.777-35', '11144477735']);

it('rejects a CPF with a bad check digit or a repeated digit', function (string $value): void {
    expect(Id::passes($value, Id::BR))->toBeFalse();
})->with([
    'bad second digit' => '11144477736',
    'bad first digit' => '11144477745',
    'all ones' => '11111111111',
    'all zeroes' => '00000000000',
    'too short' => '1114447773',
]);

// =========================================================================
// United States — SSN. No checksum exists; ranges are all there is.
// =========================================================================

it('accepts a well-formed SSN in an issued range', function (string $value): void {
    expect(Id::passes($value, Id::US))->toBeTrue();
})->with(['078-05-1120', '078051120']);

it('rejects the ranges the SSA has never issued', function (string $value): void {
    expect(Id::passes($value, Id::US))->toBeFalse();
})->with([
    'area 000' => '000-12-3456',
    'area 666' => '666-12-3456',
    'area 900+' => '900-12-3456',
    'area 999' => '999-12-3456',
    'group 00' => '078-00-1120',
    'serial 0000' => '078-05-0000',
    'too short' => '078-05-112',
]);

// =========================================================================
// United Kingdom — NINO
// =========================================================================

it('accepts a NINO with or without spaces and suffix', function (string $value): void {
    expect(Id::passes($value, Id::GB))->toBeTrue();
})->with(['AB123456C', 'AB 12 34 56 C', 'ab123456c', 'AB123456']);

it('rejects reserved prefixes and letters the scheme never uses', function (string $value): void {
    expect(Id::passes($value, Id::GB))->toBeFalse();
})->with([
    'D first' => 'DA123456C',
    'F first' => 'FA123456C',
    'Q first' => 'QQ123456C',
    'U first' => 'UA123456C',
    'V first' => 'VA123456C',
    'O second' => 'AO123456C',
    'reserved BG' => 'BG123456C',
    'reserved GB' => 'GB123456C',
    'reserved NK' => 'NK123456C',
    'reserved TN' => 'TN123456C',
    'reserved ZZ' => 'ZZ123456C',
    'suffix E' => 'AB123456E',
]);

// =========================================================================
// France — NIR, mod-97 key
// =========================================================================

it('accepts a NIR whose key matches, including Corsica', function (string $value): void {
    // 2A and 2B are department codes, not digits; the published rule
    // substitutes 19 and 18 before the modulo.
    expect(Id::passes($value, Id::FR))->toBeTrue();
})->with([
    'mainland' => '269054958815780',
    'key of 97' => '180126745108997',
    'another' => '184017512345658',
    'Corsica 2A' => '199122A12345641',
    'Corsica 2B' => '199122B12345668',
]);

it('rejects a NIR whose key does not match', function (string $value): void {
    expect(Id::passes($value, Id::FR))->toBeFalse();
})->with([
    'wrong key' => '269054958815781',
    'Corsica wrong key' => '199122A12345642',
    'bad sex digit' => '369054958815780',
    'bad month' => '269134958815780',
    'too short' => '26905495881578',
]);

// =========================================================================
// Cross-cutting
// =========================================================================

it('does not pass everything for an unknown country', function (): void {
    expect(Id::passes('111222333', 'zz'))->toBeFalse();
});

it('rejects a non-string', function (mixed $value): void {
    expect(Id::passes($value, Id::NL))->toBeFalse();
})->with([null, 111222333, [['111222333']], true]);

it('names the country in its failure, without echoing the number', function (): void {
    // These identify people. The message must not repeat the value.
    $message = null;
    new Id(Id::NL)->validate('bsn', '111222334', function (string $key) use (&$message): PotentiallyTranslatedString {
        $message = $key;

        return new PotentiallyTranslatedString('', resolve(Translator::class));
    });

    expect($message)->toBe('laranail/validation::validation.national_identifier');
});
