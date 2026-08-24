<?php declare(strict_types=1);

namespace Simtabi\Laranail\Validation\Rules\Chrono;

use Closure;
use Exception;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * An ISO 8601 duration — `P1Y`, `PT30M`, `P1DT12H` — as PHP's own
 * {@see \DateInterval} parses it, which is the definition that matters
 * because the value will be fed to it next.
 *
 * `positive:` additionally requires the duration to be NON-ZERO: the spec
 * string cannot express a negative duration, so "positive" can only mean
 * "not `P0Y`/`PT0S`" — the guard for a retention period or booking length
 * where zero is a configuration mistake, not a choice.
 *
 * Pure tier — no IO.
 */
final class DateInterval implements ValidationRule
{
    public function __construct(private readonly bool $positive = false) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || ! $this->passes($value)) {
            $key = $this->positive ? 'date_interval_positive' : 'date_interval';
            $fail('laranail-validation::validation.' . $key)->translate();
        }
    }

    private function passes(string $value): bool
    {
        try {
            $interval = new \DateInterval($value);
        } catch (Exception) {
            return false;
        }

        if (! $this->positive) {
            return true;
        }

        return $interval->y + $interval->m + $interval->d
            + $interval->h + $interval->i + $interval->s + $interval->f > 0;
    }
}
