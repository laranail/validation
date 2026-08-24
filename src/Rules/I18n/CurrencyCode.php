<?php declare(strict_types=1);

namespace Simtabi\Laranail\Validation\Rules\I18n;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Translation\PotentiallyTranslatedString;
use LogicException;
use Simtabi\Laranail\Validation\Contracts\I18n\CurrencyDataset;

/**
 * A current ISO 4217 currency identifier — the alpha code (`USD`) by
 * default, the three-digit numeric code (`840`) with `numeric:`, or a
 * recognised symbol (`€`) with `symbol:`.
 *
 * One mode per rule instance: a field that accepts "USD or 840 or $" is
 * three different storage formats pretending to be one, and the caller
 * should decide which it wants before validating. Asking for two modes at
 * once is therefore a construction error, not a runtime `false`.
 *
 * "Current" is the operative word — the bundled {@see CurrencyDataset}
 * tracks the official registry, so retired codes (HRK, SLL, ZWL, …) fail.
 * A ledger that must still accept historical codes binds its own dataset.
 * Pure tier.
 */
final class CurrencyCode implements ValidationRule
{
    public function __construct(
        private readonly bool $numeric = false,
        private readonly bool $symbol = false,
        private readonly bool $caseInsensitive = false,
        private ?CurrencyDataset $dataset = null,
    ) {
        if ($this->numeric && $this->symbol) {
            throw new LogicException(
                'CurrencyCode validates one representation per instance — pick numeric: or symbol:, not both.',
            );
        }
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value)) {
            $this->fail($fail);

            return;
        }

        $passes = match (true) {
            $this->numeric => $this->dataset()->isNumericCode($value),
            $this->symbol => $this->dataset()->isSymbol($value),
            default => $this->dataset()->isCode($this->caseInsensitive ? strtoupper($value) : $value),
        };

        if (! $passes) {
            $this->fail($fail);
        }
    }

    /** @param  Closure(string): PotentiallyTranslatedString  $fail */
    private function fail(Closure $fail): void
    {
        $key = match (true) {
            $this->numeric => 'laranail-validation::validation.currency_code_numeric',
            $this->symbol => 'laranail-validation::validation.currency_symbol',
            default => 'laranail-validation::validation.currency_code',
        };

        $fail($key)->translate();
    }

    private function dataset(): CurrencyDataset
    {
        return $this->dataset ??= resolve(CurrencyDataset::class);
    }
}
