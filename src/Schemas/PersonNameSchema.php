<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Validation\Schemas;

use LogicException;
use InvalidArgumentException;
use Illuminate\Support\Traits\Macroable;
use Simtabi\Laranail\Validation\RuleSet;
use Simtabi\Laranail\Validation\FluentRule;
use Simtabi\Laranail\Validation\Rules\Text\PersonName;

/**
 * The rules for a person's name, across however many fields the system has.
 *
 * A person's name is not one value and it is not three. Some people have one
 * name, some have four given names, some have two family names, and the split
 * a schema chose is a guess about a naming culture rather than a fact about a
 * person. So the field set is declared by the caller and nothing here assumes
 * `first`/`middle`/`last`:
 *
 *     PersonNameSchema::make()                                   // first, middle, last
 *     PersonNameSchema::make('given_name', 'family_name')
 *     PersonNameSchema::make('first_name', 'middle_name', 'last_name', 'suffix')
 *     PersonNameSchema::list('names')                            // an unbounded list
 *
 * The result is a {@see RuleSet}, so it composes with everything else the
 * package does — `merge()`, `only()`, `validate()`, the optimized form-request
 * path — rather than being a private arrangement of arrays.
 *
 *     public function rules(): RuleSet
 *     {
 *         return PersonNameSchema::make()->toRuleSet()->merge([
 *             'email' => FluentRule::email()->required(),
 *         ]);
 *     }
 *
 * ## Why the requirement hangs off one field
 *
 * `AtLeastOne` attaches `required_without_all` to the FIRST declared field and
 * nowhere else. Attaching it to all of them reports one mistake once per field,
 * and a form showing three copies of "provide at least one name" reads as three
 * problems.
 *
 * ## Why every field is nullable, including that one
 *
 * `nullable` on the field carrying the requirement looks like it would cancel
 * it. It does not, and the asymmetry is the whole reason this class exists.
 * `required_without_all` is IMPLICIT, so it runs on an absent or null value;
 * `string` and `max` are not, so without `nullable` they run on a legitimately
 * null first name and reject it — telling someone with only a family name that
 * "the first name field must be a string", which names neither the rule nor
 * the fix. Both halves of that have been live bugs.
 */
final class PersonNameSchema
{
    use Macroable;

    /** @var array<string, string> */
    private array $labels = [];

    private PersonNamePresence $presence = PersonNamePresence::AtLeastOne;

    private ?string $presenceMessage = null;

    private int $maxCharacters = 255;

    private int $minNames = 1;

    private ?int $maxNames = null;

    private bool $allowDigits = false;

    private bool $checkCharacters = true;

    /** Set only by {@see list()} — the field holding an unbounded list of names. */
    private ?string $listField = null;

    private ?int $maxListNames = null;

    /** @param  list<string>  $fields */
    private function __construct(private array $fields) {}

    /**
     * A name split across named fields, defaulting to the common three.
     *
     * @throws InvalidArgumentException When no field is named, or one is named twice.
     */
    public static function make(string ...$fields): self
    {
        $fields = $fields === [] ? ['first_name', 'middle_name', 'last_name'] : $fields;

        if (count($fields) !== count(array_unique($fields))) {
            throw new InvalidArgumentException(
                'A name field may only be declared once; got ' . implode(', ', $fields) . '.',
            );
        }

        return new self(array_values($fields));
    }

    /**
     * A name held as an unbounded list — `names.0`, `names.1`, … — for systems
     * that store name parts as rows rather than as columns.
     *
     * The honest shape when a schema genuinely does not know how many parts a
     * name has. `$max` is a cap on the submission rather than on the person:
     * without one, a single request can carry an arbitrary number of entries.
     */
    public static function list(string $field = 'names', ?int $max = 10): self
    {
        $schema = new self([$field]);
        $schema->listField = $field;
        $schema->maxListNames = $max;

        return $schema;
    }

    /**
     * At least one of the declared fields must carry a name — the default.
     *
     * The message defaults to the package's own, naming the fields, because the
     * stock one ("required when none of middle name / last name are present")
     * describes the constraint rather than the fix.
     */
    public function requireAtLeastOne(?string $message = null): self
    {
        return $this->with(function (self $schema) use ($message): void {
            $schema->presence = PersonNamePresence::AtLeastOne;
            $schema->presenceMessage = $message;
        });
    }

