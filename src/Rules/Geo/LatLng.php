<?php declare(strict_types=1);

namespace Simtabi\Laranail\Validation\Rules\Geo;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * A coordinate pair in `latitude,longitude` order.
 *
 * Latitude first, because that is the order every geocoding API, every map
 * URL and every human writes it in. The opposite order is not merely
 * unconventional — a swapped pair still validates as two numbers, so nothing
 * downstream catches it, and the point silently plots somewhere else. The
 * only defence is that the ranges differ: anything beyond ±90 in the first
 * position is rejected here, which catches the common case of a longitude
 * arriving first.
 *
 * Whitespace around the comma is accepted, since `48.8584, 2.2945` is how it
 * is copied out of a map.
 *
 * Pure tier — no IO.
 */
final class LatLng implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! self::passes($value)) {
            $fail('laranail/validation::validation.lat_lng')->translate();
        }
    }

    public static function passes(mixed $value): bool
    {
        if (! is_string($value) || substr_count($value, ',') !== 1) {
            return false;
        }

        [$latitude, $longitude] = array_map(trim(...), explode(',', $value));

        return Coordinate::isWithin($latitude, 90.0)
            && Coordinate::isWithin($longitude, 180.0);
    }
}
