<?php

declare(strict_types=1);

use Simtabi\Laranail\Validation\Rules\Geo\CaProvince;
use Simtabi\Laranail\Validation\Rules\Geo\Latitude;
use Simtabi\Laranail\Validation\Rules\Geo\LatLng;
use Simtabi\Laranail\Validation\Rules\Geo\Longitude;
use Simtabi\Laranail\Validation\Rules\Geo\UsState;

// =========================================================================
// Latitude / Longitude
// =========================================================================

it('accepts latitudes in range', function (mixed $value): void {
    expect(ruleAccepts(new Latitude, $value))->toBeTrue();
})->with([
    '0', '45.5', '-45.5', '90', '-90', '89.999999', '1e1', '48.8584',
]);

it('rejects latitudes out of range', function (mixed $value): void {
    // `numeric` alone accepts every one of these. The range is the rule.
    expect(ruleAccepts(new Latitude, $value))->toBeFalse();
})->with(['90.0001', '-90.0001', '91', '180', '1000', '-1000']);

it('accepts longitudes in range', function (mixed $value): void {
    expect(ruleAccepts(new Longitude, $value))->toBeTrue();
})->with(['0', '180', '-180', '179.999999', '2.2945']);

it('rejects longitudes out of range', function (mixed $value): void {
    expect(ruleAccepts(new Longitude, $value))->toBeFalse();
})->with(['180.0001', '-180.0001', '181', '360']);

it('rejects values that are not real numbers', function (mixed $value): void {
    expect(ruleAccepts(new Latitude, $value))->toBeFalse()
        ->and(ruleAccepts(new Longitude, $value))->toBeFalse();
})->with([
    '12.34.56',   // the shape a hand-rolled regex usually lets through
    '1,5',
    '45deg',
    'abc',
    '+',
]);

it('rejects NAN and INF, which are numeric but not positions', function (): void {
    // NAN is especially nasty: it fails every comparison silently, so a
    // range check written as `$v > -90 && $v < 90` passes it straight through.
    expect(ruleAccepts(new Latitude, NAN))->toBeFalse()
        ->and(ruleAccepts(new Latitude, INF))->toBeFalse()
        ->and(ruleAccepts(new Longitude, -INF))->toBeFalse();
});

it('rejects booleans, which PHP would otherwise cast to 0 and 1', function (): void {
    expect(ruleAccepts(new Latitude, true))->toBeFalse()
        ->and(ruleAccepts(new Latitude, false))->toBeFalse();
});

// =========================================================================
// LatLng
// =========================================================================

it('accepts coordinate pairs', function (string $value): void {
    expect(ruleAccepts(new LatLng, $value))->toBeTrue();
})->with([
    '48.8584,2.2945',
    '48.8584, 2.2945',      // as copied out of a map
    '0,0',
    '-90,-180',
    '90,180',
    ' 51.5,-0.12 ',
]);

it('rejects malformed coordinate pairs', function (string $value): void {
    expect(ruleAccepts(new LatLng, $value))->toBeFalse();
})->with([
    '48.8584',              // no pair
    '48.8584,2.2945,3',     // three parts
    '48.8584;2.2945',
    'a,b',
    ',',
]);

it('catches the swapped pair when the longitude exceeds latitude range', function (): void {
    // A swapped pair still validates as two numbers, so nothing downstream
    // notices — the point just plots somewhere else. Differing ranges are the
    // only defence available, and they catch the common case.
    expect(ruleAccepts(new LatLng, '2.2945,48.8584'))->toBeTrue()   // both in range: undetectable
        ->and(ruleAccepts(new LatLng, '120.5,45.0'))->toBeFalse();  // 120 is no latitude
});

// =========================================================================
// UsState
// =========================================================================

it('accepts states by code and by name', function (string $value): void {
    expect(ruleAccepts(new UsState, $value))->toBeTrue();
})->with(['CA', 'ca', 'California', 'california', 'NY', 'New York', 'new  york', ' Texas ']);

it('includes DC by default', function (): void {
    // Not a state, but every form asking for one expects it.
    expect(ruleAccepts(new UsState, 'DC'))->toBeTrue()
        ->and(ruleAccepts(new UsState, 'District of Columbia'))->toBeTrue();
});

it('excludes territories unless asked', function (string $value): void {
    expect(ruleAccepts(new UsState, $value))->toBeFalse()
        ->and(ruleAccepts(new UsState(includeTerritories: true), $value))->toBeTrue();
})->with(['PR', 'Puerto Rico', 'GU', 'VI', 'AS', 'MP']);

it('rejects values that are not states', function (string $value): void {
    expect(ruleAccepts(new UsState, $value))->toBeFalse();
})->with(['XX', 'Ontario', 'Californa', 'C', 'United States']);

it('covers all fifty states plus DC', function (): void {
    $codes = [
        'AL', 'AK', 'AZ', 'AR', 'CA', 'CO', 'CT', 'DE', 'FL', 'GA', 'HI', 'ID', 'IL',
        'IN', 'IA', 'KS', 'KY', 'LA', 'ME', 'MD', 'MA', 'MI', 'MN', 'MS', 'MO', 'MT',
        'NE', 'NV', 'NH', 'NJ', 'NM', 'NY', 'NC', 'ND', 'OH', 'OK', 'OR', 'PA', 'RI',
        'SC', 'SD', 'TN', 'TX', 'UT', 'VT', 'VA', 'WA', 'WV', 'WI', 'WY', 'DC',
    ];

    expect($codes)->toHaveCount(51);

    foreach ($codes as $code) {
        expect(ruleAccepts(new UsState, $code))->toBeTrue("missing state code {$code}");
    }
});

// =========================================================================
// CaProvince
// =========================================================================

it('accepts provinces by code and by name', function (string $value): void {
    expect(ruleAccepts(new CaProvince, $value))->toBeTrue();
})->with(['ON', 'on', 'Ontario', 'BC', 'British Columbia', 'Newfoundland and Labrador']);

it('includes the territories by default', function (string $value): void {
    // Canada's three are ordinary places people live and post to; the US
    // territory distinction does not carry over.
    expect(ruleAccepts(new CaProvince, $value))->toBeTrue();
})->with(['NT', 'Northwest Territories', 'NU', 'Nunavut', 'YT', 'Yukon']);

it('accepts both spellings of Quebec', function (): void {
    expect(ruleAccepts(new CaProvince, 'Quebec'))->toBeTrue()
        ->and(ruleAccepts(new CaProvince, 'Québec'))->toBeTrue()
        ->and(ruleAccepts(new CaProvince, 'QC'))->toBeTrue();
});

it('rejects values that are not provinces', function (string $value): void {
    expect(ruleAccepts(new CaProvince, $value))->toBeFalse();
})->with(['XX', 'California', 'Ontari', 'Canada']);

it('covers all thirteen provinces and territories', function (): void {
    $codes = ['AB', 'BC', 'MB', 'NB', 'NL', 'NS', 'NT', 'NU', 'ON', 'PE', 'QC', 'SK', 'YT'];

    expect($codes)->toHaveCount(13);

    foreach ($codes as $code) {
        expect(ruleAccepts(new CaProvince, $code))->toBeTrue("missing province code {$code}");
    }
});
