<?php declare(strict_types=1);

namespace Simtabi\Laranail\Validation\Rules\Geo;

/**
 * Shared lookup for the subdivision rules.
 *
 * @internal
 */
final class Subdivisions
{
    /**
     * Whether the value matches a subdivision by code or by name.
     *
     * Both comparisons are case-insensitive, and the name comparison collapses
     * internal whitespace, because "new  york" and "New York" are the same
     * answer from a user's point of view and differ only in how carefully the
     * form was filled in.
     *
     * @param  array<string, string>  $subdivisions  code => name
     */
    public static function contains(string $value, array $subdivisions): bool
    {
        $candidate = self::normalise($value);

        if ($candidate === '') {
            return false;
        }

        foreach ($subdivisions as $code => $name) {
            if ($candidate === strtolower($code) || $candidate === self::normalise($name)) {
                return true;
            }
        }

        return false;
    }

    private static function normalise(string $value): string
    {
        return strtolower((string) preg_replace('/\s+/u', ' ', trim($value)));
    }
}
