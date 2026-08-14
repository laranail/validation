<?php declare(strict_types=1);

namespace Simtabi\Laranail\Validation\Builder\Nodes;

use Illuminate\Contracts\Validation\DataAwareRule;
use Illuminate\Contracts\Validation\ValidatorAwareRule;
use Illuminate\Support\Traits\Conditionable;
use Illuminate\Support\Traits\Macroable;
use Illuminate\Validation\Rules\Email;
use Simtabi\Laranail\Validation\Builder\Concerns\HasEmbeddedRules;
use Simtabi\Laranail\Validation\Builder\Concerns\HasFieldModifiers;
use Simtabi\Laranail\Validation\Builder\Concerns\SelfValidates;
use Simtabi\Laranail\Validation\Contracts\FluentRuleContract;
use Simtabi\Laranail\Validation\Rules\Email\EmailDomainIs;
use Simtabi\Laranail\Validation\Rules\Email\EmailDomainIsNot;
use Simtabi\Laranail\Validation\Rules\Email\NotDisposableEmail;
use Simtabi\Laranail\Validation\Rules\Email\NotRoleEmail;

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
        return $this->rule(new NotDisposableEmail(), $message);
    }

    /**
     * Reject shared-mailbox local parts — info@, sales@, postmaster@.
     */
    public function notRole(?string $message = null): static
    {
        return $this->rule(new NotRoleEmail(), $message);
    }

    /**
     * Restrict the address to the given domains.
     *
     * `*.example.com` matches any subdomain and NOT the bare domain; list
     * both when both are wanted. See EmailDomainIs for why that is strict.
     *
     * @param  list<string>|string  $domains
     */
    public function domainIs(array|string $domains, ?string $message = null): static
    {
        return $this->rule(new EmailDomainIs((array) $domains), $message);
    }

    /**
     * Bar the address from the given domains, same pattern syntax.
     *
     * @param  list<string>|string  $domains
     */
    public function domainIsNot(array|string $domains, ?string $message = null): static
    {
        return $this->rule(new EmailDomainIsNot((array) $domains), $message);
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
