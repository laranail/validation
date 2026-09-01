<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Validation\Rules\Net\Support;

/**
 * Matches a host name against a list of patterns, with `*.` for subdomains.
 *
 *     HostPattern::matches('mail.example.com', ['*.example.com'])   // true
 *     HostPattern::matches('example.com', ['*.example.com'])        // false
 *
 * The wildcard matches one or more subdomain labels and does NOT match the
 * bare domain. That is the strict reading, and the alternative is worse — a
 * rule where `*.corp.example.com` silently also admitted the parent would be a
 * quiet privilege widening in exactly the setting these lists are used for.
 * List both when both are wanted.
 *
 * Anchoring on a suffix instead — the naive `str_ends_with($host, 'example.com')`
 * — is the classic hole: it accepts `evil-example.com`, and written as
 * `str_contains` it accepts `example.com.attacker.test`. The leading dot in the
 * comparison below is what closes it.
 *
 * Shared by the email domain rules and the URL host lists so the two cannot
 * disagree: a gap in one would otherwise be a bypass in the other, and these
 * are allow-lists.
 *
 * @internal
 */
final class HostPattern
{
    /**
     * @param  list<string>  $patterns
     */
    public static function matches(string $host, array $patterns): bool
    {
        $host = strtolower(trim($host));

        foreach ($patterns as $pattern) {
            $pattern = strtolower(trim($pattern));

            if ($pattern === '') {
                continue;
            }

            if (str_starts_with($pattern, '*.')) {
                $parent = substr($pattern, 2);

                // The leading dot is the whole point: without it,
                // `evilexample.com` matches `*.example.com`.
                if ($parent !== '' && str_ends_with($host, '.'.$parent)) {
                    return true;
                }

                continue;
            }

            if ($host === $pattern) {
                return true;
            }
        }

        return false;
    }
}
