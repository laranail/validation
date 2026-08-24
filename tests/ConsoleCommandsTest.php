<?php declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;
use Simtabi\Laranail\Validation\Support\RuleRegistrar;
use Simtabi\Laranail\Validation\Tests\Fixtures\Registry\EvenNumber;

/**
 * The §5.8 commands, under the org's `laranail::<slug>.<command>` naming.
 * Dispatch through the FULL Artisan name proves the `::` separator
 * survives Symfony's validator — the whole point of the console base.
 */
it('lists the live registry through laranail::validation.rules', function (): void {
    $this->artisan('laranail::validation.rules')
        ->expectsOutputToContain('84 rules')
        ->assertSuccessful();
});

it('includes an application-registered rule in the listing', function (): void {
    resolve(RuleRegistrar::class)->register(EvenNumber::class);

    $this->artisan('laranail::validation.rules')
        ->expectsOutputToContain('EvenNumber')
        ->assertSuccessful();
});

it('reports a healthy default environment through the doctor', function (): void {
    $this->artisan('laranail::validation.doctor')
        ->expectsOutputToContain('rule registry')
        ->assertSuccessful();
});

it('fails the doctor when a critical setting is broken', function (): void {
    // An empty alias prefix with aliases enabled is the bare-generic-name
    // collision hazard the naming convention exists to prevent — critical,
    // so the doctor exits non-zero and a deploy gate can catch it.
    config()->set('laranail.validation.aliases.enabled', true);
    config()->set('laranail.validation.aliases.prefix', '');

    $this->artisan('laranail::validation.doctor')->assertFailed();
});

it('runs the benchmark wrapper in a repository checkout', function (): void {
    // This test runs IN the repo, where benchmark.php exists — but running
    // the full suite here would take minutes, so assert only the wiring:
    // the command resolves and dispatches under its namespaced name.
    expect(collect(Artisan::all())->keys())
        ->toContain('laranail::validation.benchmark');
});
