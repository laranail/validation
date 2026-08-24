<?php declare(strict_types=1);

namespace Simtabi\Laranail\Validation\Rules\I18n;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Simtabi\Laranail\Validation\Contracts\I18n\CountryDataset;

/**
 * An assigned ISO 3166-1 country code — alpha-2 (`KE`) by default, alpha-3
 * (`KEN`) behind the flag.
 *
 * Strict about case on purpose: this rule usually guards a column other
 * systems read, and `us` passing validation stores a value an exact-match
 * lookup later misses. `caseInsensitive:` is the opt-in for the lenient
 * form — the rule still validates the code, it just folds first (it does
 * NOT rewrite the stored value; normalise in `prepareForValidation()` or a
 * cast if the column must hold the canonical case).
 *
 * The code set is the {@see CountryDataset} contract; the bundled default
 * ships the full registry, and an application narrows or extends it by
 * binding its own. Pure tier — a hash lookup, no IO.
 */
final class CountryCode implements ValidationRule
{
    public function __construct(
        private readonly bool $alpha3 = false,
        private readonly bool $caseInsensitive = false,
        private ?CountryDataset $dataset = null,
    ) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value)) {
            $fail('laranail-validation::validation.country_code')->translate();

            return;
        }

        $code = $this->caseInsensitive ? strtoupper($value) : $value;

        $assigned = $this->alpha3
            ? $this->dataset()->isAlpha3($code)
            : $this->dataset()->isAlpha2($code);

        if (! $assigned) {
            $fail('laranail-validation::validation.country_code')->translate();
        }
    }

    private function dataset(): CountryDataset
    {
        return $this->dataset ??= resolve(CountryDataset::class);
    }
}
