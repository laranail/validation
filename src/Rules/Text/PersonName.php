<?php declare(strict_types=1);

namespace Simtabi\Laranail\Validation\Rules\Text;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Simtabi\Laranail\Validation\Contracts\ClientCheckable;

/**
 * A human name, or several of them in one field.
 *
 * Deliberately permissive about letters and strict about everything else.
 * Names carry marks, apostrophes, hyphens and spaces across every script —
 * `O'Neill`, `Jean-Luc`, `Müller`, `李`, `Ait Ben Haddou` — so the letter
 * class is Unicode (`\p{L}` plus combining marks `\p{M}`), not `[a-z]`.
 *
 * A validator that assumes names are ASCII, or that they have two parts, or
 * that they contain no punctuation, is wrong about a large fraction of the
 * world's population. This rule tries only to exclude what is definitely not
 * a name: digits, emoji and other symbols, and markup punctuation.
 *
 * Digits are rejected by default because they are almost always a paste error
 * or a bot, but `allowDigits: true` exists for the systems that genuinely
 * carry them (a suffix, a legal entity name).
 *
 * ## Why a field may hold more than one name
 *
 * By default any number of whitespace-separated names is accepted, and that
 * default is the load-bearing one. A person with three given names has to put
 * them somewhere, and a form with one `middle_name` box is where they go —
 * "Ada Byron Gordon" is a correct answer to that question, not a filled-in-
 * wrong one. A rule that silently required a single token would reject it and
 * report a character problem, which names neither the cause nor the fix.
 *
 * The bound exists for the systems that genuinely need one:
 *
 *     new PersonName()                  // one name or many — the default
 *     PersonName::single()              // exactly one, for a strict given-name column
 *     PersonName::names(min: 2)         // a full-name field that must carry a surname
 *     PersonName::names(1, 3)           // up to three
 *
 * Counting is by whitespace run, after trimming, so "Ada  Byron" is two names
 * rather than three.
 *
 * Pure tier — no IO.
 */
final readonly class PersonName implements ClientCheckable, ValidationRule
{
    /**
     * @param  int  $minNames  Fewest whitespace-separated names the field may carry.
     * @param  int|null  $maxNames  Most it may carry; null for no upper bound.
     */
    public function __construct(
        private bool $allowDigits = false,
        private int $minNames = 1,
        private ?int $maxNames = null,
    ) {}

    /** Exactly one name — for a column that must not absorb a second. */
    public static function single(bool $allowDigits = false): self
    {
        return new self($allowDigits, 1, 1);
    }

    /** A bounded number of names in the one field. */
    public static function names(int $min = 1, ?int $max = null, bool $allowDigits = false): self
    {
        return new self($allowDigits, $min, $max);
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || ! self::hasNameCharacters($value, $this->allowDigits)) {
            $fail('laranail-validation::validation.person_name')->translate();

            return;
        }

        $count = self::countNames($value);

        // Separate keys rather than one. "must be a name: letters, marks,
        // spaces…" is the wrong sentence to show someone whose characters were
        // fine and whose field held one name too many — it sends them looking
        // for a bad character that is not there.
        if ($count < $this->minNames) {
            $fail('laranail-validation::validation.person_name_min')->translate(['min' => $this->minNames]);

            return;
        }

        if ($this->maxNames !== null && $count > $this->maxNames) {
            $fail('laranail-validation::validation.person_name_max')->translate(['max' => $this->maxNames]);
        }
    }

    public static function passes(mixed $value, bool $allowDigits = false, int $minNames = 1, ?int $maxNames = null): bool
    {
        if (! is_string($value) || ! self::hasNameCharacters($value, $allowDigits)) {
            return false;
        }

        $count = self::countNames($value);

        return $count >= $minNames && ($maxNames === null || $count <= $maxNames);
    }

    /**
     * The rule's own patterns, so the browser runs the same expressions rather
     * than a hand-written twin that would drift from them.
     *
     * Three of them, and the list is what makes that expressible: the check is
     * a character class AND an at-least-one-letter test AND, when bounded, a
     * name count. Folding the count into the character pattern would mean
     * regenerating that pattern per bound and getting the boundary right in a
     * place nobody reads.
     *
     * @return list<array{rule: string, params: array<array-key, string>}>
     */
    public function clientRules(): array
    {
        $rules = [
            ['rule' => 'regex', 'params' => ['pattern' => self::characterPattern($this->allowDigits)]],
            ['rule' => 'regex', 'params' => ['pattern' => self::LETTER]],
        ];

        $names = $this->namePattern();

        if ($names !== null) {
            $rules[] = ['rule' => 'regex', 'params' => ['pattern' => $names]];
        }

        return $rules;
    }

    /** At least one actual letter: `'`, `-` and `.` are punctuation, not a name. */
    private const string LETTER = '/\p{L}/u';

    private static function hasNameCharacters(string $value, bool $allowDigits): bool
    {
        if (trim($value) === '') {
            return false;
        }

        if (preg_match(self::characterPattern($allowDigits), $value) !== 1) {
            return false;
        }

        return preg_match(self::LETTER, $value) === 1;
    }

    /**
     * `\p{S}` covers emoji, currency, maths and other symbols — the class that
     * most often shows up in a name field is emoji.
     */
    private static function characterPattern(bool $allowDigits): string
    {
        return $allowDigits
            ? '/^[\p{L}\p{M}\p{N} \'\-.]+$/u'
            : '/^[\p{L}\p{M} \'\-.]+$/u';
    }

    private static function countNames(string $value): int
    {
        $trimmed = trim($value);

        return $trimmed === '' ? 0 : count((array) preg_split('/\s+/u', $trimmed));
    }

    /**
     * The count expressed as a pattern, or null when it constrains nothing.
     *
     * Surrounding whitespace is allowed because the PHP side trims before
     * counting: a pattern anchored on `\S` would reject " Ada " in the browser
     * and accept it on the server, which is the one direction a client check
     * must never fail in.
     */
    private function namePattern(): ?string
    {
        if ($this->minNames <= 1 && $this->maxNames === null) {
            return null;
        }

        $gaps = max(1, $this->minNames) - 1;
        $limit = $this->maxNames === null ? '' : (string) ($this->maxNames - 1);

        return '/^\s*\S+(?:\s+\S+){' . $gaps . ',' . $limit . '}\s*$/u';
    }
}
