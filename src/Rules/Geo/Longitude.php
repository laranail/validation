<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Validation\Rules\Geo;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Simtabi\Laranail\Validation\Contracts\ClientCheckable;

/**
 * A longitude in decimal degrees, -180 to 180 inclusive.
 *
 * See {@see Latitude} for why the range matters rather than just `numeric`.
 *
 * Pure tier — no IO.
 */
final class Longitude implements ClientCheckable, ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! Coordinate::isWithin($value, 180.0)) {
            $fail('laranail/validation::validation.longitude')->translate();
        }
    }

    /**
     * `numeric` and a range, which is what the rule is.
     *
     * TWO native rules rather than one regex, and that is the point of the
     * contract returning a list. A bounded numeric range CAN be written as a
     * pattern — something like `-?(?:[0-8]?\\d(?:\\.\\d+)?|180(?:\\.0+)?)` — but it
     * is unreadable, it has to be rewritten for every bound, and getting the
     * boundary wrong means the browser disagreeing with the server on exactly
     * the values that matter. `numeric` carries `is_numeric` semantics
     * (including the exponent notation a CSV import contains) and `between`
     * compares magnitudes, which is precisely the rule's own check.
     *
     * `between` compares by VALUE rather than string length here because the
     * `numeric` rule is present — that is how Laravel decides the unit, and
     * the runner follows it.
     *
     * @return array<int, array<string, string|mixed[]>|array<string, string|array<string, string>>>
     */
    /**
     * @return list<array{rule: string, params: array<array-key, string>}>
     */
    public function clientRules(): array
    {
        return [
            ['rule' => 'numeric', 'params' => []],
            ['rule' => 'between', 'params' => ['min' => '-180', 'max' => '180']],
        ];
    }
}
