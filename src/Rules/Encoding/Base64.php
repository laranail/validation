<?php declare(strict_types=1);

namespace Simtabi\Laranail\Validation\Rules\Encoding;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Simtabi\Laranail\Validation\Contracts\ClientCheckable;

/**
 * A canonically base64-encoded value.
 *
 * The check is a strict decode followed by a re-encode comparison, and the
 * round trip is the point: a charset regex passes strings no encoder ever
 * produces — missing padding, whitespace in the middle, and padding whose
 * discarded bits are non-zero (`aGVsbG9=` decodes, but nothing encodes TO
 * it). If the value must survive decode → store → encode unchanged, the
 * round trip is the property to check.
 *
 * Deliberately not {@see ClientCheckable}:
 * a regex cannot see the discarded bits, so a browser pattern would accept
 * values the server then rejects — the disagreement client checking exists
 * to avoid.
 *
 * Pure tier — no IO.
 */
final class Base64 implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || ! self::passes($value)) {
            $fail('laranail/validation::validation.base64')->translate();
        }
    }

    public static function passes(string $value): bool
    {
        $decoded = base64_decode($value, true);

        return $decoded !== false && base64_encode($decoded) === $value;
    }
}
