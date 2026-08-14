<?php declare(strict_types=1);

namespace Simtabi\Laranail\Validation\Rules\Net;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Simtabi\Laranail\Validation\Rules\Net\Support\IpClassifier;

/**
 * An IP address that is NOT publicly routable — private, loopback,
 * link-local, reserved or multicast.
 *
 * The exact complement of {@see PublicIp} over valid IP addresses: both
 * delegate to the same classifier, so a range cannot be private to one rule
 * and public to the other.
 *
 * Useful for allow-listing internal targets on purpose, such as validating
 * that a configured service address really is on the internal network.
 *
 * Pure tier — no IO.
 */
final class PrivateIp implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || ! IpClassifier::isReserved($value)) {
            $fail('laranail-validation::validation.private_ip')->translate();
        }
    }
}
