<?php declare(strict_types=1);

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Validator;
use Simtabi\Laranail\Validation\Rules\Chrono\DateInterval;
use Simtabi\Laranail\Validation\Rules\Chrono\MaxDateDifference;
use Simtabi\Laranail\Validation\Rules\Chrono\MinimumAge;
use Simtabi\Laranail\Validation\Rules\Chrono\MinuteIn;
use Simtabi\Laranail\Validation\Rules\Chrono\Rfc3339;
use Simtabi\Laranail\Validation\Rules\Chrono\TimeOfDay;
use Simtabi\Laranail\Validation\Rules\Chrono\TimezoneAbbreviation;
use Simtabi\Laranail\Validation\Rules\Chrono\UnixTimestamp;

afterEach(function (): void {
    Carbon::setTestNow();
});

// =========================================================================
// Rfc3339
// =========================================================================

it('accepts RFC 3339 timestamps, including the forms PHP itself fumbles', function (string $value): void {
    // PHP's own RFC3339 createFromFormat rejects 'Z' and bare-second forms —
    // the laravel/framework#35387 class the legacy rule worked around.
    expect(ruleAccepts(new Rfc3339(), $value))->toBeTrue();
})->with([
    '2020-12-21T23:59:59+00:00',
    '2020-12-21T23:59:59Z',
    '2020-12-21t23:59:59z',            // lowercase t/z are explicitly allowed
    '2020-12-21T23:59:59.123Z',
    '1937-01-01T12:00:27.87+00:20',
    '2024-02-29T00:00:00Z',            // leap year
    '2016-12-31T23:59:60Z',            // a real leap second
]);

it('rejects timestamps that only look like RFC 3339', function (mixed $value): void {
    expect(ruleAccepts(new Rfc3339(), $value))->toBeFalse();
})->with([
    '2023-02-29T00:00:00Z',        // not a leap year
    '2020-02-30T00:00:00Z',        // no such date
    '2020-13-01T00:00:00Z',        // month 13
    '2020-12-21T24:00:00Z',        // hour 24
    '2020-12-21T23:59:59',         // offset is required
    '2020-12-21 23:59:59Z',        // space instead of T
    '2020-12-21T23:59:59+24:00',   // offset hour out of range
    '20-12-21T23:59:59Z',          // two-digit year
    12345,
    null,
]);

// =========================================================================
// TimeOfDay
// =========================================================================

it('accepts 24-hour times, with and without seconds', function (string $value): void {
    expect(ruleAccepts(new TimeOfDay(), $value))->toBeTrue();
})->with(['00:00', '9:30', '23:59', '23:59:59']);

it('rejects out-of-range 24-hour times and meridiems', function (mixed $value): void {
    expect(ruleAccepts(new TimeOfDay(), $value))->toBeFalse();
})->with(['24:00', '23:60', '23:59:60', '9:05 PM', '930', 930, null]);

it('accepts 12-hour times only with a meridiem', function (): void {
    $twelve = new TimeOfDay(twelveHour: true);

    expect(ruleAccepts($twelve, '9:05 PM'))->toBeTrue()
        ->and(ruleAccepts($twelve, '12:00 AM'))->toBeTrue()
        ->and(ruleAccepts($twelve, '09:05pm'))->toBeTrue()
        ->and(ruleAccepts($twelve, '11:30:15 am'))->toBeTrue()
        ->and(ruleAccepts($twelve, '13:00 PM'))->toBeFalse()
        ->and(ruleAccepts($twelve, '0:30 AM'))->toBeFalse()
        ->and(ruleAccepts($twelve, '9:05'))->toBeFalse();
});

it('honours a custom separator', function (): void {
    expect(ruleAccepts(new TimeOfDay(separator: '.'), '23.59'))->toBeTrue()
        ->and(ruleAccepts(new TimeOfDay(separator: '.'), '23:59'))->toBeFalse();
});

// =========================================================================
// UnixTimestamp
// =========================================================================

it('accepts canonical unix timestamps', function (mixed $value): void {
    expect(ruleAccepts(new UnixTimestamp(), $value))->toBeTrue();
})->with(['1724457600', [1724457600], '0', [0]]);

it('rejects floats, leading zeros and pre-epoch values by default', function (mixed $value): void {
    expect(ruleAccepts(new UnixTimestamp(), $value))->toBeFalse();
})->with(['1.5', [1.5], '01', '-1', [-1], '12a', '+5', null]);

it('accepts pre-epoch timestamps only when asked to', function (): void {
    expect(ruleAccepts(new UnixTimestamp(allowNegative: true), '-86400'))->toBeTrue()
        ->and(ruleAccepts(new UnixTimestamp(allowNegative: true), -86400))->toBeTrue()
        ->and(ruleAccepts(new UnixTimestamp(allowNegative: true), '-0'))->toBeFalse();
});

// =========================================================================
// DateInterval
// =========================================================================

it('accepts ISO 8601 durations', function (string $value): void {
    expect(ruleAccepts(new DateInterval(), $value))->toBeTrue();
})->with(['P1Y', 'PT30M', 'P1DT12H', 'P0Y']);

it('rejects strings DateInterval cannot parse', function (mixed $value): void {
    expect(ruleAccepts(new DateInterval(), $value))->toBeFalse();
})->with(['1 day', 'p1y', 'P', 'PT', 'P1H', 12, null]);

