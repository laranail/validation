<?php declare(strict_types=1);

namespace Simtabi\Laranail\Validation;

use Simtabi\Laranail\Package\Tools\Package;
use Simtabi\Laranail\Package\Tools\Providers\PackageServiceProvider;

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
        // Views and translations are deliberately not registered here yet.
        // This package ships neither, and package-tools' current tagged
        // release derives a `laranail/validation::` translation namespace
        // (slash) rather than the `laranail-validation::` the convention
        // requires. Wire them when the rule library's lang files land, once
        // that fix is tagged.
        $package->name('laranail/validation');
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
    }

    /**
     * Translations are registered explicitly, for the same reason as config.
     *
     * package-tools' `hasTranslations()` derives the namespace as
     * `{vendor}/{package}` — with a slash — on its currently tagged release.
     * That nests published files one level deeper than `lang/vendor/{namespace}`
     * expects, and it is not the `laranail-validation` the convention requires.
     * Calling loadTranslationsFrom() directly pins the correct namespace
     * regardless of which package-tools version resolves.
     */
    public function packageRegistered(): void
    {
        $this->loadTranslationsFrom($this->langPath(), 'laranail-validation');
    }

    public function bootingPackage(): void
    {
        $this->applyBatchLimit();

        if ($this->app->runningInConsole()) {
            $this->publishes(
                [$this->configPath() => config_path('laranail-validation.php')],
                $this->package->getNamespacedPublishTag('config'),
            );

            $this->publishes(
                [$this->langPath() => $this->app->langPath('vendor/laranail-validation')],
                $this->package->getNamespacedPublishTag('translations'),
            );
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

    private function configPath(): string
    {
        return dirname(__DIR__) . '/config/laranail-validation.php';
    }

    private function langPath(): string
    {
        return dirname(__DIR__) . '/lang';
    }
}
