<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Validation\Actions;

use Throwable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Contracts\Cache\Repository;
use Simtabi\Laranail\Validation\Contracts\Email\DnsResolver;

/**
 * The default {@see DnsResolver}: a cached MX lookup over PHP's resolver.
 *
 * Lives in `Actions/` because it performs network IO, which no rule is allowed
 * to do directly — see the tier arch tests. A rule reaches this only through
 * the contract, which is what makes deliverability fakeable in a test and
 * replaceable by `laranail/email` without touching a call site.
 *
 * Three decisions worth stating, because each is a place a naive
 * implementation gets it wrong:
 *
 * 1. **Failure means "yes".** A resolver that is unreachable, rate-limited or
 *    slow is not the same as a domain that cannot receive mail, and this class
 *    cannot tell the two apart. Returning false on a transient outage would
 *    reject every signup for its duration. The contract says return true when
 *    uncertain, and that is deliberate: a deliverability check is a quality
 *    filter, never a security boundary.
 * 2. **No MX is not undeliverable.** RFC 5321 §5.1 says a domain with an
 *    address record but no MX takes delivery at that address, and small
 *    domains genuinely rely on it. Checking MX alone rejects real mailboxes.
 * 3. **Results are cached, negatives included.** The same handful of domains
 *    dominate any signup form, and an uncached lookup on every keystroke is
 *    the cost this contract exists to contain.
 */
final readonly class CachedDnsResolver implements DnsResolver
{
    private const int DEFAULT_TTL = 3600;

    private const string CACHE_PREFIX = 'laranail-validation:mx:';

    public function __construct(
        private ?Repository $cache = null,
        private ?int $ttl = null,
    ) {}

    public function hasMailExchanger(string $domain): bool
    {
        $domain = mb_strtolower(trim($domain, " \t\n\r\0\x0B."));

        if ($domain === '') {
            return false;
        }

        // An IDN has to be punycoded before the resolver sees it; without this
        // every internationalised domain looks like a lookup failure and is
        // waved through by the uncertainty rule below, which is the wrong
        // reason to pass.
        if (! mb_check_encoding($domain, 'ASCII') && function_exists('idn_to_ascii')) {
            $ascii = idn_to_ascii($domain, IDNA_DEFAULT, INTL_IDNA_VARIANT_UTS46);

            if (is_string($ascii) && $ascii !== '') {
                $domain = $ascii;
            }
        }

        return $this->remember($domain, fn (): bool => $this->lookup($domain));
    }

    /**
     * @param callable(): bool $callback
     */
    private function remember(string $domain, callable $callback): bool
    {
        $store = $this->cache ?? $this->defaultStore();

        if (! $store instanceof Repository) {
            return $callback();
        }

        try {
            $value = $store->remember(
                self::CACHE_PREFIX . $domain,
                $this->ttl ?? $this->configuredTtl(),
                static fn (): bool => $callback(),
            );
        } catch (Throwable) {
            // A store that RESOLVES and then cannot answer — a database
            // cache with no migrated table, Redis with the server down.
            // The cache is an optimization; its infrastructure failing
            // costs speed, never a verdict. Same contract as no cache.
            return $callback();
        }

        return (bool) $value;
    }

    private function lookup(string $domain): bool
    {
        // checkdnsrr rather than dns_get_record: it answers the one question
        // asked, and does not allocate the full record set for a domain that
        // may have dozens of MX entries.
        if (@checkdnsrr($domain, 'MX')) {
            return true;
        }

        // The RFC 5321 fallback. Both families, because an IPv6-only mail host
        // is unusual but not wrong.
        if (@checkdnsrr($domain, 'A') || @checkdnsrr($domain, 'AAAA')) {
            return true;
        }

        // Distinguish "the resolver answered no" from "the resolver did not
        // answer". checkdnsrr returns false for both, so probe a name that
        // must exist: if that also fails, DNS itself is unavailable and the
        // uncertainty rule applies.
        return ! @checkdnsrr('a.root-servers.net', 'A');
    }

    private function defaultStore(): ?Repository
    {
        try {
            return Cache::store();
        } catch (Throwable) {
            // No cache configured — every lookup goes to the resolver, which
            // is correct if slow, and far better than failing to validate.
            return null;
        }
    }

    private function configuredTtl(): int
    {
        $ttl = config('laranail.validation.dns.ttl');

        return is_int($ttl) && $ttl > 0 ? $ttl : self::DEFAULT_TTL;
    }
}
