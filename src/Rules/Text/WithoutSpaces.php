<?php declare(strict_types=1);

namespace Simtabi\Laranail\Validation\Rules\Text;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * A value containing no whitespace of any kind.
 *
 * `\s` alone is not enough on Unicode input: it misses the non-breaking space,
 * the zero-width space and the other Unicode separators that are routinely
 * pasted in from word processors and used to slip past naive checks. The
 * pattern uses `\p{Z}` for separators plus the explicit control-range
 * whitespace and zero-width characters, with the `u` modifier.
 *
 * Pure tier — no IO.
 */
final class WithoutSpaces implements ValidationRule
{
    /** Unicode separators, ASCII whitespace controls, and zero-width characters. */
    private const string WHITESPACE = '/[\p{Z}\s\x{200B}-\x{200D}\x{2060}\x{FEFF}]/u';

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || preg_match(self::WHITESPACE, $value) === 1) {
            $fail('laranail-validation::validation.without_spaces')->translate();
        }
    }
}