it('treats zero as non-positive when positivity is required', function (): void {
    expect(ruleAccepts(new DateInterval(positive: true), 'P1D'))->toBeTrue()
        ->and(ruleAccepts(new DateInterval(positive: true), 'PT1S'))->toBeTrue()
        ->and(ruleAccepts(new DateInterval(positive: true), 'P0Y'))->toBeFalse()
        ->and(ruleAccepts(new DateInterval(positive: true), 'PT0S'))->toBeFalse();
});

// =========================================================================
// MinuteIn
// =========================================================================

it('accepts times whose minutes are in the allowed set', function (): void {
    $quarters = new MinuteIn([0, 15, 30, 45]);

    expect(ruleAccepts($quarters, '10:15'))->toBeTrue()
        ->and(ruleAccepts($quarters, '10:45:59'))->toBeTrue()
        ->and(ruleAccepts($quarters, '2026-08-24 10:30:00'))->toBeTrue()
        ->and(ruleAccepts($quarters, '10:20'))->toBeFalse()
        ->and(ruleAccepts($quarters, 'not a time'))->toBeFalse()
        ->and(ruleAccepts($quarters, 1015))->toBeFalse();
});

it('refuses an empty or out-of-range minute set', function (): void {
    expect(static fn (): MinuteIn => new MinuteIn([]))->toThrow(LogicException::class)
        ->and(static fn (): MinuteIn => new MinuteIn([0, 60]))->toThrow(LogicException::class);
});

// =========================================================================
// MaxDateDifference
// =========================================================================

it('bounds the distance from a fixed reference', function (): void {
    $rule = new MaxDateDifference(12, '2026-08-24 00:00:00');

    expect(ruleAccepts($rule, '2026-08-24 10:00:00'))->toBeTrue()
        ->and(ruleAccepts($rule, '2026-08-23 14:00:00'))->toBeTrue()    // before counts too
        ->and(ruleAccepts($rule, '2026-08-24 12:00:00'))->toBeTrue()    // exactly 12h
        ->and(ruleAccepts($rule, '2026-08-24 12:00:01'))->toBeFalse()
        ->and(ruleAccepts($rule, 'not a date'))->toBeFalse()
        ->and(ruleAccepts($rule, 42))->toBeFalse();
});

it('reads an @-prefixed reference from a sibling field', function (): void {
    $rules = [
        'start_at' => 'required',
        'end_at' => [new MaxDateDifference(48, '@start_at')],
    ];

    expect(Validator::make(
        ['start_at' => '2026-08-24 00:00:00', 'end_at' => '2026-08-25 12:00:00'],
        $rules,
    )->passes())->toBeTrue()
        ->and(Validator::make(
            ['start_at' => '2026-08-24 00:00:00', 'end_at' => '2026-08-27 00:00:00'],
            $rules,
        )->passes())->toBeFalse()
        ->and(Validator::make(['end_at' => '2026-08-25 12:00:00'], ['end_at' => [new MaxDateDifference(48, '@start_at')]])->passes())->toBeFalse();
});

// =========================================================================
// TimezoneAbbreviation
// =========================================================================

it('accepts timezone abbreviations in any case', function (string $value): void {
    expect(ruleAccepts(new TimezoneAbbreviation(), $value))->toBeTrue();
})->with(['EST', 'cet', 'EAT', 'utc']);

it('rejects identifiers and unknown abbreviations', function (mixed $value): void {
    // 'Europe/Berlin' is a timezone IDENTIFIER — Laravel's own `timezone`
    // rule covers those; this rule is the abbreviation set that rule rejects.
    expect(ruleAccepts(new TimezoneAbbreviation(), $value))->toBeFalse();
})->with(['XYZ', 'Europe/Berlin', 'GMT+2', 123, null]);

// =========================================================================
// MinimumAge
// =========================================================================

it('measures age in completed years', function (): void {
    Carbon::setTestNow('2026-08-24 12:00:00');

    expect(ruleAccepts(new MinimumAge(18), '2000-01-01'))->toBeTrue()
        ->and(ruleAccepts(new MinimumAge(18), '2008-08-24'))->toBeTrue()    // 18 today
        ->and(ruleAccepts(new MinimumAge(18), '2008-08-25'))->toBeFalse()   // 18 tomorrow
        ->and(ruleAccepts(new MinimumAge(18), '2010-01-01'))->toBeFalse()
        ->and(ruleAccepts(new MinimumAge(18), '2030-01-01'))->toBeFalse()   // the future
        ->and(ruleAccepts(new MinimumAge(18), 'not a date'))->toBeFalse()
        ->and(ruleAccepts(new MinimumAge(18), 18))->toBeFalse();
});

it('answers in the timezone it is told to', function (): void {
    // 20:00 UTC on the 23rd is already the 24th in Auckland: the same
    // person is 17 in London and 18 at home.
    Carbon::setTestNow('2026-08-23 20:00:00');

    expect(ruleAccepts(new MinimumAge(18, timezone: 'UTC'), '2008-08-24'))->toBeFalse()
        ->and(ruleAccepts(new MinimumAge(18, timezone: 'Pacific/Auckland'), '2008-08-24'))->toBeTrue();
});
