<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Validation\Rules\Geo;

/**
 * Shared numeric handling for the coordinate rules.
 *
 * @internal
 */
final class Coordinate
{
    /**
     * Whether the value is a real number within ±$bound inclusive.
     *
     * `is_numeric` deliberately, not a regex: it accepts int, float and
     * numeric strings including exponent notation, which is what a JSON
     * payload or a CSV import actually contains. It rejects the shapes a
     * hand-rolled pattern usually lets through — '12.34.56', '1,5', '' and
     * a leading '+' followed by nothing.
     *
     * NAN and INF are excluded explicitly: both are numeric to PHP and
     * neither is a position, and NAN fails every comparison silently.
     */
    public static function isWithin(mixed $value, float $bound): bool
    {
        if (is_bool($value) || ! is_numeric($value)) {
            return false;
        }

        $number = (float) $value;

        if (is_nan($number) || is_infinite($number)) {
            return false;
        }

        return $number >= -$bound && $number <= $bound;
    }
}
