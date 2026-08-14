<?php declare(strict_types=1);

namespace Simtabi\Laranail\Validation\Rules\Net;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Simtabi\Laranail\Validation\Rules\Net\Support\IpClassifier;

/**
 * A publicly routable IP address, v4 or v6.
 *
 * Intended as an SSRF guard: it rejects loopback, RFC 1918 private space,
 * link-local (where cloud metadata endpoints live), carrier-grade NAT,
 * multicast, documentation and other reserved ranges — and it unwraps
 * IPv4-mapped IPv6 first, so `::ffff:127.0.0.1` is recognised as loopback
 * rather than as a global v6 address.
 *
 * **A passing address is not a safe fetch target.** This rule sees the string
 * in the request, not what a hostname will resolve to at connect time, so it
 * cannot protect against DNS rebinding or a redirect to an internal host.
 * Re-check the resolved address immediately before connecting, and pin it.
 *
 * Pure tier — no IO, no DNS.
 */
final class PublicIp implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || ! IpClassifier::isPubliclyRoutable($value)) {
            $fail('laranail-validation::validation.public_ip')->translate();
        }
    }
}
