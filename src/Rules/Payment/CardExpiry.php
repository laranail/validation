<?php declare(strict_types=1);

namespace Simtabi\Laranail\Validation\Rules\Payment;

use Closure;
use DateTimeZone;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * A card expiry in the spellings embossing and forms actually use —
 * `08/26`, `8/26`, `12/2027`, `08-26`, `2027-03` — valid through the END
 * of the stated month, which is what expiry means on a card.
 *
 * "Now" is timezone-dependent on the last day of the month (23:30 UTC on
 * Aug 31 is already September in Nairobi), so `timezone:` names whose
 * calendar answers; default is the application's. `Carbon::setTestNow()`
 * controls it in tests.
 *
 * `$maxYearsAhead` (default 20) rejects the implausibly distant expiry —
 * `08/47` is a typo for `08/27`, and no scheme issues two decades out.
 * Two-digit years read as 20xx; by the 2090s this rule had better not
 * still be the one parsing them.
 *
 * Pure tier — no IO.
 */
final class CardExpiry implements ValidationRule
{
    private const string SHAPE =
        '/^(?:(?<m1>0?[1-9]|1[0-2])[\/-](?<y1>\d{2}|\d{4})|(?<y2>\d{4})-(?<m2>0[1-9]|1[0-2]))$/D';

    public function __construct(
        private readonly ?string $timezone = null,
        private readonly int $maxYearsAhead = 20,
    ) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || ! $this->passes($value)) {
            $fail('laranail-validation::validation.card_expiry')->translate();
        }
    }

    private function passes(string $value): bool
    {
        if (preg_match(self::SHAPE, $value, $parts) !== 1) {
            return false;
        }

        $month = (int) ($parts['m1'] !== '' ? $parts['m1'] : $parts['m2']);
        $year = $parts['m1'] !== '' ? $parts['y1'] : $parts['y2'];
        $year = strlen($year) === 2 ? 2000 + (int) $year : (int) $year;

        $configured = config('app.timezone', 'UTC');
        $zone = new DateTimeZone($this->timezone ?? (is_string($configured) ? $configured : 'UTC'));
        $today = now($zone)->toDateTimeImmutable()->setTimezone($zone);

        $currentYear = (int) $today->format('Y');
        $currentMonth = (int) $today->format('n');

        if ($year < $currentYear || ($year === $currentYear && $month < $currentMonth)) {
            return false;
        }

        return $year - $currentYear < $this->maxYearsAhead;
    }
}
