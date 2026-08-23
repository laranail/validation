<?php declare(strict_types=1);

namespace Simtabi\Laranail\Validation\Rules\Net;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Simtabi\Laranail\Validation\Rules\Net\Support\HostPattern;
use Simtabi\Laranail\Validation\Rules\Net\Support\IpClassifier;

/**
 * A URL, checked past the point Laravel's `url` rule stops.
 *
 * `url` answers one question — is this shaped like a URL — and every value
 * below passes it:
 *
 *     https://user:password@example.com/       credentials in a stored link
 *     http://169.254.169.254/latest/meta-data  the cloud metadata endpoint
 *     https://example.com:22/                  a scheme and port that disagree
 *     ftp://files.example.com/                 a scheme nobody meant to allow
 *
 * None of those is malformed. Each is a different question, and answering
 * them with a regex at the call site is where the mistakes happen — the
 * suffix check that accepts `evil-example.com`, the `str_contains` that
 * accepts `example.com.attacker.test`.
 *
 * The defaults are the ones a link a user typed should have: `http` or
 * `https`, a host, no credentials. Everything else is opt-in.
 *
 * ## Internationalised hosts
 *
 * `münchen.de` is converted to its A-label form before the structural check,
 * so the Unicode and Punycode spellings of one host get the same answer.
 * Requires `ext-intl`; without it non-ASCII input is rejected rather than
 * silently mishandled, matching {@see DomainName}.
 *
 * Pure tier — no IO. Nothing here resolves a name or fetches the URL. That
 * limit is load-bearing for {@see $publicHostOnly}; read its note.
 */
