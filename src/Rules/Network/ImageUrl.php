<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Validation\Rules\Network;

use Closure;
use Illuminate\Support\Facades\Http;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Contracts\Validation\ValidationRule;
use Simtabi\Laranail\Validation\Concerns\SkipsPrecognition;
use Simtabi\Laranail\Validation\Rules\Net\Support\IpClassifier;
use Simtabi\Laranail\Validation\Contracts\PrecognitionSkippable;

/**
 * The URL currently serves an image — a HEAD probe expecting 200 with an
 * `image/*` content type. (An owner decision revived this from the plan's
 * recommended drop; the redesign is the guards.)
 *
 * The probe IS the rule, so unreachable FAILS — unlike DeliverableEmail's
 * fail-open DNS posture, "serves an image right now" is exactly what was
 * asked, and passing on error would make the rule decorative precisely
 * when it cannot see. Expect that trade before using it: a flaky remote
 * host fails your form.
 *
 * Guards: http/https only; loopback names and non-routable IP literals
 * refused before any request; redirects NOT followed (a redirect into
 * private space is the classic bypass — a 3xx simply fails); bounded
 * timeout. **Hygiene, not an SSRF boundary**: a public NAME resolving to
 * a private address, or rebinding between check and request, needs an
 * egress-layer defence. Do not point this rule at hostile input without
 * one.
 *
 * Network tier — skipped during precognition.
 */
final readonly class ImageUrl implements PrecognitionSkippable, ValidationRule
{
    use SkipsPrecognition;

    private const array LOOPBACK_NAMES = ['localhost', 'localhost.localdomain', 'ip6-localhost', 'ip6-loopback'];

    /**
     * @param list<string> $mimes Accepted `image/*` subtypes; empty accepts any image.
     */
    public function __construct(
        private array $mimes = [],
        private int $timeoutSeconds = 3,
    ) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        // Before the IO: a precognitive preview must cost nothing.
        if ($this->shouldSkipPrecognition()) {
            return;
        }

        if (! is_string($value) || ! $this->passes($value)) {
            $fail('laranail/validation::validation.image_url')->translate();
        }
    }

    private function passes(string $url): bool
    {
        if (filter_var($url, FILTER_VALIDATE_URL) === false) {
            return false;
        }

        $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));
        $host = strtolower(trim((string) parse_url($url, PHP_URL_HOST), '[]'));

        if (! in_array($scheme, ['http', 'https'], true) || $host === ''
            || in_array($host, self::LOOPBACK_NAMES, true)) {
            return false;
        }

        if (filter_var($host, FILTER_VALIDATE_IP) !== false && ! IpClassifier::isPubliclyRoutable($host)) {
            return false;
        }

        try {
            $response = Http::timeout($this->timeoutSeconds)
                ->connectTimeout($this->timeoutSeconds)
                ->withOptions(['allow_redirects' => false])
                ->head($url);
        } catch (ConnectionException) {
            return false;
        }

        if ($response->status() !== 200) {
            return false;
        }

        $type = strtolower(explode(';', $response->header('Content-Type'))[0]);

        if (! str_starts_with($type, 'image/')) {
            return false;
        }

        return $this->mimes === []
            || in_array(substr($type, strlen('image/')), $this->mimes, true);
    }
}
