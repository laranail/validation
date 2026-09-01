<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Validation\Builder\Nodes;

use Illuminate\Contracts\Validation\DataAwareRule;
use Illuminate\Contracts\Validation\ValidatorAwareRule;
use Illuminate\Support\Traits\Conditionable;
use Illuminate\Support\Traits\Macroable;
use Simtabi\Laranail\Validation\Builder\Concerns\HasEmbeddedRules;
use Simtabi\Laranail\Validation\Builder\Concerns\HasFieldModifiers;
use Simtabi\Laranail\Validation\Builder\Concerns\SelfValidates;
use Simtabi\Laranail\Validation\Contracts\FluentRuleContract;
use Simtabi\Laranail\Validation\Rules\Net\InCidrRange;
use Simtabi\Laranail\Validation\Rules\Net\PrivateIp;
use Simtabi\Laranail\Validation\Rules\Net\PublicIp;

/**
 * An IP address field.
 *
 * ```php
 * 'client_ip'  => FluentRule::ip()->required(),
 * 'origin'     => FluentRule::ip()->required()->v4()->public(),
 * 'office'     => FluentRule::ip()->required()->inRange(['203.0.113.0/24']),
 * 'internal'   => FluentRule::ip()->required()->private(),
 * ```
 *
 * The package already had `PublicIp`, `PrivateIp` and a careful classifier
 * behind them — including the IPv4-mapped-v6 unwrapping that most SSRF filters
 * miss — and none of it was reachable from the fluent surface. `FluentRule::ip()`
 * returned a {@see StringRule} carrying Laravel's bare `ip`, so the only way
 * to ask whether an address was routable was to know the rule class by name.
 */
class IpAddressRule implements DataAwareRule, FluentRuleContract, ValidatorAwareRule
{
    use Conditionable;
    use HasEmbeddedRules;
    use HasFieldModifiers;
    use Macroable;
    use SelfValidates;

    /** @var list<string> */
    protected array $constraints = ['string'];

    /** `ip`, `ipv4` or `ipv6` — the family gate, always first. */
    protected string $family = 'ip';

    public function __construct()
    {
        $this->seedLastConstraint('ip');
    }

    /** IPv4 only. */
    public function v4(?string $message = null): static
    {
        return $this->family('ipv4', $message);
    }

    /** IPv6 only. */
    public function v6(?string $message = null): static
    {
        return $this->family('ipv6', $message);
    }

    /** Either family. The default; state it when the intent should be explicit. */
    public function anyFamily(?string $message = null): static
    {
        return $this->family('ip', $message);
    }

    /**
     * Publicly routable only.
     *
     * Stricter than `filter_var`'s `FILTER_FLAG_NO_PRIV_RANGE|NO_RES_RANGE`,
     * which is the usual shortcut and has two holes this does not: it reads
     * `::ffff:127.0.0.1` as an ordinary global v6 address, and it lets the
     * carrier-grade NAT block `100.64.0.0/10` through.
     */
    public function public(?string $message = null): static
    {
        return $this->rule(new PublicIp, $message);
    }

    /**
     * Private, loopback, link-local, reserved or multicast — the exact
     * complement of {@see public()} over valid addresses.
     *
     * For a field that is *meant* to name something internal, such as a
     * configured service address that must not be on the public internet.
     */
    public function private(?string $message = null): static
    {
        return $this->rule(new PrivateIp, $message);
    }

    /**
     * Inside one of these CIDR networks.
     *
     * ```php
     * FluentRule::ip()->inRange('10.0.0.0/8')
     * FluentRule::ip()->inRange(['203.0.113.0/24', '2001:db8::/32'])
     * ```
     *
     * @param  list<string>|string  $networks
     */
    public function inRange(array|string $networks, ?string $message = null): static
    {
        return $this->rule(new InCidrRange(array_values((array) $networks)), $message);
    }

    /**
     * @return list<string|object>
     */
    protected function buildValidationRules(): array
    {
        return [
            ...$this->reorderConstraints($this->constraints),
            $this->family,
            ...$this->rules,
        ];
    }

    private function family(string $rule, ?string $message): static
    {
        $this->family = $rule;

        // The message binds to the family key that is actually emitted, so a
        // chain that switches from v4 to v6 does not leave a message pinned to
        // a rule the field no longer carries.
        if ($message !== null) {
            $this->messageFor($rule, $message);
        }

        return $this;
    }
}
