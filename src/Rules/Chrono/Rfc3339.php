<?php declare(strict_types=1);

namespace Simtabi\Laranail\Validation\Rules\Chrono;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * An RFC 3339 timestamp — `2020-12-21T23:59:59Z`, with the grammar's whole
 * envelope: lowercase `t`/`z` allowed, optional fractional seconds, the
 * offset mandatory (`Z` or `±hh:mm`), and second `60` accepted because leap
 * seconds are in the RFC even though PHP cannot represent them.
 *
 * Not `createFromFormat(DateTimeInterface::RFC3339, …)`, which rejects `Z`
 * and fractional-second forms outright (laravel/framework#35387 is this
 * class of bug); the shape is matched by the grammar and the DATE is then
 * checked against the calendar, so `2023-02-29` fails as a date rather
 * than sailing through as a shape.
 *
 * Pure tier — no IO.
 */
final class Rfc3339 implements ValidationRule
{
    private const string GRAMMAR = '/^(?<y>\d{4})-(?<m>\d{2})-(?<d>\d{2})[Tt]'
        . '(?<h>[01]\d|2[0-3]):(?<i>[0-5]\d):(?<s>[0-5]\d|60)(?:\.\d+)?'
        . '(?:[Zz]|[+-](?:[01]\d|2[0-3]):[0-5]\d)$/D';

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || ! self::passes($value)) {
            $fail('laranail-validation::validation.rfc3339')->translate();
        }
    }

    public static function passes(string $value): bool
    {
        if (preg_match(self::GRAMMAR, $value, $parts) !== 1) {
            return false;
        }

        return checkdate((int) $parts['m'], (int) $parts['d'], (int) $parts['y']);
    }
}
