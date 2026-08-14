<?php declare(strict_types=1);

namespace Simtabi\Laranail\Validation\Contracts\Email;

/**
 * MX lookup behind an interface, so deliverability checks are injectable,
 * cacheable and fakeable.
 *
 * Laravel's own `email:dns` uses egulias' DNSCheckValidation directly: no
 * injection point, no caching, and nothing to swap in a test. That is the
 * reason this exists rather than a stylistic preference.
 *
 * This is the only NETWORK-tier email contract. Implementations perform IO and
 * therefore belong to an Actions service, never to a rule.
 */
interface DnsResolver
{
    /**
     * Whether the domain has at least one usable mail exchanger.
     *
     * Implementations should cache, and must not throw on lookup failure —
     * an unreachable resolver is not the same as an undeliverable domain, and
     * a rule cannot tell the difference. Return true when uncertain so a
     * transient DNS outage does not reject valid signups.
     */
    public function hasMailExchanger(string $domain): bool;
}
