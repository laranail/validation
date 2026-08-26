<?php declare(strict_types=1);

namespace Simtabi\Laranail\Validation\Rules\I18n;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Simtabi\Laranail\Validation\Contracts\I18n\LanguageDataset;

/**
 * An assigned ISO 639-1 language code (`en`, `sw`) — lowercase, the
 * registry's canonical form, unless `caseInsensitive:` folds first.
 *
 * The usual consumer is a locale picker, and most applications support a
 * handful of languages rather than all 183 — bind a narrower
 * {@see LanguageDataset} and this rule enforces exactly that set. Pure tier.
 */
final class LanguageCode implements ValidationRule
{
    public function __construct(
        private readonly bool $caseInsensitive = false,
        private ?LanguageDataset $dataset = null,
    ) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value)) {
            $fail('laranail/validation::validation.language_code')->translate();

            return;
        }

        $code = $this->caseInsensitive ? strtolower($value) : $value;

        if (! $this->dataset()->isAlpha2($code)) {
            $fail('laranail/validation::validation.language_code')->translate();
        }
    }

    private function dataset(): LanguageDataset
    {
        return $this->dataset ??= resolve(LanguageDataset::class);
    }
}
