<?php declare(strict_types=1);

namespace Simtabi\Laranail\Validation\Rules\Numbers;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Simtabi\Laranail\Validation\Contracts\ClientCheckable;

/**
 * A money amount written in plain decimal form.
 *
 *     new MonetaryAmount()                       // 0 or more, up to 2 decimals
 *     new MonetaryAmount(decimals: 3)            // e.g. KWD, BHD, TND
 *     new MonetaryAmount(allowNegative: true)    // refunds, ledger entries
 *
 * Distinct from `numeric|decimal:0,2`, which accepts `1e3`, `0x1A` and
 * `INF` — all numeric to PHP, none of them a price anyone typed. This accepts
 * an optional sign, digits, and at most `$decimals` fraction digits. Nothing
 * else: no thousands separators, no currency symbol, no exponent.
 *
 * Two decimals is the default because most currencies use two, but it is not
 * universal — JPY has none and KWD has three — so the count is a parameter
 * rather than a hardcoded assumption.
 *
 * The value is NOT converted. A rule that rewrote `1,234.50` into `1234.50`
 * would leave the application storing the unrewritten original, which is how
 * a display-formatted amount ends up in a database column.
 *
 * Pure tier — no IO.
 */
final readonly class MonetaryAmount implements ClientCheckable, ValidationRule
{
    public function __construct(
        private int $decimals = 2,
        private bool $allowNegative = false,
    ) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! self::passes($value, $this->decimals, $this->allowNegative)) {
            $fail('laranail/validation::validation.monetary_amount')
                ->translate(['decimals' => $this->decimals]);
        }
    }

    public static function passes(mixed $value, int $decimals = 2, bool $allowNegative = false): bool
    {
        if (is_int($value) || is_float($value)) {
            // A float cannot represent every decimal exactly, but the caller
            // has already lost that battle before the rule sees it; check the
            // magnitude and the sign and let the string form do the rest.
            if (is_float($value) && (is_nan($value) || is_infinite($value))) {
                return false;
            }

            $value = (string) $value;
        }

        if (! is_string($value)) {
            return false;
        }

        $value = trim($value);

        if ($value === '') {
            return false;
        }

        return preg_match(self::pattern($decimals, $allowNegative), $value) === 1;
    }

    /**
     * The pattern the rule itself matches against, so there is one definition.
     */
    public static function pattern(int $decimals = 2, bool $allowNegative = false): string
    {
        $sign = $allowNegative ? '[+-]?' : '\\+?';
        $fraction = $decimals > 0 ? '(?:\\.\\d{1,' . $decimals . '})?' : '';

        return '/^' . $sign . '\\d+' . $fraction . '$/D';
    }

    /**
     * @return list<array{rule: string, params: array<array-key, string>}>
     */
    public function clientRules(): array
    {
        return [['rule' => 'regex', 'params' => ['pattern' => self::pattern($this->decimals, $this->allowNegative)]]];
    }
}
