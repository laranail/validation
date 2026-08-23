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
use Simtabi\Laranail\Validation\Rules\Text\Username;

/**
 * A username field.
 *
 * ```php
 * 'handle' => FluentRule::username()->required()->unique('users', 'handle'),
 * 'handle' => FluentRule::username(3, 15)->required()->lowercase()->separators('_'),
 * 'handle' => FluentRule::username()->required()->reserved([...Username::DEFAULT_RESERVED, 'acme']),
 * ```
 *
 * There was no factory for this at all — the rule existed and the only way to
 * reach it was `FluentRule::string()->rule(new Username(3, 32))`, which is the
 * shape the package exists to replace.
 *
 * A reserved-name list is on by default. Every entry breaks something concrete
 * rather than merely being unwanted — impersonation, or a collision with a
 * route — and `->reserved([])` turns it off. See {@see Username}.
 */
class UsernameRule implements DataAwareRule, FluentRuleContract, ValidatorAwareRule
{
    use Conditionable;
    use HasEmbeddedRules;
    use HasFieldModifiers;
    use Macroable;
    use SelfValidates;

    /** @var list<string> */
    protected array $constraints = ['string'];

    protected string $separators = '._-';

    protected bool $lowercaseOnly = false;

    /** @var list<string>|null */
    protected ?array $reserved = null;

    public function __construct(
        protected int $min = 3,
        protected int $max = 32,
    ) {
        $this->seedLastConstraint('username');
    }

    /** The length bounds, counting separators. */
    public function length(int $min, int $max): static
    {
        $this->min = $min;
        $this->max = $max;

        return $this;
    }

    public function min(int $value): static
    {
        $this->min = $value;

        return $this;
    }

    public function max(int $value): static
    {
        $this->max = $value;

        return $this;
    }

    /**
     * Which characters may sit between others. Defaults to `._-`.
     *
     * Pass `''` for a flat alphanumeric handle. The set is escaped before it
     * reaches a character class, so a `-` in it is a literal hyphen rather
     * than a range — which is what `.-_` would otherwise compile to, silently
     * admitting every codepoint between `.` and `_`.
     */
    public function separators(string $characters): static
    {
        $this->separators = $characters;

        return $this;
    }

    /**
     * Reject uppercase rather than accepting it.
     *
     * Worth stating rather than folding on save: if `Alice` and `alice` are
     * the same account, the rule that says so should be visible at the edge,
     * not implied by a `strtolower()` three layers in.
     *
     * It also settles uniqueness. `unique()` compares the attribute exactly as
     * it arrived, so without this a row holding `alice` does not stop someone
     * registering `Alice` — two accounts differing only in case, which is the
     * confusion a handle exists to prevent.
     */
    public function lowercase(): static
    {
        $this->lowercaseOnly = true;

        return $this;
    }

    /**
     * Replace the reserved-name list. `[]` turns the check off.
     *
     * ```php
     * ->reserved([...Username::DEFAULT_RESERVED, 'acme', 'enterprise'])
     * ```
     *
     * @param  list<string>  $names
     */
    public function reserved(array $names): static
    {
        $this->reserved = array_values($names);

        return $this;
    }

    /**
     * Add to the shipped list rather than replacing it.
     *
     * @param  list<string>|string  $names
     */
    public function alsoReserved(array|string $names): static
    {
        $this->reserved = [
            ...($this->reserved ?? Username::DEFAULT_RESERVED),
            ...array_values((array) $names),
        ];

        return $this;
    }

    /**
     * @return list<string|object>
     */
    protected function buildValidationRules(): array
    {
        return [
            ...$this->reorderConstraints($this->constraints),
            new Username(
                min: $this->min,
                max: $this->max,
                separators: $this->separators,
                lowercase: $this->lowercaseOnly,
                reserved: $this->reserved,
            ),
            ...$this->rules,
        ];
    }
}
