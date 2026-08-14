<?php declare(strict_types=1);

namespace Simtabi\Laranail\Validation\Rules\Email\Support;

/**
 * Splits an address into its local part and domain.
 *
 * Deliberately minimal, and deliberately NOT a value object: the email rules
 * live in laranail/validation and the Email value object lives in
 * laranail/email, which depends on this package. Reaching for it here would
 * invert the dependency.
 *
 * Splits on the LAST `@`, because a quoted local part may legally contain
 * one — `"a@b"@example.com` is a valid address whose domain is example.com.
 *
 * @internal
 */
final class Address
{
    /**
     * @return array{0: string, 1: string}|null  [local part, domain], or null.
     */
    public static function split(mixed $value): ?array
    {
        if (! is_string($value)) {
            return null;
        }

        $position = strrpos($value, '@');

        if ($position === false) {
            return null;
        }

        // Nothing before the @ or nothing after it. Kept as separate
        // comparisons rather than an in_array: this runs once per validated
        // address, and rector.php already records the same reasoning for the
        // fast-check hot path — a literal array allocated per call is not free.
        if ($position === 0 || $position === strlen($value) - 1) {
            return null;
        }

        return [substr($value, 0, $position), strtolower(substr($value, $position + 1))];
    }
}