    /** Every declared field is required. */
    public function requireAll(): self
    {
        return $this->with(static function (self $schema): void {
            $schema->presence = PersonNamePresence::All;
        });
    }

    /** No presence requirement — for a partial update where absence means "unchanged". */
    public function optional(): self
    {
        return $this->with(static function (self $schema): void {
            $schema->presence = PersonNamePresence::Optional;
        });
    }

    /** The per-field character limit. Defaults to 255, matching a stock `string` column. */
    public function max(int $characters): self
    {
        return $this->with(static function (self $schema) use ($characters): void {
            $schema->maxCharacters = $characters;
        });
    }

    /**
     * How many whitespace-separated names ONE field may carry.
     *
     * Unbounded by default, which is the point: a person with three given names
     * types them into the one box a form offers them.
     */
    public function names(int $min = 1, ?int $max = null): self
    {
        return $this->with(static function (self $schema) use ($min, $max): void {
            $schema->minNames = $min;
            $schema->maxNames = $max;
        });
    }

    /** Exactly one name per field, for a schema that means its columns strictly. */
    public function singleNamePerField(): self
    {
        return $this->names(1, 1);
    }

    /** Accept digits — a suffix, or a legal entity carried in a name column. */
    public function allowDigits(bool $allow = true): self
    {
        return $this->with(static function (self $schema) use ($allow): void {
            $schema->allowDigits = $allow;
        });
    }

    /**
     * Drop the character check, keeping only presence and length.
     *
     * For an existing table whose rows would not pass it. Validating new input
     * against a rule the stored data violates turns every edit of an old record
     * into an error the user cannot resolve.
     */
    public function withoutCharacterCheck(): self
    {
        return $this->with(static function (self $schema): void {
            $schema->checkCharacters = false;
        });
    }

    /**
     * Human labels, used as `:attribute` and in the at-least-one message.
     *
     * Defaults are derived from the field name (`first_name` → "first name"),
     * which is what Laravel would have shown anyway.
     *
     * @param array<string, string> $labels
     */
    public function labels(array $labels): self
    {
        return $this->with(static function (self $schema) use ($labels): void {
            $schema->labels = [...$schema->labels, ...$labels];
        });
    }

    /** @return list<string> */
    public function fields(): array
    {
        return $this->fields;
    }

    public function toRuleSet(): RuleSet
    {
        return RuleSet::from($this->rules());
    }

    /**
     * The rule map, as builder objects.
     *
     * Labels and messages ride on the builders rather than being returned
     * alongside them, so there is no second array to keep in step — see
     * `docs/tools/error-messages.md`.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return $this->listField !== null ? $this->listRules() : $this->fieldRules();
    }

    /**
     * Trim the declared fields and turn blanks into nulls.
     *
     * Every HTML form submits `''` for an untouched optional input, and `''` is
     * not null: it satisfies a "one of these is present" check, renders as a
     * stray space in the joined name, and sorts before every real value.
     *
     * Every declared field is present in the result even when the payload
     * omitted it, so the caller writes a complete row rather than a partial one.
     * Keys the caller did not declare are dropped.
     *
     * @param array<string, mixed> $input
     *
     * @return array<string, string|null>
     */
    public function normalise(array $input): array
    {
        if ($this->listField !== null) {
            // Handing the input back untouched would be a no-op that looks like
            // it worked, and the caller would write blanks into the table it
            // was trying to keep clean.
            throw new LogicException('A list-shaped name schema normalises through normaliseList().');
        }

        $normalised = [];

        foreach ($this->fields as $field) {
            $normalised[$field] = $this->trimmedOrNull($input[$field] ?? null);
        }

        return $normalised;
    }

    /**
     * The list form of {@see normalise()} — trimmed, blanks dropped, reindexed.
     *
     * @param array<array-key, mixed> $names
     *
     * @return list<string>
     */
    public function normaliseList(array $names): array
    {
        return array_values(array_filter(
            array_map($this->trimmedOrNull(...), $names),
            static fn (?string $name): bool => $name !== null,
        ));
    }