final readonly class Url implements ValidationRule
{
    /**
     * The practical ceiling. No RFC sets one, but IE capped at 2,083 and it
     * became the de-facto limit for anything that has to survive a browser
     * address bar, a log line or a database index.
     */
    public const int DEFAULT_MAX_LENGTH = 2048;

    /** Hosts that mean "this machine" without being IP literals. */
    private const array LOOPBACK_NAMES = ['localhost', 'localhost.localdomain', 'ip6-localhost', 'ip6-loopback'];

    /**
     * @param  list<string>  $schemes  Accepted schemes, lowercase, without `://`.
     * @param  list<string>  $hosts  Allow-list; `*.example.com` matches subdomains only.
     * @param  list<string>  $blockedHosts  Deny-list, same syntax, applied after the allow-list.
     * @param  list<int>  $ports  Accepted ports; empty accepts any, including none.
     * @param  bool  $allowCredentials  Permit `user:pass@` in the authority.
     * @param  bool  $allowIpHost  Permit an IP literal instead of a name.
     * @param  bool  $publicHostOnly  Reject reserved IP literals and loopback names.
     * @param  bool  $requireTld  Reject single-label hosts such as `intranet`.
     */
    public function __construct(
        private array $schemes = ['http', 'https'],
        private array $hosts = [],
        private array $blockedHosts = [],
        private array $ports = [],
        private bool $allowCredentials = false,
        private bool $allowIpHost = true,
        private bool $publicHostOnly = false,
        private bool $requireTld = true,
        private bool $allowQuery = true,
        private bool $allowFragment = true,
        private int $maxLength = self::DEFAULT_MAX_LENGTH,
    ) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $parts = $this->structure($value);

        // Separate keys, because "that is not a URL" and "that scheme is not
        // allowed here" send the user to different places. A single message
        // for both makes a working link look broken for no stated reason.
        if ($parts === null) {
            $fail('laranail-validation::validation.url.malformed')->translate();

            return;
        }

        $scheme = strtolower((string) ($parts['scheme'] ?? ''));

        if (! in_array($scheme, $this->schemes, true)) {
            $fail('laranail-validation::validation.url.scheme')
                ->translate(['schemes' => implode(', ', $this->schemes)]);

            return;
        }

        if (! $this->allowCredentials && (isset($parts['user']) || isset($parts['pass']))) {
            $fail('laranail-validation::validation.url.credentials')->translate();

            return;
        }

        $host = strtolower((string) ($parts['host'] ?? ''));

        if ($host === '') {
            $fail('laranail-validation::validation.url.malformed')->translate();

            return;
        }

        if (! $this->hostShapeIsValid($host)) {
            $fail('laranail-validation::validation.url.host')->translate();

            return;
        }

        if ($this->publicHostOnly && ! $this->isPublicHost($host)) {
            $fail('laranail-validation::validation.url.private_host')->translate();

            return;
        }

        if ($this->hosts !== [] && ! HostPattern::matches($host, $this->hosts)) {
            $fail('laranail-validation::validation.url.host_is')
                ->translate(['hosts' => implode(', ', $this->hosts)]);

            return;
        }

        if ($this->blockedHosts !== [] && HostPattern::matches($host, $this->blockedHosts)) {
            $fail('laranail-validation::validation.url.host_is_not')
                ->translate(['hosts' => implode(', ', $this->blockedHosts)]);

            return;
        }

        if ($this->ports !== [] && ! in_array((int) ($parts['port'] ?? 0), $this->ports, true)) {
            $fail('laranail-validation::validation.url.port')
                ->translate(['ports' => implode(', ', array_map(strval(...), $this->ports))]);

            return;
        }

        if (! $this->allowQuery && isset($parts['query'])) {
            $fail('laranail-validation::validation.url.query')->translate();

            return;
        }

        if (! $this->allowFragment && isset($parts['fragment'])) {
            $fail('laranail-validation::validation.url.fragment')->translate();
        }
    }

    /**
     * Decompose the value, or null when it is not structurally a URL.
     *
     * `parse_url()` alone is not a validator — it is happy with `http://` and
     * with a host containing spaces — so `filter_var` gates it first and the
     * host is checked separately below.
     *
     * @return array<string, int|string>|null
     */
    private function structure(mixed $value): ?array
    {
        if (! is_string($value) || $value === '' || mb_strlen($value) > $this->maxLength) {
            return null;
        }

        // Control characters and whitespace ANYWHERE, not just at the ends.
        // A newline inside a URL that later reaches a header is response
        // splitting, and a tab inside the host is how a browser and a
        // server-side parser are made to disagree about where the host ends.
        if (preg_match('/[\x00-\x20\x7F]/', $value) === 1) {
            return null;
        }

        $ascii = $this->toAscii($value);

        if ($ascii === null || filter_var($ascii, FILTER_VALIDATE_URL) === false) {
            return null;
        }

        $parts = parse_url($ascii);

        return is_array($parts) ? $parts : null;
    }

    /**
     * The URL in the pure-ASCII form `filter_var` can read, or null if it
     * cannot be produced.
     *
     * Two different conversions, because the two halves of a URL encode
     * non-ASCII differently and applying either one to the whole string is
     * wrong. The HOST becomes an A-label through Punycode; everything else is
     * percent-encoded, which is what a browser does with a Unicode path.
     * Running `idn_to_ascii` over the whole URL would mangle the path, and
     * percent-encoding the host would produce a name DNS cannot resolve.
     */
    private function toAscii(string $url): ?string
    {
        if (mb_check_encoding($url, 'ASCII')) {
            return $url;
        }

        if (! function_exists('idn_to_ascii')) {
            // Rejecting is the honest outcome. Passing the raw Unicode to
            // filter_var would fail anyway, but for a reason that reads as
            // "malformed" rather than "this build cannot check that".
            return null;
        }

        $host = parse_url($url, PHP_URL_HOST);
        $converted = $url;

        if (is_string($host) && $host !== '' && ! mb_check_encoding($host, 'ASCII')) {
            $encoded = idn_to_ascii($host, IDNA_NONTRANSITIONAL_TO_ASCII, INTL_IDNA_VARIANT_UTS46);

            if ($encoded === false || $encoded === '') {
                return null;
            }

            // The first occurrence only: a host may legitimately reappear in
            // the query string as a redirect parameter, and rewriting that
            // too would change what is being validated.
            $position = strpos($url, $host);

            if ($position === false) {
                return null;
            }

            $converted = substr_replace($url, $encoded, $position, strlen($host));
        }

        // Whatever non-ASCII is left is in the path, query or fragment, where
        // percent-encoding is the correct representation and is what the
        // browser would have sent anyway.
        return (string) preg_replace_callback(
            '/[\x80-\xFF]+/',
            static fn (array $match): string => rawurlencode($match[0]),
            $converted,
        );
    }

    private function hostShapeIsValid(string $host): bool
    {
        $ip = $this->hostAsIp($host);

        if ($ip !== null) {
            return $this->allowIpHost;
        }

        return DomainName::passes($host, $this->requireTld);
    }

    /**
     * The host as a bare IP address, or null when it is a name.
     *
     * A v6 literal arrives bracketed — `[::1]` — and `inet_pton` does not
     * accept the brackets, so an unbracketed check would read every v6 host as
     * a name and skip the IP rules entirely.
     */
    private function hostAsIp(string $host): ?string
    {
        $candidate = str_starts_with($host, '[') && str_ends_with($host, ']')
            ? substr($host, 1, -1)
            : $host;

        return IpClassifier::isValid($candidate) ? $candidate : null;
    }

    /**
     * Whether the host is one this rule is willing to call public.
     *
     * **This is hygiene, not an SSRF boundary, and the difference matters.**
     * It rejects an IP literal in a reserved range and the loopback names, so
     * the obvious `http://169.254.169.254/latest/meta-data` and
     * `http://127.0.0.1:6379/` do not get through. It cannot reject
     * `https://evil.test/` that resolves to 127.0.0.1, because answering that
     * needs DNS — and even a rule that resolved would be defeated by rebinding
     * between the check and the request.
     *
     * A real SSRF defence resolves at request time, pins the address it
     * validated, and refuses redirects. Treating this rule as that defence is
     * the mistake it is documented to prevent.
     */
    private function isPublicHost(string $host): bool
    {
        if (in_array($host, self::LOOPBACK_NAMES, true)) {
            return false;
        }

        $ip = $this->hostAsIp($host);

        return $ip === null || IpClassifier::isPubliclyRoutable($ip);
    }
}
