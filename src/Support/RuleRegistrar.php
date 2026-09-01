<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Validation\Support;

use Closure;
use Illuminate\Contracts\Container\Container;
use Illuminate\Contracts\Validation\ValidationRule;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Simtabi\Laranail\Validation\Contracts\ClientCheckable;
use SplFileInfo;

/**
 * The one registry of rule classes (§5.2.2) — the alias wiring, the console
 * `rules` command, the docs tooling and the JS exporter all read the same
 * set, so a rule cannot be visible to one consumer and invisible to another.
 *
 * Three sources merge, in order:
 *
 *  1. The package's own rules, DISCOVERED from `src/Rules` rather than
 *     hand-listed (a hand-kept list is the count drift the doc tests exist
 *     to catch). Scanned once per registrar, then memoized.
 *  2. Classes a consumer registers directly: `register(MyRule::class,
 *     alias: 'acme_thing', factory: fn ($params) => new MyRule(...))`.
 *     The alias is used AS GIVEN — vendor-scope it (`acme_...`), because
 *     the validator's extension map is a flat registry where the last
 *     writer silently wins.
 *  3. Classes tagged into the container under `laranail.validation.rules`
 *     — the tagged-collection seam for packages that prefer wiring over
 *     calls.
 *
 * Bound as a singleton by the provider; resolved at boot, never mutated
 * per request (the Octane rule the batch limit already follows).
 */
final class RuleRegistrar
{
    public const string TAG = 'laranail.validation.rules';

    /** @var list<class-string<ValidationRule>>|null Discovered package rules, memoized. */
    private ?array $discovered = null;

    /** @var list<class-string<ValidationRule>> Consumer-registered classes. */
    private array $registered = [];

    /** @var array<string, Closure(list<string>): ValidationRule> Alias → factory. */
    private array $aliasFactories = [];

    public function __construct(private readonly Container $container) {}

    /**
     * Register a consumer rule, optionally with a string alias.
     *
     * @param  class-string<ValidationRule>  $rule
     * @param  Closure(list<string>): ValidationRule|null  $factory  Builds the rule from
     *                                                               string-rule parameters; required for an alias.
     */
    public function register(string $rule, ?string $alias = null, ?Closure $factory = null): self
    {
        if (! in_array($rule, $this->registered, true)) {
            $this->registered[] = $rule;
        }

        if ($alias !== null && $factory instanceof Closure) {
            $this->aliasFactories[$alias] = $factory;
        }

        return $this;
    }

    /**
     * Every known rule class: discovered + registered + container-tagged.
     *
     * @return list<class-string<ValidationRule>>
     */
    public function classes(): array
    {
        $tagged = [];

        foreach ($this->container->tagged(self::TAG) as $service) {
            if ($service instanceof ValidationRule) {
                $tagged[] = $service::class;
            }
        }

        return array_values(array_unique([...$this->discover(), ...$this->registered, ...$tagged]));
    }

    /**
     * The subset advertising a browser form — what the wire schema can
     * decide client-side.
     *
     * @return list<class-string<ValidationRule>>
     */
    public function clientCheckable(): array
    {
        return array_values(array_filter(
            $this->classes(),
            static fn (string $class): bool => is_subclass_of($class, ClientCheckable::class),
        ));
    }

    /**
     * Consumer-registered alias factories, for the provider to wire beside
     * the package's own prefixed aliases.
     *
     * @return array<string, Closure(list<string>): ValidationRule>
     */
    public function aliasFactories(): array
    {
        return $this->aliasFactories;
    }

    /** @return list<class-string<ValidationRule>> */
    private function discover(): array
    {
        if ($this->discovered !== null) {
            return $this->discovered;
        }

        $root = __DIR__.'/../Rules';
        $classes = [];

        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root)) as $file) {
            if (! $file instanceof SplFileInfo || $file->getExtension() !== 'php') {
                continue;
            }

            $relative = substr((string) realpath($file->getPathname()), strlen((string) realpath($root)) + 1, -4);
            $class = 'Simtabi\\Laranail\\Validation\\Rules\\'.str_replace(DIRECTORY_SEPARATOR, '\\', $relative);

            if (class_exists($class) && is_subclass_of($class, ValidationRule::class)) {
                $classes[] = $class;
            }
        }

        sort($classes);

        return $this->discovered = $classes;
    }
}
