<?php declare(strict_types=1);

namespace Simtabi\Laranail\Validation\Rules\Text;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Simtabi\Laranail\Validation\Contracts\ClientCheckable;

/**
 * A username: letters, digits, and single internal separators.
 *
 * Underscores, hyphens and dots are allowed between characters but never at
 * the start, at the end, or doubled. That rules out the shapes people use to
 * impersonate one another — `admin.` and `admin..` and `_admin` all read as
 * `admin` at a glance, and a doubled separator is invisible in most fonts.
 *
 * ASCII only, by design. A username is an identifier people type, read aloud
 * and compare visually; allowing Unicode invites homograph impersonation
 * (`аdmin` with a Cyrillic а), which is a much worse problem here than the
 * inconvenience of an ASCII-only handle.
 *
 * Pure tier — no IO. Availability is `unique`'s job, and a reserved-word list
 * belongs in the application.
 */
final readonly class Username implements ClientCheckable, ValidationRule
{
    public function __construct(
        private int $min = 3,
        private int $max = 32,
    ) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! self::passes($value, $this->min, $this->max)) {
            $fail('laranail-validation::validation.username')->translate();
        }
    }

    public static function passes(mixed $value, int $min = 3, int $max = 32): bool
    {
        if (! is_string($value)) {
            return false;
        }

        return preg_match(self::pattern($min, $max), $value) === 1;
    }

    /**
     * Shape and length in one pattern, so the rule and the form advertised to
     * a browser cannot disagree.
     *
     * The length bound was a `strlen()` check, which counts BYTES; the
     * lookahead counts characters. They cannot differ here, because the
     * character class is ASCII-only — anything that could pass has one byte
     * per character. That is what makes this rule expressible as a pattern at
     * all, and why a rule with a Unicode class could not do the same.
     */
    public static function pattern(int $min = 3, int $max = 32): string
    {
        // Alphanumeric at both ends; separators only between, never doubled.
        return '/^(?=.{' . $min . ',' . $max . '}$)[a-zA-Z0-9]+(?:[._-][a-zA-Z0-9]+)*$/';
    }

    public function clientRules(): array
    {
        return [['rule' => 'regex', 'params' => ['pattern' => self::pattern($this->min, $this->max)]]];
    }
}
