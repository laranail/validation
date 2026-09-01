<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Validation\Rules\Email;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Simtabi\Laranail\Validation\Rules\Email\Support\Address;
use Simtabi\Laranail\Validation\Rules\Net\Support\HostPattern;

/**
 * The address is at one of the given domains.
 *
 *     new EmailDomainIs(['example.com'])
 *     new EmailDomainIs(['*.example.com'])       // any subdomain
 *     new EmailDomainIs(['example.com', '*.example.com'])
 *
 * The wildcard matches one or more subdomain labels and does NOT match the
 * bare domain: `*.example.com` accepts `mail.example.com` and rejects
 * `example.com`. That is the strict reading, and the alternative is worse —
 * a rule where "*.corp.example.com" silently also admitted the parent would
 * be a quiet privilege widening in exactly the setting this rule is used for.
 * List both when both are wanted.
 *
 * Matching is on the domain only. Anchoring on a suffix instead — the naive
 * `str_ends_with($email, '@example.com')` — is the classic hole: it accepts
 * `evil-example.com` if the `@` is forgotten, and a `*.example.com` written
 * as `str_contains` accepts `example.com.attacker.test`.
 *
 * Pure tier — no IO. Nothing here asks whether the domain resolves.
 */
final readonly class EmailDomainIs implements ValidationRule
{
    /** @param  list<string>  $domains */
    public function __construct(private array $domains) {}

    /**
     * The matching itself lives in {@see HostPattern}, because the URL rules
     * need exactly these semantics for their host lists. Two copies of an
     * allow-list matcher is two chances to leave a gap, and a gap in one would
     * be a bypass in the other.
     *
     * @param  list<string>  $patterns
     */
    public static function matches(string $domain, array $patterns): bool
    {
        return HostPattern::matches($domain, $patterns);
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $address = Address::split($value);

        if ($address === null) {
            $fail('laranail/validation::validation.email.malformed')->translate();

            return;
        }

        if (! self::matches($address[1], $this->domains)) {
            $fail('laranail/validation::validation.email.domain_is')
                ->translate(['domains' => implode(', ', $this->domains)]);
        }
    }
}
