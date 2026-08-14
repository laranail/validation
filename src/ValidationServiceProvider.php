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
    }

    public function bootingPackage(): void
    {
        $this->applyBatchLimit();

        if ($this->app->runningInConsole()) {
            $this->publishes(
                [$this->configPath() => config_path('laranail-validation.php')],
                $this->package->getNamespacedPublishTag('config'),
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
}
