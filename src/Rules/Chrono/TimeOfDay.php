<?php declare(strict_types=1);

namespace Simtabi\Laranail\Validation\Rules\Chrono;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * A time of day — `23:59`, `23:59:59`, or with `twelveHour:` the meridiem
 * form (`9:05 PM`, meridiem required, case-insensitive, space optional).
 *
 * The two conventions are one rule with a mode rather than two classes
 * because the field means the same thing either way; what differs is the
 * clock the user was shown. A 12-hour value without its meridiem is
 * rejected — `9:05` on a 12-hour form is ambiguous, and guessing AM is how
 * a scheduler books the wrong half of the day.
 *
 * `$separator` covers locales that write `23.59`; it replaces `:` in the
 * pattern verbatim (regex-quoted).
 *
 * Pure tier — no IO.
 */
final readonly class TimeOfDay implements ValidationRule
{
    public function __construct(
        private bool $twelveHour = false,
        private string $separator = ':',
    ) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || preg_match($this->pattern(), $value) !== 1) {
            $fail('laranail/validation::validation.time_of_day')->translate();
        }
    }

    private function pattern(): string
    {
        $sep = preg_quote($this->separator, '/');

        if ($this->twelveHour) {
            return '/^(0?[1-9]|1[0-2])' . $sep . '[0-5]\d(?:' . $sep . '[0-5]\d)? ?[AaPp][Mm]$/D';
        }

        return '/^([01]?\d|2[0-3])' . $sep . '[0-5]\d(?:' . $sep . '[0-5]\d)?$/D';
    }
}
