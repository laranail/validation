<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Validation\Rules\Net\Support;

use Simtabi\Laranail\Validation\Rules\Net\PrivateIp;
use Simtabi\Laranail\Validation\Rules\Net\PublicIp;

/**
 * Decides whether an IP address is publicly routable.
 *
 * Shared by {@see PublicIp} and
 * {@see PrivateIp} so the two can never
 * disagree — a gap in one would otherwise be a bypass in the other.
 *
 * `filter_var`'s `FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE` is the
 * usual shortcut and it is not sufficient for an SSRF guard. It does not
 * unwrap IPv4-mapped IPv6, so `::ffff:127.0.0.1` reads as an ordinary global
 * v6 address while resolving to loopback; and it does not exclude the
 * carrier-grade NAT range `100.64.0.0/10`, which is not internet-routable.
 *
 * @internal
 */
final class IpClassifier
{
    /**
     * IPv4 ranges that are not publicly routable, as [network, prefix length].
     *
     * @var list<array{string, int}>
     */
    private const array V4_RESERVED = [
        ['0.0.0.0', 8],          // "this network" (RFC 1122)
        ['10.0.0.0', 8],         // private (RFC 1918)
        ['100.64.0.0', 10],      // carrier-grade NAT (RFC 6598)
        ['127.0.0.0', 8],        // loopback
        ['169.254.0.0', 16],     // link-local — cloud metadata lives here
        ['172.16.0.0', 12],      // private (RFC 1918)
        ['192.0.0.0', 24],       // IETF protocol assignments
        ['192.0.2.0', 24],       // documentation (TEST-NET-1)
        ['192.88.99.0', 24],     // deprecated 6to4 relay anycast
        ['192.168.0.0', 16],     // private (RFC 1918)
        ['198.18.0.0', 15],      // benchmarking (RFC 2544)
        ['198.51.100.0', 24],    // documentation (TEST-NET-2)
        ['203.0.113.0', 24],     // documentation (TEST-NET-3)
        ['224.0.0.0', 4],        // multicast
        ['240.0.0.0', 4],        // reserved, including 255.255.255.255
    ];

    /**
     * IPv6 ranges that are not publicly routable.
     *
     * @var list<array{string, int}>
     */
    private const array V6_RESERVED = [
        ['::', 128],             // unspecified
        ['::1', 128],            // loopback
        ['100::', 64],           // discard-only (RFC 6666)
        ['2001:db8::', 32],      // documentation
        ['fc00::', 7],           // unique local (RFC 4193)
        ['fe80::', 10],          // link-local
        ['ff00::', 8],           // multicast
    ];

    public static function isValid(string $ip): bool
    {
        return filter_var($ip, FILTER_VALIDATE_IP) !== false;
    }

    public static function isPubliclyRoutable(string $ip): bool
    {
        if (! self::isValid($ip)) {
            return false;
        }

        return ! self::isReserved($ip);
    }

    public static function isReserved(string $ip): bool
    {
        if (! self::isValid($ip)) {
            return false;
        }

        // Unwrap first. ::ffff:127.0.0.1 and ::ffff:7f00:1 are both loopback
        // written as v6, and treating them as global addresses is the classic
        // SSRF filter bypass.
        $unwrapped = self::unwrapMappedV4($ip);

        if ($unwrapped !== null) {
            return self::matchesAny($unwrapped, self::V4_RESERVED);
        }

        return filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false
            ? self::matchesAny($ip, self::V4_RESERVED)
            : self::matchesAny($ip, self::V6_RESERVED);
    }

    /**
     * Returns the dotted-quad form of an IPv4-mapped or IPv4-compatible IPv6
     * address, or null when the address is not one.
     */
    private static function unwrapMappedV4(string $ip): ?string
    {
        $packed = inet_pton($ip);

        if ($packed === false || strlen($packed) !== 16) {
            return null;
        }

        // ::ffff:a.b.c.d — 80 zero bits, 16 one bits, then the v4 address.
        $isMapped = str_starts_with($packed, str_repeat("\x00", 10)."\xff\xff");

        // ::a.b.c.d, the deprecated IPv4-compatible form. `::` and `::1` are
        // handled by the v6 table, so exclude anything that low.
        $isCompatible = str_starts_with($packed, str_repeat("\x00", 12))
            && substr($packed, 12) > "\x00\x00\x00\x01";

        if (! $isMapped && ! $isCompatible) {
            return null;
        }

        $dotted = inet_ntop(substr($packed, 12));

        return $dotted === false ? null : $dotted;
    }

    /**
     * @param  list<array{string, int}>  $ranges
     */
    private static function matchesAny(string $ip, array $ranges): bool
    {
        $packed = inet_pton($ip);

        if ($packed === false) {
            return false;
        }

        foreach ($ranges as [$network, $prefix]) {
            $networkPacked = inet_pton($network);

            if ($networkPacked === false || strlen($networkPacked) !== strlen($packed)) {
                continue;
            }

            if (self::sharesPrefix($packed, $networkPacked, $prefix)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Compare the first $prefix bits of two packed addresses. Whole bytes are
     * compared directly and the remainder through a mask, which keeps this
     * correct for prefixes that do not fall on a byte boundary — /10, /12 and
     * /7 all appear in the tables above.
     */
    private static function sharesPrefix(string $a, string $b, int $prefix): bool
    {
        $wholeBytes = intdiv($prefix, 8);
        $remainingBits = $prefix % 8;

        if ($wholeBytes > 0 && strncmp($a, $b, $wholeBytes) !== 0) {
            return false;
        }

        if ($remainingBits === 0) {
            return true;
        }

        $mask = 0xFF << (8 - $remainingBits) & 0xFF;

        return (ord($a[$wholeBytes]) & $mask) === (ord($b[$wholeBytes]) & $mask);
    }
}
