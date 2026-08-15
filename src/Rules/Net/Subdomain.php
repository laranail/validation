<?php declare(strict_types=1);

namespace Simtabi\Laranail\Validation\Rules\Net;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Simtabi\Laranail\Validation\Contracts\ClientCheckable;

/**
 * A single DNS label, as used for a user-chosen subdomain.
 *
 * One label only: `blog` rather than `blog.example.com`. Letters, digits and
 * hyphens, 1-63 characters, no leading or trailing hyphen.
 *
 * `xn--` is rejected outright. Accepting Punycode from user input invites
 * homograph attacks — `xn--pple-43d` renders as `аpple` with a Cyrillic а —
 * and a subdomain someone picks for themselves is exactly where that matters.
 * Accept a Unicode label and encode it yourself if internationalised
 * subdomains are wanted.
 *
 * Pure tier — no IO. Whether the subdomain is already taken is a Database-tier
 * question for `unique`.
 */
final class Subdomain implements ClientCheckable, ValidationRule
{
    private const string PATTERN = '/^(?!-)[a-z0-9-]{1,63}(?<!-)$/i';

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! self::passes($value)) {
            $fail('laranail-validation::validation.subdomain')->translate();
        }
    }

    public static function passes(mixed $value): bool
    {
        if (! is_string($value) || preg_match(self::PATTERN, $value) !== 1) {
            return false;
        }

        return ! str_starts_with(strtolower($value), 'xn--');
    }

    /**
     * The whole check is this pattern, so the browser can run the same one
     * rather than a hand-written twin that would drift from it.
     */
    /**
     * @return list<array{rule: string, params: array<array-key, string>}>
     */
    public function clientRules(): array
    {
        return [['rule' => 'regex', 'params' => ['pattern' => self::PATTERN]]];
    }
}
