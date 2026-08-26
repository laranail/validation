<?php declare(strict_types=1);

namespace Simtabi\Laranail\Validation\Rules\Text;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Simtabi\Laranail\Validation\Contracts\ClientCheckable;

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
final class WithoutSpaces implements ClientCheckable, ValidationRule
{
    /** Unicode separators, ASCII whitespace controls, and zero-width characters. */
    private const string WHITESPACE = '/[\p{Z}\s\x{200B}-\x{200D}\x{2060}\x{FEFF}]/u';

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || preg_match(self::WHITESPACE, $value) === 1) {
            $fail('laranail/validation::validation.without_spaces')->translate();
        }
    }

    /**
     * The whole check is this pattern, so the browser can run the same one
     * rather than a hand-written twin that would drift from it.
     */
    /**
     * @return list<array{rule: string, params: array<array-key, string>}>
     */
    public function clientRules(): array
    {
        return [['rule' => 'not_regex', 'params' => ['pattern' => self::WHITESPACE]]];
    }
}
