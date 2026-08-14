<?php declare(strict_types=1);

namespace Simtabi\Laranail\Validation\Rules\Text;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * A human name.
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
 * Pure tier — no IO.
 */
final readonly class PersonName implements ValidationRule
{
    public function __construct(private bool $allowDigits = false) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! self::passes($value, $this->allowDigits)) {
            $fail('laranail-validation::validation.person_name')->translate();
        }
    }

    public static function passes(mixed $value, bool $allowDigits = false): bool
    {
        if (! is_string($value) || trim($value) === '') {
            return false;
        }

        // \p{S} covers emoji, currency, maths and other symbols — the class
        // that most often shows up in a name field is emoji.
        $allowed = $allowDigits
            ? '/^[\p{L}\p{M}\p{N} \'\-.]+$/u'
            : '/^[\p{L}\p{M} \'\-.]+$/u';

        if (preg_match($allowed, $value) !== 1) {
            return false;
        }

        // At least one actual letter: ' - . is punctuation, not a name.
        return preg_match('/\p{L}/u', $value) === 1;
    }
}
