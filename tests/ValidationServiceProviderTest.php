<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Validator;
use Simtabi\Laranail\Validation\BatchDatabaseChecker;
use Simtabi\Laranail\Validation\Contracts\Email\DnsResolver;
use Simtabi\Laranail\Validation\Contracts\Email\RoleAccountList;
use Simtabi\Laranail\Validation\Providers\ValidationServiceProvider;
use Simtabi\Laranail\Validation\Contracts\Email\DisposableDomainList;
use Simtabi\Laranail\Validation\Support\Email\BundledDisposableDomainList;

/**
 * The provider's own behaviour, tested against the BOOTED application rather
 * than by reading the registration code. It was the least-tested class
 * relative to its subtlety: singletonIf ordering against laranail/email, the
 * snake/studly message-key round trip an alias failure depends on, the
 * boot-only batch limit, and the deliberately flat config key.
 */
function bootAliasProvider(string $prefix = 'laranail_'): void
{
    config()->set('laranail.validation.aliases.enabled', true);
    config()->set('laranail.validation.aliases.prefix', $prefix);

    app()->register(ValidationServiceProvider::class, force: true);
}

it('merges config under the flat laranail.validation key', function (): void {
    // hasConfigFile() would have produced laranail.validation.laranail-validation;
    // the explicit mergeConfigFrom is what keeps the key flat. Read it live.
    expect(config('laranail.validation.aliases.enabled'))->toBeFalse()
        ->and(config('laranail.validation.dns.ttl'))->toBe(3600)
        ->and(config('laranail.validation.batch.max_values_per_group'))->toBe(10_000);
});

it('binds the bundled email fallbacks only when nothing else has', function (): void {
    // The default: bundled implementations answer the contracts.
    expect(resolve(DisposableDomainList::class))->toBeInstanceOf(BundledDisposableDomainList::class);

    // The laranail/email scenario: a consumer (or sibling package) bound the
    // contract FIRST. Re-registering this provider must leave it alone —
    // singletonIf, not singleton, is what makes the outcome order-free.
    $replacement = new class implements DisposableDomainList
    {
        public function contains(string $domain): bool
        {
            return $domain === 'blocked.test';
        }
    };

    app()->singleton(DisposableDomainList::class, fn () => $replacement);
    app()->register(ValidationServiceProvider::class, force: true);

    expect(resolve(DisposableDomainList::class))->toBe($replacement)
        ->and(resolve(RoleAccountList::class))->not->toBeNull()
        ->and(resolve(DnsResolver::class))->not->toBeNull();
});

it('applies the batch limit at boot, and ignores an unusable value', function (): void {
    $original = BatchDatabaseChecker::$maxValuesPerGroup;

    try {
        config()->set('laranail.validation.batch.max_values_per_group', 250);
        app()->register(ValidationServiceProvider::class, force: true);

        expect(BatchDatabaseChecker::$maxValuesPerGroup)->toBe(250);

        // A zero, negative, or non-int value must not reach the static —
        // a limit of 0 would refuse every batch.
        config()->set('laranail.validation.batch.max_values_per_group', 0);
        app()->register(ValidationServiceProvider::class, force: true);

        expect(BatchDatabaseChecker::$maxValuesPerGroup)->toBe(250);
    } finally {
        BatchDatabaseChecker::$maxValuesPerGroup = $original;
    }
});

it('lands an alias failure on the snake/studly round-trip message key', function (): void {
    // Laravel studly-cases a rule string to dispatch and snake-cases it back
    // to look the message up, so the key the closure registers must be that
    // ROUND TRIP of the alias, not the alias as written. Get it wrong and
    // the user sees the raw `validation.laranail_iban` key. Proven with a
    // camelCase prefix, whose round trip is NOT the identity:
    // `myApp_iban` → studly `MyAppIban` → snake `my_app_iban`.
    bootAliasProvider('myApp_');

    $validator = Validator::make(['account' => 'not-an-iban'], ['account' => ['myApp_iban']]);

    expect($validator->fails())->toBeTrue()
        ->and($validator->errors()->first('account'))
        ->not->toContain('validation.')
        ->and($validator->errors()->first('account'))->not->toBeEmpty();
});

it('drops non-scalar alias parameters rather than fataling on a cast', function (): void {
    bootAliasProvider();

    // A hand-built validator can smuggle an array into the parameter list;
    // `(string) []` is fatal, so the narrowing must drop it instead.
    $validator = Validator::make(['code' => 'ABC'], ['code' => ['laranail_parity:even']]);

    expect($validator->fails())->toBeTrue();
});
