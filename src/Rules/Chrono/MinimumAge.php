<?php declare(strict_types=1);

namespace Simtabi\Laranail\Validation\Rules\Chrono;

use Closure;
use DateTimeImmutable;
use DateTimeZone;
use Exception;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * A date of birth at least `$years` completed years ago — the age-gate
 * rule, `new MinimumAge(18)`.
 *
 * Age is a TIMEZONE-DEPENDENT fact: at 20:00 UTC on the 23rd it is
 * already the 24th in Auckland, and the same person is 17 in London and
 * 18 at home. `$timezone` names whose midnight the birthday ticks over
 * at (default: the application's). "Today" comes through `now()`, so
 * `Carbon::setTestNow()` controls it in tests.
 *
 * A future date of birth fails — it is not "zero years old", it is
 * wrong — and so does anything the date parser cannot read.
 *
 * Pure tier — no IO.
 */
final readonly class MinimumAge implements ValidationRule
{
    public function __construct(
        private int $years,
        private ?string $timezone = null,
    ) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || ! $this->passes($value)) {
            $fail('laranail-validation::validation.minimum_age')->translate(['years' => $this->years]);
        }
    }

    private function passes(string $value): bool
    {
        $configured = config('app.timezone', 'UTC');
        $zone = new DateTimeZone($this->timezone ?? (is_string($configured) ? $configured : 'UTC'));

        try {
            $birth = new DateTimeImmutable($value, $zone);
        } catch (Exception) {
            return false;
        }

        $today = now($zone)->toDateTimeImmutable()->setTimezone($zone);
        $age = $birth->diff($today);

        return $age->invert === 0 && $age->y >= $this->years;
    }
}
