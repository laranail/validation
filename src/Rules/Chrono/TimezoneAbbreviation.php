<?php declare(strict_types=1);

namespace Simtabi\Laranail\Validation\Rules\Chrono;

use Closure;
use DateTimeZone;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * A timezone ABBREVIATION — `EST`, `CET`, `EAT` — the set Laravel's own
 * `timezone` rule rejects, because that rule validates identifiers
 * (`Europe/Berlin`) and an abbreviation is not one.
 *
 * The accepted set is PHP's own {@see DateTimeZone::listAbbreviations()},
 * so it tracks the timezone database the runtime actually resolves
 * against instead of a hand-kept list (the legacy rule's 28 entries).
 *
 * Prefer identifiers in new schemas — abbreviations are ambiguous (CST is
 * three different offsets) — but data that already carries them needs
 * validating as what it is.
 *
 * Pure tier — no IO.
 */
final class TimezoneAbbreviation implements ValidationRule
{
    /** @var array<string, true>|null */
    private static ?array $abbreviations = null;

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        self::$abbreviations ??= array_fill_keys(array_keys(DateTimeZone::listAbbreviations()), true);

        if (! is_string($value) || ! isset(self::$abbreviations[strtolower($value)])) {
            $fail('laranail-validation::validation.timezone_abbreviation')->translate();
        }
    }
}
