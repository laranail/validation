<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Validation\Rules\Net;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * A MAC address, with the parts a bare format check cannot see.
 *
 * Laravel's `mac_address` is `filter_var(..., FILTER_VALIDATE_MAC)`, which
 * answers "is this shaped like a MAC" and nothing else. Three things it does
 * not answer come up constantly:
 *
 * - **Which notation.** `00:1B:44:11:3A:B7`, `00-1B-44-11-3A-B7` and
 *   `001b.4411.3ab7` are the same address written three ways. A column that
 *   accepts all three cannot be looked up by equality, and the duplicate is
 *   invisible in the table.
 * - **Whether it identifies a device.** `FF:FF:FF:FF:FF:FF` is the broadcast
 *   address and `00:00:00:00:00:00` is the null address. Both pass a format
 *   check and neither is a device you can register.
 * - **Whether it is real or randomised.** Every modern phone presents a
 *   locally-administered address to networks it has not joined. It is a
 *   perfectly good MAC and a useless identity, because it changes.
 *
 * ## The two bits in the first octet
 *
 * Bit 0 is the I/G bit: set means multicast, clear means unicast. Bit 1 is the
 * U/L bit: set means locally administered, clear means the address came out of
 * an IEEE-assigned OUI. `{@see requireUniversal()}` on the builder is the one
 * that matters in practice — it is what tells a randomised phone address from
 * a manufacturer's.
 *
 * Pure tier — no IO. Nothing here looks an OUI up in a registry.
 */
