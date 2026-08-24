<?php declare(strict_types=1);

namespace Simtabi\Laranail\Validation;

use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\InvokableValidationRule;
use Illuminate\Validation\Validator as ValidationValidator;
use Simtabi\Laranail\Package\Tools\Package;
use Simtabi\Laranail\Package\Tools\Providers\PackageServiceProvider;
use Simtabi\Laranail\Validation\Actions\CachedDnsResolver;
use Simtabi\Laranail\Validation\Commands\BenchmarkCommand;
use Simtabi\Laranail\Validation\Commands\DoctorCommand;
use Simtabi\Laranail\Validation\Commands\RulesCommand;
use Simtabi\Laranail\Validation\Contracts\Email\DisposableDomainList;
use Simtabi\Laranail\Validation\Contracts\Email\DnsResolver;
use Simtabi\Laranail\Validation\Contracts\Email\RoleAccountList;
use Simtabi\Laranail\Validation\Contracts\I18n\CountryDataset;
use Simtabi\Laranail\Validation\Contracts\I18n\CurrencyDataset;
use Simtabi\Laranail\Validation\Contracts\I18n\LanguageDataset;
use Simtabi\Laranail\Validation\Contracts\Payment\CardBrandCatalogue;
use Simtabi\Laranail\Validation\Contracts\ReservedUsernameList;
use Simtabi\Laranail\Validation\Support\Email\BundledDisposableDomainList;
use Simtabi\Laranail\Validation\Support\Email\BundledRoleAccountList;
use Simtabi\Laranail\Validation\Support\I18n\BundledCountryDataset;
use Simtabi\Laranail\Validation\Support\I18n\BundledCurrencyDataset;
use Simtabi\Laranail\Validation\Support\I18n\BundledLanguageDataset;
use Simtabi\Laranail\Validation\Support\Payment\BundledCardBrandCatalogue;
use Simtabi\Laranail\Validation\Support\RuleAliases;
use Simtabi\Laranail\Validation\Support\RuleRegistrar;
use Simtabi\Laranail\Validation\Support\Text\DefaultReservedUsernameList;
use Stringable;

/**
 * The package is usable with no provider at all — every builder entry point is
 * a static factory and the optimizer is wired by the traits. This provider
 * exists for the parts that must reach into the application: publishable
 * config, and (opt-in) the string-rule aliases for the extended rule library.
 */
class ValidationServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        // Translations are registered; views are not, because this package
        // ships none.
        //
        // This was deferred while package-tools derived the namespace as
        // `laranail/validation` with a slash. That mattered beyond tidiness:
        // published translations live in one directory, `lang/vendor/{ns}`, so
        // a slash nested them a level deeper than `vendor:publish` and every
        // consumer's override path look for them. package-tools now returns
        // the dashed form, which is what all 23 message keys here already
        // reference.
        $package
            ->name('laranail/validation')
            ->hasTranslations();
    }

    /**
     * Config is registered explicitly rather than through `hasConfigFile()`.
     *
     * That helper derives the config key from the file name, and only collapses
     * to the bare namespace when the two match. The convention wants a prefixed
     * file (`config/laranail-validation.php`, so `vendor:publish` cannot clobber
     * an application's own `config/validation.php`) AND the flat
     * `laranail.validation` key — which together would otherwise produce
     * `laranail.validation.laranail-validation`.
     */
    public function registeringPackage(): void
    {
        $this->mergeConfigFrom($this->configPath(), 'laranail.validation');

        $this->bindEmailListFallbacks();
        $this->bindI18nDatasetFallbacks();

        // One registry for the whole application: the alias wiring, the
        // console command and the docs tooling all read this singleton, and
        // a consumer's provider registers into the same one.
        $this->app->singletonIf(RuleRegistrar::class);
    }

    /**
     * Bind the bundled email lists, but only if nothing else has.
     *
     * `singletonIf`, not `singleton`, and the asymmetry is deliberate:
     * laranail/email binds these contracts UNCONDITIONALLY, so whichever
     * provider registers second gets the outcome right. If this one runs
     * first, laranail/email replaces the snapshot; if laranail/email runs
     * first, `singletonIf` sees the binding and leaves it alone. Using
     * `singleton` here — or `singletonIf` there — would make the result
     * depend on provider order, which is not something a consumer controls.
     */
    private function bindEmailListFallbacks(): void
    {
        $this->app->singletonIf(DisposableDomainList::class, BundledDisposableDomainList::class);
        $this->app->singletonIf(RoleAccountList::class, BundledRoleAccountList::class);
        $this->app->singletonIf(DnsResolver::class, CachedDnsResolver::class);
    }

    /**
     * Bind the bundled ISO datasets, with the same `singletonIf` asymmetry as
     * the email lists: an application (or laranail/atlas) that binds its own
     * dataset unconditionally wins regardless of provider order.
     */
    private function bindI18nDatasetFallbacks(): void
    {
        $this->app->singletonIf(CountryDataset::class, BundledCountryDataset::class);
        $this->app->singletonIf(CurrencyDataset::class, BundledCurrencyDataset::class);
        $this->app->singletonIf(LanguageDataset::class, BundledLanguageDataset::class);
        $this->app->singletonIf(CardBrandCatalogue::class, BundledCardBrandCatalogue::class);
        $this->app->singletonIf(ReservedUsernameList::class, DefaultReservedUsernameList::class);
    }

    public function bootingPackage(): void
    {
        $this->applyBatchLimit();
        $this->registerRuleAliases();

        if ($this->app->runningInConsole()) {
            $this->publishes(
                [$this->configPath() => config_path('laranail-validation.php')],
                $this->package->getNamespacedPublishTag('config'),
            );

            $this->commands([
                RulesCommand::class,
                DoctorCommand::class,
                BenchmarkCommand::class,
            ]);
        }
    }

    /**
     * Boot-time only. BatchDatabaseChecker caps how many values a single
     * exists/unique group may carry; mutating it per-request is unsafe under
     * Octane, which is why it is read once here rather than at call time.
     */
    private function applyBatchLimit(): void
    {
        $limit = config('laranail.validation.batch.max_values_per_group');

        if (is_int($limit) && $limit > 0) {
            BatchDatabaseChecker::$maxValuesPerGroup = $limit;
        }
    }

    /**
     * Register the extended rule library as string rules — opt-in, prefixed.
     *
     * Off by default because the validator's extension map is a flat,
     * host-owned registry where the last writer silently wins; a library that
     * claimed 38 names in it unasked would be exactly the collision hazard the
     * naming convention exists to prevent. Prefixed even when enabled, and the
     * prefix is configurable so an application that already owns a name can
     * move ours rather than fight it.
     *
     * Registered through `Validator::extend` rather than package-tools'
     * `hasValidationRule()`, which instantiates the rule class with no
     * arguments and drops the rule's parameters — that would leave every
     * parameterised alias (`laranail_postal_code:US`) silently unparameterised.
     *
     * Execution goes through Laravel's own `InvokableValidationRule`, so a
     * rule reached by alias sees the same DataAware/ValidatorAware wiring and
     * message translation it gets when used as an object.
     */
    private function registerRuleAliases(): void
    {
        if (config('laranail.validation.aliases.enabled') !== true) {
            return;
        }

        $prefix = config('laranail.validation.aliases.prefix');
        $prefix = is_string($prefix) ? $prefix : 'laranail_';

        // Consumer-registered aliases ride the same extension mechanism.
        // Their names are used AS GIVEN — the consumer owns vendor-scoping
        // them (`acme_thing`), per the org naming convention: the extension
        // map is a flat registry where the last writer silently wins.
        $factories = RuleAliases::map();

        foreach ($this->app->make(RuleRegistrar::class)->aliasFactories() as $alias => $factory) {
            $factories[$alias] = $factory;
        }

        foreach ($factories as $suffix => $factory) {
            $alias = array_key_exists($suffix, RuleAliases::map()) ? $prefix . $suffix : $suffix;

            // The key Laravel will look the message up under. It studly-cases
            // the rule string to dispatch and snake-cases it again to format,
            // so the round trip — not the alias as written — is what matches.
            $messageKey = Str::snake(Str::studly($alias));

            Validator::extend(
                $alias,
                static function (string $attribute, mixed $value, array $parameters, ValidationValidator $validator) use ($factory, $messageKey): bool {
                    $rule = InvokableValidationRule::make($factory(self::stringParameters($parameters)));
                    $rule->setValidator($validator);

                    if ($rule->passes($attribute, $value)) {
                        return true;
                    }

                    // The rule already produced a translated message. Without
                    // handing it over, the failure falls back to Laravel's
                    // `validation.laranail_iban` key, which no locale defines,
                    // and the user is shown the raw key.
                    //
                    // message() returns a LIST — a rule may call $fail more
                    // than once — while a custom message must be a string. An
                    // extension can only register one failure per attribute,
                    // so the messages are joined rather than silently reduced
                    // to the first.
                    $messages = (array) $rule->message();

                    if ($messages !== []) {
                        $validator->setCustomMessages([
                            $attribute . '.' . $messageKey => implode(' ', self::stringParameters($messages)),
                        ]);
                    }

                    return false;
                },
            );
        }
    }

    /**
     * Narrow the validator's loosely-typed parameter array to a string list.
     *
     * `Validator::extend` types the third argument as a bare `array`, so the
     * factories cannot assume anything about it. Non-scalars are dropped
     * rather than coerced: `(string) []` is a fatal, and a rule string cannot
     * carry an array parameter in the first place.
     *
     * @param  array<array-key, mixed>  $parameters
     * @return list<string>
     */
    private static function stringParameters(array $parameters): array
    {
        $strings = [];

        foreach ($parameters as $parameter) {
            if (is_scalar($parameter) || $parameter instanceof Stringable) {
                $strings[] = (string) $parameter;
            }
        }

        return $strings;
    }

    private function configPath(): string
    {
        return dirname(__DIR__) . '/config/laranail-validation.php';
    }
}
