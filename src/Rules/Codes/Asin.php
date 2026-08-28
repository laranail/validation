<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Validation\Rules\Codes;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * An Amazon Standard Identification Number.
 *
 * Two shapes exist and both are ten characters: modern ASINs start with `B`
 * followed by nine uppercase alphanumerics, and book ASINs are the title's
 * ISBN-10 — which has a checksum, so it is verified through {@see Isbn}
 * rather than pattern-matched. "Any 10 alphanumerics" (the legacy rule)
 * accepts every lowercase typo and checksum-failing ISBN this rejects.
 *
 * Amazon publishes no checksum for the `B` form, so a well-formed B-number
 * being *assigned* cannot be checked here — only its shape can.
 *
 * Pure tier — no IO.
 */
final class Asin implements ValidationRule
{
    public static function passes(string $value): bool
    {
        if (preg_match('/^B[0-9A-Z]{9}$/D', $value) === 1) {
            return true;
        }

        return Isbn::passes($value, [10]);
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || ! self::passes($value)) {
            $fail('laranail/validation::validation.asin')->translate();
        }
    }
}
