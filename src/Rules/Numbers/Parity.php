<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Validation\Rules\Numbers;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * An integer of a given parity.
 *
 * One parameterised rule rather than an `EvenNumber` and an `OddNumber` that
 * would differ by a single character, matching how `Text\CaseStyle` handles
 * the same shape.
 *
 *     new Parity(Parity::EVEN)
 *     'laranail_parity:odd'
 *
 * Only integers have parity. A float is rejected outright rather than
 * truncated, because `2.5` is neither even nor odd and answering either is a
 * guess about what the caller meant. Numeric strings ARE accepted — form input
 * arrives as `"4"`, and rejecting that would make the rule unusable where it
 * is most needed — but only when they denote a whole number.
 *
 * Pure tier — no IO.
 */
final readonly class Parity implements ValidationRule
{
    public const string EVEN = 'even';

    public const string ODD = 'odd';

    public function __construct(private string $parity) {}

    public static function passes(mixed $value, string $parity): bool
    {
        $integer = self::asInteger($value);

        if ($integer === null) {
            return false;
        }

        // abs() before the modulo: PHP's % keeps the sign of the dividend, so
        // -3 % 2 is -1, and a naive `=== 1` check calls every negative odd
        // number even.
        $remainder = abs($integer % 2);

        return match (mb_strtolower(trim($parity))) {
            self::EVEN => $remainder === 0,
            self::ODD => $remainder === 1,
            default => false,
        };
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! self::passes($value, $this->parity)) {
            $fail('laranail/validation::validation.parity.'.$this->normalised())->translate();
        }
    }

    private static function asInteger(mixed $value): ?int
    {
        if (is_int($value)) {
            return $value;
        }

        if (is_float($value)) {
            // A float that happens to be whole is still a float, and 1e20 has
            // no exact int form. Accept only what round-trips.
            return $value === floor($value) && abs($value) <= PHP_INT_MAX ? (int) $value : null;
        }

        if (! is_string($value)) {
            return null;
        }

        $trimmed = trim($value);

        // ctype_digit would reject "-4" and accept "0004"; a bounded pattern
        // says exactly what is meant.
        return preg_match('/^[+-]?\d+$/D', $trimmed) === 1 ? (int) $trimmed : null;
    }

    private function normalised(): string
    {
        $parity = mb_strtolower(trim($this->parity));

        return $parity === self::ODD ? self::ODD : self::EVEN;
    }
}
