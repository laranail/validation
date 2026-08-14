<?php declare(strict_types=1);

namespace Simtabi\Laranail\Validation\Rules\Geo;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * A longitude in decimal degrees, -180 to 180 inclusive.
 *
 * See {@see Latitude} for why the range matters rather than just `numeric`.
 *
 * Pure tier — no IO.
 */
final class Longitude implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! Coordinate::isWithin($value, 180.0)) {
            $fail('laranail-validation::validation.longitude')->translate();
        }
    }
}