final readonly class MacAddress implements ValidationRule
{
    /** Colon-separated pairs — `00:1B:44:11:3A:B7`. The IEEE's own notation. */
    public const string COLON = 'colon';

    /** Hyphen-separated pairs — `00-1B-44-11-3A-B7`. Windows and IEEE 802 print this. */
    public const string HYPHEN = 'hyphen';

    /** Cisco's dotted triples — `001b.4411.3ab7`. */
    public const string DOTTED = 'dotted';

    /** No separators at all — `001B44113AB7`. */
    public const string BARE = 'bare';

    /**
     * @param list<string> $formats Accepted notations; empty accepts all four.
     * @param int|null $bytes 6 for EUI-48, 8 for EUI-64; null accepts either.
     * @param bool $requireUnicast Reject multicast addresses (I/G bit set).
     * @param bool $requireUniversal Reject locally-administered addresses (U/L bit set).
     * @param list<string> $ouis Accepted OUI prefixes, in any notation; empty accepts any.
     */
    public function __construct(
        private array $formats = [],
        private ?int $bytes = null,
        private bool $requireUnicast = false,
        private bool $requireUniversal = false,
        private array $ouis = [],
    ) {}

    public static function passes(mixed $value): bool
    {
        return is_string($value) && self::formatOf($value) !== null;
    }

    /**
     * Which notation the value is written in, or null if it is none of them.
     *
     * Deliberately not `FILTER_VALIDATE_MAC`: that accepts colon, hyphen and
     * dotted forms but reports only pass or fail, and the notation is the
     * thing a column has to be consistent about. It also rejects the bare
     * form, which is what most vendor exports contain.
     */
    public static function formatOf(string $value): ?string
    {
        return match (true) {
            preg_match('/^[0-9a-f]{2}(?::[0-9a-f]{2}){5}$/iD', $value) === 1,
            preg_match('/^[0-9a-f]{2}(?::[0-9a-f]{2}){7}$/iD', $value) === 1 => self::COLON,

            preg_match('/^[0-9a-f]{2}(?:-[0-9a-f]{2}){5}$/iD', $value) === 1,
            preg_match('/^[0-9a-f]{2}(?:-[0-9a-f]{2}){7}$/iD', $value) === 1 => self::HYPHEN,

            preg_match('/^[0-9a-f]{4}(?:\.[0-9a-f]{4}){2}$/iD', $value) === 1,
            preg_match('/^[0-9a-f]{4}(?:\.[0-9a-f]{4}){3}$/iD', $value) === 1 => self::DOTTED,

            preg_match('/^(?:[0-9a-f]{12}|[0-9a-f]{16})$/iD', $value) === 1 => self::BARE,

            default => null,
        };
    }

    /**
     * The address as octet values, normalised out of whatever notation it
     * arrived in. Empty when the value is not a MAC address.
     *
     * @return list<int>
     */
    public static function octets(string $value): array
    {
        if (self::formatOf($value) === null) {
            return [];
        }

        $hex = (string) preg_replace('/[^0-9a-f]/i', '', $value);

        return array_map(
            static fn (string $pair): int => (int) hexdec($pair),
            str_split($hex, 2),
        );
    }

    /**
     * The canonical colon form, uppercase — for storing one notation per column.
     *
     * Returns null rather than a half-converted string when the input is not a
     * MAC address, so a caller cannot write garbage without noticing.
     */
    public static function normalise(string $value): ?string
    {
        $octets = self::octets($value);

        if ($octets === []) {
            return null;
        }

        return implode(':', array_map(
            static fn (int $octet): string => strtoupper(str_pad(dechex($octet), 2, '0', STR_PAD_LEFT)),
            $octets,
        ));
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value)) {
            $fail('laranail/validation::validation.mac_address.malformed')->translate();

            return;
        }

        $format = self::formatOf($value);

        if ($format === null) {
            $fail('laranail/validation::validation.mac_address.malformed')->translate();

            return;
        }

        if ($this->formats !== [] && ! in_array($format, $this->formats, true)) {
            $fail('laranail/validation::validation.mac_address.format')
                ->translate(['formats' => implode(', ', $this->formats)]);

            return;
        }

        $octets = self::octets($value);

        if ($this->bytes !== null && count($octets) !== $this->bytes) {
            $fail('laranail/validation::validation.mac_address.length')
                ->translate(['bytes' => $this->bytes]);

            return;
        }

        // Before the bit checks, because both of these have the bits of a
        // perfectly ordinary address and neither names a device — reporting
        // "must be unicast" for the broadcast address would be true and
        // useless.
        if ($this->isBroadcast($octets)) {
            $fail('laranail/validation::validation.mac_address.broadcast')->translate();

            return;
        }

        if ($this->isNull($octets)) {
            $fail('laranail/validation::validation.mac_address.null')->translate();

            return;
        }

        if ($this->requireUnicast && (($octets[0] & 0b1) !== 0)) {
            $fail('laranail/validation::validation.mac_address.multicast')->translate();

            return;
        }

        if ($this->requireUniversal && (($octets[0] & 0b10) !== 0)) {
            $fail('laranail/validation::validation.mac_address.local')->translate();

            return;
        }

        if ($this->ouis !== [] && ! $this->matchesAnyOui($octets, $this->ouis)) {
            $fail('laranail/validation::validation.mac_address.oui')
                ->translate(['ouis' => implode(', ', $this->ouis)]);
        }
    }

    /** @param  list<int>  $octets */
    private function isBroadcast(array $octets): bool
    {
        return $octets !== [] && ! in_array(false, array_map(
            static fn (int $octet): bool => $octet === 0xFF,
            $octets,
        ), true);
    }

    /** @param  list<int>  $octets */
    private function isNull(array $octets): bool
    {
        return $octets !== [] && ! in_array(false, array_map(
            static fn (int $octet): bool => $octet === 0x00,
            $octets,
        ), true);
    }

    /**
     * @param list<int> $octets
     * @param list<string> $ouis
     */
    private function matchesAnyOui(array $octets, array $ouis): bool
    {
        foreach ($ouis as $oui) {
            // The OUI may be written in any notation and may be a prefix of
            // any length — a 24-bit OUI, or a longer MA-M/MA-S assignment.
            $hex = strtolower((string) preg_replace('/[^0-9a-f]/i', '', $oui));

            if ($hex === '' || strlen($hex) % 2 !== 0) {
                continue;
            }

            $prefix = array_map(
                static fn (string $pair): int => (int) hexdec($pair),
                str_split($hex, 2),
            );

            if (array_slice($octets, 0, count($prefix)) === $prefix) {
                return true;
            }
        }

        return false;
    }
}
