<?php declare(strict_types=1);

namespace Simtabi\Laranail\Validation\Rules\Identifiers;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * A JSON Web Token in JWS compact serialisation (RFC 7515 §3.1).
 *
 * Three base64url segments separated by dots. The signature may be empty —
 * that is an unsecured token, `alg: none` — but both dots must be present.
 *
 * Beyond the shape, the header is decoded and required to be a JSON object
 * carrying `alg`. A bare regex accepts any three dot-separated base64url runs,
 * including `aaa.bbb.ccc`, which is a shape and not a token; decoding the
 * header costs one base64 pass and rejects that class outright.
 *
 * **This validates form, never trust.** It does not verify the signature,
 * check `exp`, or look at who issued the token. A JWT that passes this rule is
 * still entirely attacker-controlled — decode and verify it with a JWT library
 * before believing a single claim. In particular, `alg: none` is well-formed.
 *
 * Pure tier — no IO.
 */
final class Jwt implements ValidationRule
{
    private const PATTERN = '/^[A-Za-z0-9_-]+\.[A-Za-z0-9_-]+\.[A-Za-z0-9_-]*$/';

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! self::passes($value)) {
            $fail('laranail-validation::validation.jwt')->translate();
        }
    }

    public static function passes(mixed $value): bool
    {
        if (! is_string($value) || preg_match(self::PATTERN, $value) !== 1) {
            return false;
        }

        $header = self::decodeSegment(explode('.', $value)[0]);

        return is_array($header) && isset($header['alg']);
    }

    /**
     * base64url differs from base64 in two ways: `-`/`_` replace `+`/`/`, and
     * the `=` padding is dropped. Both have to be undone before decoding.
     */
    private static function decodeSegment(string $segment): mixed
    {
        $base64 = strtr($segment, '-_', '+/');
        $base64 .= str_repeat('=', (4 - strlen($base64) % 4) % 4);

        $json = base64_decode($base64, true);

        if ($json === false) {
            return null;
        }

        return json_decode($json, associative: true);
    }
}
