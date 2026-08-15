<?php declare(strict_types=1);

namespace Simtabi\Laranail\Validation\Builder\Nodes;

use Closure;
use Illuminate\Contracts\Validation\DataAwareRule;
use Illuminate\Contracts\Validation\ValidatorAwareRule;
use Illuminate\Support\Traits\Conditionable;
use Illuminate\Support\Traits\Macroable;
use Simtabi\Laranail\Phone\Enums\PhoneNumberType;
use Simtabi\Laranail\Validation\Builder\Concerns\HasEmbeddedRules;
use Simtabi\Laranail\Validation\Builder\Concerns\HasFieldModifiers;
use Simtabi\Laranail\Validation\Builder\Concerns\SelfValidates;
use Simtabi\Laranail\Validation\Contracts\FluentRuleContract;
use Simtabi\Laranail\Validation\Rules\Telecom\Phone;
use Simtabi\Laranail\Validation\Rules\Telecom\UniquePhone;

/**
 * A phone-number field.
 *
 * ```php
 * 'phone' => FluentRule::phone()->required()->country('KE')->mobile(),
 * 'phone' => FluentRule::phone()->required()->countryFrom('phone_country'),
 * 'phone' => FluentRule::phone()->nullable()->international(),
 * ```
 *
 * Accepts any country by default, because a rule that silently assumes one is how an international
 * signup form starts rejecting half its users. Narrow it deliberately with {@see country()} or point
 * at a country picker with {@see countryFrom()}.
 *
 * The check itself lives in {@see Phone}; this class is the fluent surface over it. It requires
 * `laranail/phone`, a suggested rather than required dependency — see that class for why.
 */
class PhoneRule implements DataAwareRule, FluentRuleContract, ValidatorAwareRule
{
    use Conditionable;
    use HasEmbeddedRules;
    use HasFieldModifiers;
    use Macroable;
    use SelfValidates;

    /** @var list<string> */
    protected array $constraints = ['string'];

    /** @var list<string> */
    protected array $countries = [];

    protected ?string $countryField = null;

    /** @var list<PhoneNumberType> */
    protected array $types = [];

    protected bool $possibleOnly = false;

    protected bool $allowExtension = true;

    protected bool $rejectShortNumbers = false;

    protected bool $rejectEmergency = false;

    public function __construct()
    {
        $this->seedLastConstraint('phone');
    }

    /**
     * Accept numbers from these countries only.
     *
     * With exactly one country, it doubles as the parse hint for bare national input. With several it
     * does not — picking one arbitrarily would make the outcome depend on array order — so pair a
     * multi-country list with {@see countryFrom()} or expect international input.
     *
     * @param string|list<string> $countries ISO 3166-1 alpha-2
     */
    public function country(string|array $countries): static
    {
        $this->countries = array_values(array_map(
            strtoupper(...),
            is_string($countries) ? [$countries] : $countries,
        ));

        return $this;
    }

    /** Accept a number from any country. The default; state it when the intent should be explicit. */
    public function international(): static
    {
        $this->countries = [];

        return $this;
    }

    /**
     * Read the country from a sibling field, such as the picker beside the input.
     *
     * The field holds an ISO 3166-1 alpha-2 code. Dot notation works, so a repeater row can point at
     * its own sibling.
     */
    public function countryFrom(string $field): static
    {
        $this->countryField = $field;

        return $this;
    }

    /**
     * Accept only these line types.
     *
     * `FixedLineOrMobile` satisfies a request for either — see {@see Phone::typeMatches()}.
     *
     * @param PhoneNumberType|list<PhoneNumberType> $types
     */
    public function type(PhoneNumberType|array $types): static
    {
        $this->types = $types instanceof PhoneNumberType ? [$types] : array_values($types);

        return $this;
    }

    /** Only numbers that can receive a call or a message on a handset. */
    public function mobile(): static
    {
        return $this->type(PhoneNumberType::Mobile);
    }

    public function fixedLine(): static
    {
        return $this->type(PhoneNumberType::FixedLine);
    }

    public function tollFree(): static
    {
        return $this->type(PhoneNumberType::TollFree);
    }

    public function voip(): static
    {
        return $this->type(PhoneNumberType::Voip);
    }

    /**
     * Accept anything correctly *shaped* for its country, without requiring the range to be allocated.
     *
     * The looser of the two checks, and the right one where turning away a real customer costs more
     * than accepting an unreachable number — signup, lead capture. Newly allocated ranges are possible
     * for months before Google's metadata catches up.
     */
    public function possible(): static
    {
        $this->possibleOnly = true;

        return $this;
    }

    /** Require an allocated range. The default; state it when the intent should be explicit. */
    public function strict(): static
    {
        $this->possibleOnly = false;

        return $this;
    }

    /** Reject `+1 555 123 4567 x890`. Extensions are accepted by default. */
    public function withoutExtension(): static
    {
        $this->allowExtension = false;

        return $this;
    }

    /** Reject short codes such as `611`. Relevant where the number must be dialable from abroad. */
    public function rejectShortNumbers(): static
    {
        $this->rejectShortNumbers = true;

        return $this;
    }

    /** Reject emergency numbers. Worth setting on anything that will be dialled automatically. */
    public function rejectEmergency(): static
    {
        $this->rejectEmergency = true;

        return $this;
    }

    /**
     * @return list<string|object>
     */
    protected function buildValidationRules(): array
    {
        return [
            ...$this->reorderConstraints($this->constraints),
            new Phone(
                countries: $this->countries,
                countryField: $this->countryField,
                types: $this->types,
                possibleOnly: $this->possibleOnly,
                allowExtension: $this->allowExtension,
                rejectShortNumbers: $this->rejectShortNumbers,
                rejectEmergency: $this->rejectEmergency,
            ),
            ...$this->rules,
        ];
    }

    /**
     * Uniqueness compared in E.164 rather than as typed.
     *
     * Overrides the generic `unique()` deliberately. Laravel's compares the attribute exactly as it
     * arrived, which does not work here: with a row holding `+254712123456`, a user typing
     * `0712 123456` passes, and you get a duplicate contact nobody can explain from looking at the
     * table. {@see UniquePhone} normalises first.
     *
     * The country hint follows whatever this chain was already told — `country()` or
     * `countryFrom()` — so it does not have to be repeated.
     *
     * ```php
     * FluentRule::phone()->country('KE')->unique('contacts', 'phone');
     * FluentRule::phone()->unique('contacts', 'phone', fn ($rule) => $rule->ignore($id));
     * ```
     *
     * @param (Closure(UniquePhone): void)|null $callback Receives the rule, for `ignore()`
     */
    public function unique(string $table, ?string $column = null, ?Closure $callback = null, ?string $message = null): static
    {
        $rule = new UniquePhone(
            table: $table,
            column: $column ?? 'phone',
            country: count($this->countries) === 1 ? $this->countries[0] : null,
            countryField: $this->countryField,
        );

        if ($callback instanceof Closure) {
            $callback($rule);
        }

        return $this->addRule($rule, $message);
    }
}
