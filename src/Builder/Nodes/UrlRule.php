<?php declare(strict_types=1);

namespace Simtabi\Laranail\Validation\Builder\Nodes;

use Illuminate\Contracts\Validation\DataAwareRule;
use Illuminate\Contracts\Validation\ValidatorAwareRule;
use Illuminate\Support\Traits\Conditionable;
use Illuminate\Support\Traits\Macroable;
use Simtabi\Laranail\Validation\Builder\Concerns\HasEmbeddedRules;
use Simtabi\Laranail\Validation\Builder\Concerns\HasFieldModifiers;
use Simtabi\Laranail\Validation\Builder\Concerns\SelfValidates;
use Simtabi\Laranail\Validation\Contracts\FluentRuleContract;
use Simtabi\Laranail\Validation\Rules\Net\Support\HostPattern;
use Simtabi\Laranail\Validation\Rules\Net\Url;

/**
 * A URL field.
 *
 * ```php
 * 'website'  => FluentRule::url()->required(),
 * 'webhook'  => FluentRule::url()->required()->secure()->publicHost(),
 * 'callback' => FluentRule::url()->required()->hostIs(['*.example.com'])->port(443),
 * ```
 *
 * The defaults are what a link a user typed should be: `http` or `https`, a
 * real host, and no `user:password@` in it. Everything stricter is one call.
 *
 * `FluentRule::url()` used to return a {@see StringRule} carrying Laravel's
 * bare `url` rule, which meant a URL field autocompleted `hexColor()`,
 * `uuid()` and `dateFormat()` while offering nothing about schemes or hosts.
 * The narrow surface is the point of the package; this is it applied here.
 */
class UrlRule implements DataAwareRule, FluentRuleContract, ValidatorAwareRule
{
    use Conditionable;
    use HasEmbeddedRules;
    use HasFieldModifiers;
    use Macroable;
    use SelfValidates;

    /** @var list<string> */
    protected array $constraints = ['string'];

    /** @var list<string> */
    protected array $schemes = ['http', 'https'];

    /** @var list<string> */
    protected array $hosts = [];

    /** @var list<string> */
    protected array $blockedHosts = [];

    /** @var list<int> */
    protected array $ports = [];

    protected bool $allowCredentials = false;

    protected bool $allowIpHost = true;

    protected bool $publicHostOnly = false;

    protected bool $requireTld = true;

    protected bool $allowQuery = true;

    protected bool $allowFragment = true;

    protected int $maxLength = Url::DEFAULT_MAX_LENGTH;

    public function __construct()
    {
        $this->seedLastConstraint('url');
    }

    /**
     * Accept only these schemes. Written without `://`.
     *
     * @param  list<string>|string  $schemes
     */
    public function scheme(array|string $schemes): static
    {
        $this->schemes = array_values(array_map(strtolower(...), (array) $schemes));

        return $this;
    }

    /** `https` only — for anything a credential or a token will travel over. */
    public function secure(): static
    {
        return $this->scheme('https');
    }

    /**
     * Restrict the host, with `*.example.com` matching subdomains and NOT the
     * bare domain. List both when both are wanted — see the note on
     * {@see HostPattern}.
     *
     * @param  list<string>|string  $hosts
     */
    public function hostIs(array|string $hosts): static
    {
        $this->hosts = array_values((array) $hosts);

        return $this;
    }

    /**
     * Bar these hosts, same syntax. Applied after the allow-list.
     *
     * @param  list<string>|string  $hosts
     */
    public function hostIsNot(array|string $hosts): static
    {
        $this->blockedHosts = array_values((array) $hosts);

        return $this;
    }

    /**
     * Accept only these ports. A URL with no port is accepted only if the
     * default for its scheme is in the list — state 80 or 443 explicitly.
     *
     * @param  list<int>|int  $ports
     */
    public function port(array|int $ports): static
    {
        $this->ports = array_values(array_map(intval(...), (array) $ports));

        return $this;
    }

    /**
     * Permit `https://user:password@example.com/`.
     *
     * Off by default. A URL a user submits should not carry credentials: it
     * ends up in a database, in a log line, and in the referrer of whatever
     * the application fetches next.
     */
    public function allowCredentials(bool $allow = true): static
    {
        $this->allowCredentials = $allow;

        return $this;
    }

    /** Require a host name; reject `http://192.0.2.1/` and `http://[2001:db8::1]/`. */
    public function withoutIpHost(): static
    {
        $this->allowIpHost = false;

        return $this;
    }

    /**
     * Reject reserved IP literals and the loopback names.
     *
     * Stops the obvious `http://169.254.169.254/latest/meta-data` and
     * `http://127.0.0.1:6379/`. It is **hygiene, not an SSRF boundary**: it
     * cannot see where a host name resolves, and a rule that could would still
     * be defeated by rebinding between the check and the request. Read
     * {@see Url::isPublicHost()} before relying on it for that.
     */
    public function publicHost(): static
    {
        $this->publicHostOnly = true;

        return $this;
    }

    /** Accept a single-label host such as `intranet` or `localhost`. */
    public function allowSingleLabelHost(): static
    {
        $this->requireTld = false;

        return $this;
    }

    /** Reject `?a=b`. For a field that must hold a base address. */
    public function withoutQuery(): static
    {
        $this->allowQuery = false;

        return $this;
    }

    /** Reject `#section`. A fragment never reaches the server, so a stored one is noise. */
    public function withoutFragment(): static
    {
        $this->allowFragment = false;

        return $this;
    }

    /** The character limit. Defaults to 2048 — see {@see Url::DEFAULT_MAX_LENGTH}. */
    public function maxLength(int $characters): static
    {
        $this->maxLength = $characters;

        return $this;
    }

    /**
     * Also require the URL to resolve, via Laravel's `active_url`.
     *
     * **Network tier** — one DNS lookup per validation, on a value the user
     * controls. Fine on an admin form, expensive on a public one.
     */
    public function active(?string $message = null): static
    {
        return $this->addRule('active_url', $message);
    }

    /**
     * @return list<string|object>
     */
    protected function buildValidationRules(): array
    {
        return [
            ...$this->reorderConstraints($this->constraints),
            new Url(
                schemes: $this->schemes,
                hosts: $this->hosts,
                blockedHosts: $this->blockedHosts,
                ports: $this->ports,
                allowCredentials: $this->allowCredentials,
                allowIpHost: $this->allowIpHost,
                publicHostOnly: $this->publicHostOnly,
                requireTld: $this->requireTld,
                allowQuery: $this->allowQuery,
                allowFragment: $this->allowFragment,
                maxLength: $this->maxLength,
            ),
            ...$this->rules,
        ];
    }
}
