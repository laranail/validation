<?php declare(strict_types=1);

namespace Simtabi\Laranail\Validation\Builder\Nodes;

use Illuminate\Contracts\Validation\DataAwareRule;
use Illuminate\Contracts\Validation\ValidatorAwareRule;
use Illuminate\Support\Traits\Conditionable;
use Illuminate\Support\Traits\Macroable;
use Simtabi\Laranail\Validation\Builder\Concerns\HasEmbeddedRules;
use Simtabi\Laranail\Validation\Builder\Concerns\HasFieldModifiers;
use Simtabi\Laranail\Validation\Builder\Concerns\SelfValidates;
use Simtabi\Laranail\Validation\Contracts\FluentRuleContract;
use Simtabi\Laranail\Validation\Rules\Net\MacAddress;

/**
 * A MAC address field.
 *
 * ```php
 * 'mac'    => FluentRule::macAddress()->required(),
 * 'device' => FluentRule::macAddress()->required()->colon()->unicast()->universal(),
 * 'switch' => FluentRule::macAddress()->required()->eui48()->oui(['00:1B:44']),
 * ```
 *
 * Laravel's `mac_address` answers whether the value is shaped like one. The
 * three questions it cannot answer all bite in practice: which notation (a
 * column accepting three spellings of one address cannot be looked up by
 * equality), whether it names a device at all (broadcast and null pass a
 * format check), and whether it is a manufacturer's or a phone's randomised
 * one. See {@see MacAddress}.
 */
class MacAddressRule implements DataAwareRule, FluentRuleContract, ValidatorAwareRule
{
    use Conditionable;
    use HasEmbeddedRules;
    use HasFieldModifiers;
    use Macroable;
    use SelfValidates;

    /** @var list<string> */
    protected array $constraints = ['string'];

    /** @var list<string> */
    protected array $formats = [];

    protected ?int $bytes = null;

    protected bool $requireUnicast = false;

    protected bool $requireUniversal = false;

    /** @var list<string> */
    protected array $ouis = [];

    public function __construct()
    {
        $this->seedLastConstraint('mac_address');
    }

    /** `00:1B:44:11:3A:B7` — the IEEE's own notation. */
    public function colon(): static
    {
        return $this->format(MacAddress::COLON);
    }

    /** `00-1B-44-11-3A-B7` — what Windows prints. */
    public function hyphen(): static
    {
        return $this->format(MacAddress::HYPHEN);
    }

    /** `001b.4411.3ab7` — Cisco's dotted triples. */
    public function dotted(): static
    {
        return $this->format(MacAddress::DOTTED);
    }

    /** `001B44113AB7` — what most vendor exports contain. */
    public function bare(): static
    {
        return $this->format(MacAddress::BARE);
    }

    /**
     * Accept only these notations.
     *
     * @param  list<string>|string  $formats  {@see MacAddress::COLON} and friends.
     */
    public function format(array|string $formats): static
    {
        $this->formats = array_values((array) $formats);

        return $this;
    }

    /** Six bytes — an ordinary Ethernet address. */
    public function eui48(): static
    {
        $this->bytes = 6;

        return $this;
    }

    /** Eight bytes — 802.15.4, FireWire, and the derived IPv6 interface identifier. */
    public function eui64(): static
    {
        $this->bytes = 8;

        return $this;
    }

    /** Reject multicast addresses — the I/G bit, bit 0 of the first octet. */
    public function unicast(): static
    {
        $this->requireUnicast = true;

        return $this;
    }

    /**
     * Reject locally-administered addresses — the U/L bit, bit 1 of the first
     * octet.
     *
     * The one that matters most in practice. Every modern phone presents a
     * randomised, locally-administered address to networks it has not joined;
     * it is a perfectly valid MAC and a useless identity, because it changes.
     * A device register that stores one is storing something that will not
     * match tomorrow.
     */
    public function universal(): static
    {
        $this->requireUniversal = true;

        return $this;
    }

    /**
     * Accept only addresses beginning with one of these prefixes.
     *
     * Written in any notation and any length — a 24-bit OUI, or a longer
     * MA-M/MA-S assignment.
     *
     * @param  list<string>|string  $ouis
     */
    public function oui(array|string $ouis): static
    {
        $this->ouis = array_values((array) $ouis);

        return $this;
    }

    /**
     * @return list<string|object>
     */
    protected function buildValidationRules(): array
    {
        return [
            ...$this->reorderConstraints($this->constraints),
            new MacAddress(
                formats: $this->formats,
                bytes: $this->bytes,
                requireUnicast: $this->requireUnicast,
                requireUniversal: $this->requireUniversal,
                ouis: $this->ouis,
            ),
            ...$this->rules,
        ];
    }
}
