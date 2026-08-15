<?php declare(strict_types=1);

namespace Simtabi\Laranail\Validation\Rules\Geo;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Simtabi\Laranail\Validation\Contracts\ClientCheckable;

/**
 * A latitude in decimal degrees, -90 to 90 inclusive.
 *
 * `numeric` alone is not enough: 91 and 1000 are perfectly good numbers and
 * neither is a latitude. Getting the bound wrong is the classic swapped-pair
 * bug — a longitude accepted into a latitude column reads as a plausible
 * coordinate right up until it plots in the wrong hemisphere.
 *
 * Pure tier — no IO.
 */
final class Latitude implements ClientCheckable, ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! Coordinate::isWithin($value, 90.0)) {
            $fail('laranail-validation::validation.latitude')->translate();
        }
    }

    /**
     * `numeric` and a range, which is what the rule is.
     *
     * TWO native rules rather than one regex, and that is the point of the
     * contract returning a list. A bounded numeric range CAN be written as a
     * pattern — something like `-?(?:[0-8]?\\d(?:\\.\\d+)?|90(?:\\.0+)?)` — but it
     * is unreadable, it has to be rewritten for every bound, and getting the
     * boundary wrong means the browser disagreeing with the server on exactly
     * the values that matter. `numeric` carries `is_numeric` semantics
     * (including the exponent notation a CSV import contains) and `between`
     * compares magnitudes, which is precisely the rule's own check.
     *
     * `between` compares by VALUE rather than string length here because the
     * `numeric` rule is present — that is how Laravel decides the unit, and
     * the runner follows it.
     * @return array<int, array<string, string|mixed[]>|array<string, string|array<string, string>>>
     */
    /**
     * @return list<array{rule: string, params: array<array-key, string>}>
     */
    public function clientRules(): array
    {
        return [
            ['rule' => 'numeric', 'params' => []],
            ['rule' => 'between', 'params' => ['min' => '-90', 'max' => '90']],
        ];
    }
}