    /**
     * Trim a submitted value, or null it if there is nothing there.
     *
     * Scalars are stringified rather than refused: a form can legitimately post
     * `8` for a regnal suffix, and dropping it here would hide it from the
     * rules instead of from the user, who would then be told nothing at all.
     * Anything that is not a scalar — an array, an object, a file — is not a
     * name and becomes null, which the presence rule then reports properly.
     */
    private function trimmedOrNull(mixed $value): ?string
    {
        if (! is_scalar($value)) {
            return null;
        }

        $trimmed = trim((string) $value);

        return $trimmed === '' ? null : $trimmed;
    }

    /** @return array<string, mixed> */
    private function fieldRules(): array
    {
        $rules = [];

        foreach ($this->fields as $index => $field) {
            $rule = FluentRule::string($this->labelFor($field));

            $rule = $this->presence === PersonNamePresence::All
                ? $rule->required()
                : $rule->nullable();

            // Only the first field carries it, and only when there is a second
            // field for it to refer to — `required_without_all` with no
            // arguments is not a rule, it is a parse error waiting to happen.
            if ($this->presence === PersonNamePresence::AtLeastOne && $index === 0) {
                $others = array_slice($this->fields, 1);

                $rule = $others === []
                    ? $rule->required($this->presenceMessage)
                    : $rule
                        ->requiredWithoutAll(...$others)
                        ->messageFor('required_without_all', $this->atLeastOneMessage());
            }

            $rule = $rule->max($this->maxCharacters);

            if ($this->checkCharacters) {
                $rule = $rule->rule(new PersonName($this->allowDigits, $this->minNames, $this->maxNames));
            }

            $rules[$field] = $rule;
        }

        return $rules;
    }

    /** @return array<string, mixed> */
    private function listRules(): array
    {
        $field = (string) $this->listField;

        $entry = FluentRule::string($this->labelFor($field))
            ->required()
            ->max($this->maxCharacters);

        if ($this->checkCharacters) {
            $entry = $entry->rule(new PersonName($this->allowDigits, $this->minNames, $this->maxNames));
        }

        $list = FluentRule::array(label: $this->labelFor($field));

        // "At least one name" over a list is `min:1` on the list itself, which
        // is the same requirement the field form spells as required_without_all
        // — one entry has to be there, and which is not this rule's business.
        $list = $this->presence === PersonNamePresence::Optional
            ? $list->nullable()
            : $list->required()->min(1);

        if ($this->maxListNames !== null) {
            $list = $list->max($this->maxListNames);
        }

        return [$field => $list->each($entry)];
    }

    /**
     * "first name, middle name or last name" — the fields, in the sentence the
     * user has to act on.
     */
    private function atLeastOneMessage(): string
    {
        if ($this->presenceMessage !== null) {
            return $this->presenceMessage;
        }

        $labels = array_map($this->labelFor(...), $this->fields);
        $last = (string) array_pop($labels);

        $values = $labels === [] ? $last : implode(', ', $labels) . ' or ' . $last;

        $key = 'laranail/validation::validation.person_name_required';
        $message = trans($key, ['values' => $values]);

        // `trans()` hands the key back when the namespace is not registered —
        // a stale `bootstrap/cache/packages.php` is enough to do it — and this
        // message goes straight onto a form. Every other message in the package
        // is emitted through `$fail(...)->translate()` and is covered by
        // RuleMessagesResolveTest; this one is resolved eagerly, at rule-build
        // time, so it needs its own floor. A plain English sentence is a worse
        // translation and a far better thing to show someone than
        // "laranail/validation::validation.person_name_required".
        return is_string($message) && $message !== $key
            ? $message
            : "Please provide at least one of {$values}.";
    }

    private function labelFor(string $field): string
    {
        return $this->labels[$field] ?? str_replace('_', ' ', $field);
    }

    /**
     * Copy-on-write, so a schema handed to two form requests cannot be mutated
     * by one of them. The builders it produces are freshly constructed per
     * call, so nothing downstream shares state either.
     *
     * @param callable(self): void $mutate
     */
    private function with(callable $mutate): self
    {
        $clone = clone $this;
        $mutate($clone);

        return $clone;
    }
}
