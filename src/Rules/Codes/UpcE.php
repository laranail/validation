<?php declare(strict_types=1);

namespace Simtabi\Laranail\Validation\Rules\Codes;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * A UPC-E — the zero-suppressed, 8-digit compression of a UPC-A: number
 * system (`0` or `1`), six data digits, and the check digit.
 *
 * The check digit belongs to the EXPANDED UPC-A, not to the compressed
 * digits: the sixth data digit selects which zero-suppression pattern was
 * applied, the code is expanded accordingly, and the GTIN-12 checksum runs
 * on the result (via {@see Gtin}). Checksumming the 8 compressed digits —
 * the legacy implementation — both accepts invalid codes and rejects valid
 * ones, which for a checksum is the whole job failed.
 *
 * (UPC-A itself needs no rule of its own: it is GTIN-12 — use {@see Gtin}.)
 *
 * Pure tier — no IO.
 */
final class UpcE implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || ! self::passes($value)) {
            $fail('laranail-validation::validation.upc_e')->translate();
        }
    }

    public static function passes(string $value): bool
    {
        if (preg_match('/^[01]\d{7}$/D', $value) !== 1) {
            return false;
        }

        $numberSystem = $value[0];
        $data = substr($value, 1, 6);
        $selector = $data[5];

        // GS1's zero-suppression patterns, keyed by the sixth data digit.
        $body = match (true) {
            in_array($selector, ['0', '1', '2'], true) => $numberSystem . substr($data, 0, 2) . $selector . '0000' . substr($data, 2, 3),
            $selector === '3' => $numberSystem . substr($data, 0, 3) . '00000' . substr($data, 3, 2),
            $selector === '4' => $numberSystem . substr($data, 0, 4) . '00000' . $data[4],
            default => $numberSystem . substr($data, 0, 5) . '0000' . $selector,
        };

        return Gtin::passes($body . $value[7], [12]);
    }
}
