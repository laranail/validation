<?php declare(strict_types=1);

namespace Simtabi\Laranail\Validation\Tests\Fixtures\Consumer;

use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Simtabi\Laranail\Validation\Contracts\TermList;
use Simtabi\Laranail\Validation\Events\ValidationFailed;
use Simtabi\Laranail\Validation\Support\InlineTermList;
use Simtabi\Laranail\Validation\Support\RuleRegistrar;
use Simtabi\Laranail\Validation\Tests\Fixtures\Registry\EvenNumber;

/**
 * The Phase-1 exit criterion, as a working example: an application (or
 * sibling package) provider that adds a rule with an alias, binds a
 * dataset contract, and listens on the event seam — all without touching
 * a single core file.
 */
final class AcmeServiceProvider extends ServiceProvider
{
    /** @var list<string> Fields whose failures the listener observed. */
    public static array $observedFailures = [];

    public function register(): void
    {
        // A dataset contract, replaced with the application's own data.
        $this->app->singleton(TermList::class, fn (): TermList => new InlineTermList(
            terms: ['acmeforbidden'],
        ));
    }

    public function boot(): void
    {
        // A custom rule with a vendor-scoped alias.
        $this->app->make(RuleRegistrar::class)->register(
            EvenNumber::class,
            alias: 'acme_even',
            factory: fn (array $parameters): EvenNumber => new EvenNumber(),
        );

        // The notification seam: route failures wherever the app's
        // monitoring policy wants them.
        Event::listen(ValidationFailed::class, function (ValidationFailed $event): void {
            self::$observedFailures = array_values([...self::$observedFailures, ...$event->errors->keys()]);
        });
    }
}
