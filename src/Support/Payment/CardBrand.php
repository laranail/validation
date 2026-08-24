<?php declare(strict_types=1);

namespace Simtabi\Laranail\Validation\Support\Payment;

use LogicException;

/**
 * One card brand as data: its IIN prefix ranges, the account-number lengths
 * it issues, its CVC widths, and whether its numbers carry a Luhn check
 * digit (UnionPay's do not, universally — which is why that is a property
 * here rather than an assumption in the rule).
 *
 * Ranges are inclusive numeric intervals over the number's leading digits,
 * both bounds the same width — `['622126', '622925']` — because that is how
 * the industry publishes them, and turning a range into a regex is where
 * the legacy catalogue went wrong twice (it excluded two POINTS from the
 * UnionPay range instead of the interval, and inverted Troy's lookahead so
 * every 9-card EXCEPT a Troy matched).
 */
final readonly class CardBrand
{
    /**
     * @param  string  $name  Machine slug (`visa`), used in `brands:` restrictions.
     * @param  list<array{string, string}>  $ranges  Inclusive prefix intervals, equal-width bounds.
     * @param  list<int>  $lengths
     * @param  list<int>  $cvcLengths
     */
    public function __construct(
        public string $name,
        public string $displayName,
        public array $ranges,
        public array $lengths,
        public array $cvcLengths,
        public bool $luhn = true,
    ) {
        foreach ($ranges as [$low, $high]) {
            if (strlen($low) !== strlen($high)) {
                throw new LogicException(
                    "Range bounds for [{$name}] must be equal width; [{$low}]-[{$high}] are not.",
                );
            }
        }
    }

    /** Whether the (digits-only) number starts inside one of the brand's ranges. */
    public function matches(string $number): bool
    {
        return $this->matchWidth($number) !== null;
    }

    /**
     * The width of the most specific matching range — the tie-breaker when
     * two brands claim a prefix (622126 is Discover by a 6-digit range and
     * UnionPay by a 2-digit one; the 6 wins).
     */
    public function matchWidth(string $number): ?int
    {
        $best = null;

        foreach ($this->ranges as [$low, $high]) {
            $width = strlen($low);
            $prefix = substr($number, 0, $width);

            if (strlen($prefix) === $width && $prefix >= $low && $prefix <= $high) {
                $best = max($best ?? 0, $width);
            }
        }

        return $best;
    }
}
