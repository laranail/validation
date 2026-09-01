<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Validator;
use Simtabi\Laranail\Validation\Rules\Postal\Patterns;
use Simtabi\Laranail\Validation\Rules\Postal\PostalCode;

// =========================================================================
// Fixed country
// =========================================================================

it('accepts postcodes matching the given country', function (string $country, string $value): void {
    expect(ruleAccepts(new PostalCode($country), $value))->toBeTrue();
})->with([
    'US 5-digit' => ['US', '90210'],
    'GB outward+inward' => ['GB', 'SW1A 1AA'],
    'GB short' => ['GB', 'M1 1AE'],
    'NL digits only' => ['NL', '1012'],
    'NL with letters' => ['NL', '1012 AB'],
    'CA' => ['CA', 'K1A 0B1'],
    'CA no space' => ['CA', 'K1A0B1'],
    'JP' => ['JP', '100-0001'],
    'DE' => ['DE', '10115'],
    'BR' => ['BR', '01310-100'],
    'lowercase input' => ['GB', 'sw1a 1aa'],
    'surrounding space' => ['US', ' 90210 '],
]);

it('rejects postcodes that do not match the given country', function (string $country, string $value): void {
    expect(ruleAccepts(new PostalCode($country), $value))->toBeFalse();
})->with([
    'US too short' => ['US', '9021'],
    'US too long' => ['US', '902101'],
    'US letters' => ['US', 'ABCDE'],
    'GB malformed' => ['GB', '12345'],
    'CA missing final digit' => ['CA', 'K1A 0B'],
    'CA barred letter D' => ['CA', 'D1A 0B1'],
    'CA barred letter as second' => ['CA', 'K1I 0B1'],
    'JP without hyphen' => ['JP', '1000001'],
    'NL too many letters' => ['NL', '1012 ABC'],
]);

it('accepts a value valid for any of several countries', function (): void {
    $rule = new PostalCode(['NL', 'GB']);

    expect(ruleAccepts($rule, '1012 AB'))->toBeTrue()
        ->and(ruleAccepts($rule, 'SW1A 1AA'))->toBeTrue()
        ->and(ruleAccepts($rule, '90210'))->toBeFalse();
});

it('fails for an unsupported or missing country rather than passing', function (): void {
    // Silently accepting anything for a country not in the table is how a
    // postcode column fills with junk. The failure is visible; the pass is not.
    expect(ruleAccepts(new PostalCode('ZW'), '12345'))->toBeFalse()
        ->and(ruleAccepts(new PostalCode, '12345'))->toBeFalse();
});

// =========================================================================
// Country read from a sibling field
// =========================================================================

it('reads the country from another field', function (): void {
    $rules = [
        'country' => ['required', 'string'],
        'postcode' => PostalCode::reference('country'),
    ];

    expect(Validator::make(['country' => 'NL', 'postcode' => '1012 AB'], $rules)->passes())->toBeTrue()
        ->and(Validator::make(['country' => 'US', 'postcode' => '1012 AB'], $rules)->passes())->toBeFalse()
        ->and(Validator::make(['country' => 'US', 'postcode' => '90210'], $rules)->passes())->toBeTrue();
});

it('fails when the referenced country is absent or empty', function (): void {
    $rules = ['postcode' => PostalCode::reference('country')];

    expect(Validator::make(['postcode' => '90210'], $rules)->passes())->toBeFalse()
        ->and(Validator::make(['country' => '', 'postcode' => '90210'], $rules)->passes())->toBeFalse();
});

it('resolves a wildcard reference to the same row', function (): void {
    // Without per-row resolution, every row would be validated against the
    // FIRST row's country — wrong in a way that only shows when rows differ.
    $rules = [
        'addresses.*.country' => ['required', 'string'],
        'addresses.*.postcode' => PostalCode::reference('addresses.*.country'),
    ];

    $mixed = ['addresses' => [
        ['country' => 'US', 'postcode' => '90210'],
        ['country' => 'NL', 'postcode' => '1012 AB'],
    ]];

    $crossed = ['addresses' => [
        ['country' => 'US', 'postcode' => '90210'],
        ['country' => 'NL', 'postcode' => '90210'],   // US code in an NL row
    ]];

    expect(Validator::make($mixed, $rules)->passes())->toBeTrue()
        ->and(Validator::make($crossed, $rules)->errors()->keys())->toBe(['addresses.1.postcode']);
});

// =========================================================================
// The table itself
// =========================================================================

it('covers 100 countries with 20 distinct patterns', function (): void {
    $countries = Patterns::countries();
    $patterns = array_unique(array_map(Patterns::for(...), $countries));

    expect($countries)->toHaveCount(100)
        ->and($patterns)->toHaveCount(20);
});

it('contains no country code the ICU database does not recognise', function (): void {
    // The source table carried KV, XY and ZU. KV was Kosovo written with a
    // code that was never assigned (XK is the user-assigned one); the other
    // two are not countries at all.
    $unknown = array_values(array_filter(
        Patterns::countries(),
        static fn (string $code): bool => Locale::getDisplayRegion('-'.$code, 'en') === $code,
    ));

    expect($unknown)->toBeEmpty('unrecognised country codes: '.implode(', ', $unknown));
})->skip(fn (): bool => ! class_exists(Locale::class), 'ext-intl not installed');

it('uses only bounded quantifiers, so no pattern can backtrack catastrophically', function (): void {
    foreach (Patterns::countries() as $country) {
        $pattern = (string) Patterns::for($country);

        expect($pattern)->not->toMatch('/\([^)]*[+*][^)]*\)\s*[+*]/', "quantified group in {$country}")
            ->and($pattern)->not->toMatch('/\([^)]*\|[^)]*\)\s*[+*]/', "quantified alternation in {$country}");
    }
});
