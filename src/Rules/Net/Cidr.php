<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Validation\Rules\Net;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Simtabi\Laranail\Validation\Rules\Net\Support\IpClassifier;

/**
 * An IP network in CIDR notation — an address, a slash, and a prefix length.
 *
 * The prefix is bounded by the address family: 0-32 for v4, 0-128 for v6.
 * `10.0.0.0/64` is a common copy-paste error between the two and is rejected.
 *
 * The address is NOT required to be the network address, so `10.0.0.1/8`
 * passes. That form is ubiquitous in interface configuration ("this host, on
 * this network") and rejecting it would surprise more people than it helps.
 *
 * Pure tier — no IO.
 */
final class Cidr implements ValidationRule
{
    public static function passes(mixed $value): bool
    {
        if (! is_string($value) || substr_count($value, '/') !== 1) {
            return false;
        }

        [$address, $prefix] = explode('/', $value);

        if (! IpClassifier::isValid($address)) {
            return false;
        }

        // Reject '', '+8', ' 8' and '08' — ctype_digit plus an explicit
        // leading-zero check, since (int) would happily accept all of them.
        if ($prefix === '' || ! ctype_digit($prefix) || ($prefix !== '0' && str_starts_with($prefix, '0'))) {
            return false;
        }

        $max = filter_var($address, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false ? 32 : 128;

        return (int) $prefix <= $max;
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! self::passes($value)) {
            $fail('laranail/validation::validation.cidr')->translate();
        }
    }
}
