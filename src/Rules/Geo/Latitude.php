<?php declare(strict_types=1);

namespace Simtabi\Laranail\Validation\Rules\Geo;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

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
final class Latitude implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! Coordinate::isWithin($value, 90.0)) {
            $fail('laranail-validation::validation.latitude')->translate();
        }
    }
}
