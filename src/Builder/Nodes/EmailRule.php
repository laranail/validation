<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Validation\Builder\Nodes;

use Illuminate\Validation\Rules\Email;
use Illuminate\Support\Traits\Macroable;
use Illuminate\Support\Traits\Conditionable;
use Illuminate\Contracts\Validation\DataAwareRule;
use Illuminate\Contracts\Validation\ValidatorAwareRule;
use Simtabi\Laranail\Validation\Rules\Email\NotRoleEmail;
use Simtabi\Laranail\Validation\Rules\Email\EmailDomainIs;
use Simtabi\Laranail\Validation\Rules\Email\NoSubaddressing;
use Simtabi\Laranail\Validation\Contracts\FluentRuleContract;
use Simtabi\Laranail\Validation\Rules\Email\EmailDomainIsNot;
use Simtabi\Laranail\Validation\Builder\Concerns\SelfValidates;
use Simtabi\Laranail\Validation\Rules\Email\NotDisposableEmail;
use Simtabi\Laranail\Validation\Rules\Network\DeliverableEmail;
use Simtabi\Laranail\Validation\Builder\Concerns\HasEmbeddedRules;
use Simtabi\Laranail\Validation\Builder\Concerns\HasFieldModifiers;

class EmailRule implements DataAwareRule, FluentRuleContract, ValidatorAwareRule
{
    use Conditionable;
    use HasEmbeddedRules;
    use HasFieldModifiers;
    use Macroable;
    use SelfValidates;

    /** @var list<string> */
    protected array $constraints = ['string'];

    /** @var list<string> */
    protected array $modes = [];

    public function __construct(protected bool $useDefaults = true)
    {
        $this->seedLastConstraint('email');
    }

    public function rfcCompliant(bool $strict = false): static
    {
        $this->modes[] = $strict ? 'strict' : 'rfc';

        return $this;
    }

    public function strict(): static
    {
        return $this->rfcCompliant(true);
    }

    public function validateMxRecord(): static
    {
        $this->modes[] = 'dns';

        return $this;
    }

    public function preventSpoofing(): static
    {
        $this->modes[] = 'spoof';

        return $this;
    }

    public function withNativeValidation(bool $allowUnicode = false): static
    {
        $this->modes[] = $allowUnicode ? 'filter_unicode' : 'filter';

        return $this;
    }

    // -- String-like constraints that make sense on email fields --

    /**
     * Reject addresses at a throwaway-mailbox provider.
     *
     * The list comes from the container, so an application that installs
     * laranail/email gets its maintained list here without changing this call.
     */
    public function notDisposable(?string $message = null): static
    {
        return $this->rule(new NotDisposableEmail, $message);
    }

    /**
     * Reject shared-mailbox local parts — info@, sales@, postmaster@.
     */
    public function notRole(?string $message = null): static
    {
        return $this->rule(new NotRoleEmail, $message);
    }

    /**
     * Restrict the address to the given domains.
     *
     * `*.example.com` matches any subdomain and NOT the bare domain; list
     * both when both are wanted. See EmailDomainIs for why that is strict.
     *
     * @param list<string>|string $domains
     */
    public function domainIs(array|string $domains, ?string $message = null): static
    {
        return $this->rule(new EmailDomainIs((array) $domains), $message);
    }

    /**
     * Bar the address from the given domains, same pattern syntax.
     *
     * @param list<string>|string $domains
     */
    public function domainIsNot(array|string $domains, ?string $message = null): static
    {
        return $this->rule(new EmailDomainIsNot((array) $domains), $message);
    }

    /**
     * The domain can actually receive mail — one DNS lookup for its MX record.
     *
     * **Network tier**, and different from `validateMxRecord()` above in the
     * way that matters operationally: that one compiles to Laravel's
     * `email:dns`, which calls egulias' DNSCheckValidation directly and is
     * therefore neither cached, injectable nor fakeable. This goes through
     * {@see DnsResolver}, so the same handful of domains that dominate any
     * signup form are looked up once, and a test does not have to reach the
     * network to run.
     *
     * It also skips itself during a precognitive request. Without that, a
     * debounced email field issues a DNS lookup per keystroke.
     *
     * A lookup that fails for any reason PASSES — a DNS outage rejecting every
     * signup is the worse error. It is a quality filter, not a boundary.
     */
    public function deliverable(?string $message = null): static
    {
        return $this->rule(new DeliverableEmail, $message);
    }

    /**
     * Reject `user+tag@example.com`.
     *
     * Subaddressing is a legitimate feature and this is off by default. It is
     * worth turning on for exactly one thing: a signup form where one mailbox
     * can mint unlimited distinct addresses, which is how a single person
     * takes a free trial repeatedly. Turning it on elsewhere annoys the people
     * who use it to filter their own mail.
     *
     * `+` is the separator every major provider uses; a provider using
     * something else is not covered, and pretending otherwise would be worse
     * than the stated limit.
     */
    public function withoutSubaddressing(?string $message = null): static
    {
        return $this->rule(new NoSubaddressing, $message);
    }

    /**
     * The RFC 5321 ceiling — 254 characters for the whole address.
     *
     * Not a default, because most columns are narrower and the rule that
     * matters is the column's. Worth stating when the column is not: a longer
     * address is not deliverable, so accepting it stores something that can
     * never be written to.
     */
    public function maxRfcLength(?string $message = null): static
    {
        return $this->max(254, $message);
    }

    public function max(int $value, ?string $message = null): static
    {
        return $this->addRule('max:' . $value, $message);
    }

    public function confirmed(?string $message = null): static
    {
        return $this->addRule('confirmed', $message);
    }

    public function same(string $field, ?string $message = null): static
    {
        return $this->addRule('same:' . $field, $message);
    }

    public function different(string $field, ?string $message = null): static
    {
        return $this->addRule('different:' . $field, $message);
    }

    /**
     * Note: compiledRules() is deliberately NOT overridden here. SelfValidates
     * already calls this buildValidationRules(), and its version applies two
     * guards this class must not lose — it skips pipe-joining when any token
     * contains a literal `|` (otherwise Laravel's parser splits a regex mid
     * pattern), and it only stringifies In/NotIn, because Exists/Unique
     * silently drop closure-based wheres in __toString().
     *
     * @return list<string|object>
     */
    protected function buildValidationRules(): array
    {
        // Explicit modes always take precedence.
        if ($this->modes !== []) {
            return [...$this->reorderConstraints($this->constraints), 'email:' . implode(',', $this->modes), ...$this->rules];
        }

        // Use Email::default() when defaults are enabled and the app has configured them.
        if ($this->useDefaults && Email::$defaultCallback !== null) {
            return [...$this->reorderConstraints($this->constraints), Email::default(), ...$this->rules];
        }

        return [...$this->reorderConstraints($this->constraints), 'email', ...$this->rules];
    }
}
