<?php declare(strict_types=1);

namespace Simtabi\Laranail\Validation\Rules\Encoding;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * An RFC 2397 data URI: `data:[<mediatype>][;base64],<data>`.
 *
 * Both the media type and the `;base64` flag are optional; the comma is
 * not. What follows it is validated for what the header CLAIMS it is —
 * strict canonical base64 when flagged (via {@see Base64}), URL-encoded
 * text otherwise (a raw space fails; `%20` passes). A media type must be a
 * full `type/subtype`; `;base64` is distinguishable from a parameter
 * because parameters carry `=`.
 *
 * `$mediaTypes` restricts what the URI may declare — exact (`image/png`)
 * or by family (`image/*`). Restriction requires a DECLARED type: the
 * RFC's implied `text/plain` default is what an attacker gets for free by
 * omitting the header, so an untyped URI does not qualify. Note this
 * matches the declaration, not the payload — sniff the decoded bytes
 * ({@see Base64Image}) when the content itself is the question.
 *
 * Pure tier — no IO.
 */
final readonly class DataUri implements ValidationRule
{
    private const string SHAPE =
        '/^data:(?<media>[\w!#$&^.+-]+\/[\w!#$&^.+-]+(?:;[\w-]+=[^;,]+)*)?(?<b64>;base64)?,(?<data>.*)$/isD';

    private const string URL_ENCODED = "/^(?:[A-Za-z0-9\\-_.~!$&'()*+,;=:@\\/?]|%[0-9A-Fa-f]{2})*$/D";

    /**
     * @param  list<string>  $mediaTypes  Accepted declared types, exact or `family/*`.
     */
    public function __construct(private array $mediaTypes = []) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || ! $this->passes($value)) {
            $fail('laranail-validation::validation.data_uri')->translate();
        }
    }

    private function passes(string $value): bool
    {
        if (preg_match(self::SHAPE, $value, $parts) !== 1) {
            return false;
        }

        $declared = $parts['media'] === '' ? null : strtolower(explode(';', $parts['media'])[0]);

        if ($this->mediaTypes !== [] && ! $this->declaredTypeAllowed($declared)) {
            return false;
        }

        if ($parts['b64'] !== '') {
            return Base64::passes($parts['data']);
        }

        return preg_match(self::URL_ENCODED, $parts['data']) === 1;
    }

    private function declaredTypeAllowed(?string $declared): bool
    {
        if ($declared === null) {
            return false;
        }

        foreach ($this->mediaTypes as $allowed) {
            $allowed = strtolower($allowed);

            if ($declared === $allowed) {
                return true;
            }

            if (str_ends_with($allowed, '/*') && str_starts_with($declared, substr($allowed, 0, -1))) {
                return true;
            }
        }

        return false;
    }
}
