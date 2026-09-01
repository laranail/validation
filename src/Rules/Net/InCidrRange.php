<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Validation\Rules\Net;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Simtabi\Laranail\Validation\Rules\Net\Support\IpClassifier;

/**
 * An IP address inside one of the given CIDR networks.
 *
 *     new InCidrRange(['10.0.0.0/8', '192.168.0.0/16'])
 *     new InCidrRange(['2001:db8::/32'])
 *
 * The rule an office allow-list, a webhook source check or a "this must be on
 * the VPN" field actually needs. Written at the call site it is almost always
 * either a string comparison (`str_starts_with($ip, '10.')`, which misses
 * `10.0.0.1` written as `::ffff:10.0.0.1` and matches `100.0.0.1`) or an
 * `ip2long` mask, which silently returns false for every IPv6 address.
 *
 * Comparison is on the packed bytes, so a prefix that does not land on a byte
 * boundary — `/10`, `/12`, `/7` — is handled, and the two address families
 * never match each other: a v4 address is not in a v6 network, whatever the
 * bits look like.
 *
 * **IPv4-mapped v6 is unwrapped first.** `::ffff:10.0.0.1` is 10.0.0.1, and a
 * range check that treats it as an unrelated v6 address is how an allow-list
 * gets bypassed. That is the same unwrapping {@see IpClassifier} does, for the
 * same reason.
 *
 * Pure tier — no IO.
 */
final readonly class InCidrRange implements ValidationRule
{
    /** @param  list<string>  $networks  CIDR notation; an invalid entry never matches. */
    public function __construct(private array $networks) {}

    /**
     * @param  list<string>  $networks
     */
    public static function passes(mixed $value, array $networks): bool
    {
        if (! is_string($value) || ! IpClassifier::isValid($value)) {
            return false;
        }

        return array_any($networks, fn (string $network) => self::contains($network, $value));
    }

    /**
     * Whether one CIDR network contains one address.
     *
     * A malformed network returns false rather than throwing. An allow-list
     * with a typo in it should let nothing through that entry, not everything
     * — and not blow up mid-request on a value the user chose.
     */
    public static function contains(string $network, string $address): bool
    {
        if (! Cidr::passes($network)) {
            return false;
        }

        [$base, $prefix] = explode('/', $network);

        $packedBase = inet_pton($base);
        $packedAddress = inet_pton(self::unwrap($address));

        if ($packedBase === false || $packedAddress === false) {
            return false;
        }

        // Different families never match. Without this, comparing a 4-byte
        // string to a 16-byte one would compare only the leading bytes.
        if (strlen($packedBase) !== strlen($packedAddress)) {
            return false;
        }

        return self::sharesPrefix($packedAddress, $packedBase, (int) $prefix);
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! self::passes($value, $this->networks)) {
            $fail('laranail/validation::validation.in_cidr_range')
                ->translate(['networks' => implode(', ', $this->networks)]);
        }
    }

    /** `::ffff:10.0.0.1` is 10.0.0.1; anything else is returned unchanged. */
    private static function unwrap(string $address): string
    {
        $packed = inet_pton($address);

        if ($packed === false || strlen($packed) !== 16) {
            return $address;
        }

        if (! str_starts_with($packed, str_repeat("\x00", 10)."\xff\xff")) {
            return $address;
        }

        $dotted = inet_ntop(substr($packed, 12));

        return $dotted === false ? $address : $dotted;
    }

    /**
     * Compare the first $prefix bits of two packed addresses. Whole bytes go
     * through strncmp and the remainder through a mask, which is what keeps
     * this correct for a prefix that does not fall on a byte boundary.
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
