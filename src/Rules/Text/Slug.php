<?php declare(strict_types=1);

namespace Simtabi\Laranail\Validation\Rules\Text;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Simtabi\Laranail\Validation\Contracts\ClientCheckable;

/**
 * A URL slug: lowercase alphanumerics separated by single hyphens.
 *
 * Matches what `Str::slug()` produces, so a value that passes is stable
 * through a round trip. Leading, trailing and doubled hyphens are rejected —
 * `Str::slug()` never emits them, and accepting them means two slugs that
 * differ only in punctuation can point at the same resource.
 *
 * Pure tier — no IO. Whether the slug is already taken is `unique`'s job.
 */
final class Slug implements ClientCheckable, ValidationRule
{
    private const string PATTERN = '/^[a-z0-9]+(?:-[a-z0-9]+)*$/';

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || preg_match(self::PATTERN, $value) !== 1) {
            $fail('laranail-validation::validation.slug')->translate();
        }
    }

    /**
     * The whole check is this pattern, so the browser can run the same one
     * rather than a hand-written twin that would drift from it.
     */
    public function clientRules(): array
    {
        return [['rule' => 'regex', 'params' => ['pattern' => self::PATTERN]]];
    }
}
