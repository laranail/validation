<?php declare(strict_types=1);

namespace Simtabi\Laranail\Validation\Rules\Chrono;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * A Unix timestamp in canonical integer form — an int, or the string a form
 * posts one as.
 *
 * Canonical means exactly what an encoder emits: no float (`1.5` is not a
 * timestamp, it is a bug reaching the API), no leading zeros, no `+`, and
 * `-0` never. The legacy rule took `is_numeric`, which accepts all of those
 * plus `1e9` and hex. Pre-epoch (negative) values are real Unix times but
 * rare in form input, so they are opt-in via `allowNegative:`.
 *
 * Deliberately no range cap: what counts as a plausible instant is the
 * field's business rule (`after:`/`before:` on a date field, or a bound in
 * the form request), not a property of the encoding.
 *
 * Pure tier — no IO.
 */
final readonly class UnixTimestamp implements ValidationRule
{
    public function __construct(private bool $allowNegative = false) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! $this->passes($value)) {
            $fail('laranail-validation::validation.unix_timestamp')->translate();
        }
    }

    private function passes(mixed $value): bool
    {
        if (is_int($value)) {
            return $this->allowNegative || $value >= 0;
        }

        if (! is_string($value)) {
            return false;
        }

        $pattern = $this->allowNegative ? '/^-?(?:0|[1-9]\d*)$/D' : '/^(?:0|[1-9]\d*)$/D';

        return preg_match($pattern, $value) === 1 && $value !== '-0';
    }
}
