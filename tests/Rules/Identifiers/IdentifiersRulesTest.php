<?php

declare(strict_types=1);

use Simtabi\Laranail\Validation\Rules\Identifiers\Jwt;
use Simtabi\Laranail\Validation\Rules\Identifiers\Vin;
use Simtabi\Laranail\Validation\Rules\Identifiers\Imei;
use Simtabi\Laranail\Validation\Rules\Identifiers\SemVer;

// =========================================================================
// IMEI
// =========================================================================

it('accepts valid IMEIs', function (string $value): void {
    expect(ruleAccepts(new Imei, $value))->toBeTrue();
})->with([
    '490154203237518',
    '356938035643809',
    '359881030314355',
]);

it('rejects invalid IMEIs', function (string $value): void {
    expect(ruleAccepts(new Imei, $value))->toBeFalse();
})->with([
    '490154203237519',    // Luhn check off by one
    '35693803564380',     // 14 digits
    '3569380356438099',   // 16 digits: IMEISV, which carries no checksum
    '49015420323751a',
    'not-an-imei',
]);

it('rejects IMEISV, which has no check digit to verify', function (): void {
    // Swapping the check digit for a two-digit software version removes the
    // only integrity check the format has. Accepting both widths would mean
    // silently validating nothing for the 16-digit case.
    expect(ruleAccepts(new Imei, '3569380356438099'))->toBeFalse();
});

// =========================================================================
// VIN
// =========================================================================

it('accepts structurally valid VINs', function (string $value): void {
    expect(ruleAccepts(new Vin, $value))->toBeTrue();
})->with([
    '1M8GDM9AXKP042788',
    '1HGCM82633A004352',
    'JH4TB2H26CC000000',
    '5YJ3E1EA6PF384836',
    '1hgcm82633a004352',   // case-insensitive
]);

it('rejects VINs containing I, O or Q', function (string $value): void {
    // Barred throughout the format, being too easily confused with 1 and 0.
    expect(ruleAccepts(new Vin, $value))->toBeFalse();
})->with([
    '1M8GDM9AXKP04278I',
    '1M8GDM9AXKP04278O',
    '1M8GDM9AXKP04278Q',
]);

it('rejects VINs of the wrong length', function (string $value): void {
    expect(ruleAccepts(new Vin, $value))->toBeFalse();
})->with(['1M8GDM9AXKP04278', '1M8GDM9AXKP0427888']);

it('verifies the check digit only when asked', function (): void {
    // The check digit is mandated in North America and conventional elsewhere,
    // so a structurally valid European or Japanese VIN routinely fails it.
    // Enforcing it by default would reject real data.
    $broken = '1M8GDM9A0KP042788';   // valid shape, check digit forced to 0

    expect(ruleAccepts(new Vin, $broken))->toBeTrue()
        ->and(ruleAccepts(new Vin(checkDigit: true), $broken))->toBeFalse()
        ->and(ruleAccepts(new Vin(checkDigit: true), '1M8GDM9AXKP042788'))->toBeTrue();
});

it('handles the X check digit', function (): void {
    // A remainder of 10 is written X — the same escape ISBN-10 and ISSN use,
    // and the reason a VIN cannot be treated as alphanumeric-with-digits.
    expect(ruleAccepts(new Vin(checkDigit: true), '1M8GDM9AXKP042788'))->toBeTrue();
});

// =========================================================================
// SemVer
// =========================================================================

it('accepts valid semantic versions', function (string $value): void {
    expect(ruleAccepts(new SemVer, $value))->toBeTrue();
})->with([
    '0.0.4',
    '1.2.3',
    '10.20.30',
    '1.0.0-alpha',
    '1.0.0-alpha.1',
    '1.0.0-0.3.7',
    '1.0.0-x.7.z.92',
    '1.0.0-alpha+001',
    '1.0.0+20130313144700',
    '1.0.0-beta+exp.sha.5114f85',
    '99999999999999999999999.999999999999999999.99999999999999999',
]);

it('rejects invalid semantic versions', function (string $value): void {
    expect(ruleAccepts(new SemVer, $value))->toBeFalse();
})->with([
    '1',
    '1.2',
    '1.2.3-0123',        // leading zero in a prerelease identifier
    '1.01.1',            // leading zero in a numeric identifier
    '1.2.3-',            // empty prerelease
    'v1.2.3',            // the `v` prefix is not part of the grammar
    '1.2.3.4',
    'alpha',
]);

it('resists catastrophic backtracking', function (): void {
    // The semver.org pattern nests quantifiers inside a repeated group, which
    // is the shape that usually signals a ReDoS. Measure rather than assume:
    // a vulnerable pattern takes exponential time on a long almost-matching
    // prerelease, so anything near-instant here rules it out.
    $pathological = '1.0.0-' . str_repeat('a.', 5000) . '!';

    $start = hrtime(true);
    $result = ruleAccepts(new SemVer, $pathological);
    $elapsedMs = (hrtime(true) - $start) / 1_000_000;

    expect($result)->toBeFalse()
        ->and($elapsedMs)->toBeLessThan(1000.0, "took {$elapsedMs}ms — pattern may be backtracking");
});

// =========================================================================
// JWT
// =========================================================================

it('accepts well-formed JWTs', function (): void {
    $header = rtrim(strtr(base64_encode((string) json_encode(['alg' => 'HS256', 'typ' => 'JWT'])), '+/', '-_'), '=');
    $payload = rtrim(strtr(base64_encode((string) json_encode(['sub' => '123'])), '+/', '-_'), '=');

    expect(ruleAccepts(new Jwt, "{$header}.{$payload}.signature"))->toBeTrue()
        // An unsecured token has an empty signature but keeps both dots.
        ->and(ruleAccepts(new Jwt, "{$header}.{$payload}."))->toBeTrue();
});

it('rejects strings that merely look like JWTs', function (): void {
    // The classic bare-regex failure: three dot-separated base64url runs is a
    // shape, not a token. Decoding the header rejects it for one base64 pass.
    expect(ruleAccepts(new Jwt, 'aaa.bbb.ccc'))->toBeFalse();
});

it('rejects malformed JWTs', function (string $value): void {
    expect(ruleAccepts(new Jwt, $value))->toBeFalse();
})->with([
    'onlyonesegment',
    'two.segments',
    'has spaces.in.it',
    'a.b.c.d',
]);

it('rejects a header that decodes but carries no alg', function (): void {
    $header = rtrim(strtr(base64_encode((string) json_encode(['typ' => 'JWT'])), '+/', '-_'), '=');

    expect(ruleAccepts(new Jwt, "{$header}.eyJzdWIiOiIxMjMifQ.sig"))->toBeFalse();
});

it('accepts alg none, which is well-formed and worthless', function (): void {
    // Documenting the boundary: this rule validates FORM, never trust. An
    // unsecured token is a valid JWT and must still be verified by a JWT
    // library before any claim in it is believed.
    $header = rtrim(strtr(base64_encode((string) json_encode(['alg' => 'none'])), '+/', '-_'), '=');

    expect(ruleAccepts(new Jwt, "{$header}.eyJzdWIiOiIxMjMifQ."))->toBeTrue();
});
