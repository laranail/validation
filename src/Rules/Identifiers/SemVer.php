<?php declare(strict_types=1);

namespace Simtabi\Laranail\Validation\Rules\Identifiers;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Simtabi\Laranail\Validation\Contracts\ClientCheckable;

/**
 * A Semantic Versioning 2.0.0 version string.
 *
 * The pattern is the one published at semver.org, unmodified. It is stricter
 * than it first looks and the strictness is the point: leading zeroes are
 * rejected in every numeric identifier, so `1.01.0` is not a version, and a
 * `v` prefix is not part of the grammar.
 *
 * Pure tier — no IO.
 */
final class SemVer implements ClientCheckable, ValidationRule
{
    /**
     * The official semver.org pattern. Every quantifier is separated from the
     * next by a literal `.`, `-` or `+`, so there is no adjacent-quantifier
     * ambiguity for a backtracking attack to exploit — timed against
     * pathological input in the test suite rather than assumed.
     */
    private const string PATTERN = '/^(0|[1-9]\d*)\.(0|[1-9]\d*)\.(0|[1-9]\d*)'
        . '(?:-((?:0|[1-9]\d*|\d*[a-zA-Z-][0-9a-zA-Z-]*)'
        . '(?:\.(?:0|[1-9]\d*|\d*[a-zA-Z-][0-9a-zA-Z-]*))*))?'
        . '(?:\+([0-9a-zA-Z-]+(?:\.[0-9a-zA-Z-]+)*))?$/';

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || preg_match(self::PATTERN, $value) !== 1) {
            $fail('laranail-validation::validation.semver')->translate();
        }
    }

    /**
     * The whole check is this pattern, so the browser can run the same one
     * rather than a hand-written twin that would drift from it.
     */
    public function clientRule(): array
    {
        return ['rule' => 'regex', 'params' => ['pattern' => self::PATTERN]];
    }
}
