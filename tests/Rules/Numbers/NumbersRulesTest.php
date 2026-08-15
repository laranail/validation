<?php declare(strict_types=1);

use Simtabi\Laranail\Validation\Rules\Numbers\MonetaryAmount;
use Simtabi\Laranail\Validation\Rules\Numbers\Parity;

// =========================================================================
// Parity
// =========================================================================

it('classifies whole numbers, including negatives', function (mixed $value, bool $even): void {
    // PHP's % keeps the sign of the dividend, so -3 % 2 is -1. A rule that
    // compared the remainder to 1 would call every negative odd number even.
    expect(Parity::passes($value, Parity::EVEN))->toBe($even)
        ->and(Parity::passes($value, Parity::ODD))->toBe(! $even);
})->with([
    'zero' => [0, true],
    'positive even' => [4, true],
    'positive odd' => [7, false],
    'negative even' => [-4, true],
    'negative odd' => [-3, false],
    'numeric string' => ['4', true],
    'signed string' => ['-3', false],
    'padded string' => ['  6  ', true],
]);

it('rejects values that have no parity', function (mixed $value): void {
    // 2.5 is neither even nor odd; answering either would be a guess about
    // what the caller meant, so both must be false.
    expect(Parity::passes($value, Parity::EVEN))->toBeFalse()
        ->and(Parity::passes($value, Parity::ODD))->toBeFalse();
})->with([
    'fractional' => 2.5,
    'not a number' => 'four',
    'empty' => '',
    'hex-ish' => '0x4',
    'exponent' => '1e2',
    'null' => null,
    'array' => [[2]],
    'bool' => true,
]);

it('accepts a whole float but not one beyond int range', function (): void {
    expect(Parity::passes(4.0, Parity::EVEN))->toBeTrue()
        ->and(Parity::passes(1e30, Parity::EVEN))->toBeFalse();
});

it('rejects an unknown parity name rather than guessing', function (): void {
    expect(Parity::passes(4, 'sideways'))->toBeFalse();
});

it('reports the parity it wanted', function (): void {
    expect(ruleAccepts(new Parity(Parity::ODD), 4))->toBeFalse()
        ->and(ruleAccepts(new Parity(Parity::ODD), 5))->toBeTrue();
});

// =========================================================================
// MonetaryAmount
// =========================================================================

it('accepts a plain decimal amount', function (mixed $value): void {
    expect(MonetaryAmount::passes($value))->toBeTrue();
})->with(['0', '0.00', '12', '12.5', '12.34', '+3.00', 1234, 12.5]);

it('rejects what numeric accepts but nobody typed as a price', function (mixed $value): void {
    // This is the whole reason not to use `numeric|decimal:0,2`: every one of
    // these is numeric to PHP.
    expect(MonetaryAmount::passes($value))->toBeFalse();
})->with([
    'exponent' => '1e3',
    'hex' => '0x1A',
    'infinity' => INF,
    'nan' => NAN,
    'thousands separator' => '1,234.50',
    'currency symbol' => '$12.00',
    'trailing dot' => '12.',
    'too many decimals' => '12.345',
    'negative by default' => '-12.00',
    'empty' => '',
    'spaces only' => '   ',
]);

it('takes the decimal count as a parameter, because currencies differ', function (): void {
    // JPY has no minor unit; KWD has three. Hardcoding two is wrong for both.
    expect(MonetaryAmount::passes('12.345', decimals: 3))->toBeTrue()
        ->and(MonetaryAmount::passes('12.5', decimals: 0))->toBeFalse()
        ->and(MonetaryAmount::passes('12', decimals: 0))->toBeTrue();
});

it('allows a negative only when asked', function (): void {
    expect(MonetaryAmount::passes('-12.00', allowNegative: true))->toBeTrue()
        ->and(MonetaryAmount::passes('-12.00'))->toBeFalse();
});
