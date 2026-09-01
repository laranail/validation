<?php

declare(strict_types=1);

use Illuminate\Validation\Factory as ValidationFactory;
use Simtabi\Laranail\Validation\BatchDatabaseChecker;

// =========================================================================
// Every public name this package registers must carry the vendor AND the
// package slug. Laravel keeps config keys, view hints, translation
// namespaces and validator extensions in flat maps: a second package
// claiming the same key does not collide loudly, it replaces the first, and
// the damage surfaces far away as a missing view or the wrong rule running.
//
// These assertions read the LIVE registries rather than grepping the
// provider, so they still hold if the registration code is refactored.
// =========================================================================

it('registers its config under the vendor-scoped key', function (): void {
    expect(config('laranail.validation'))->toBeArray()
        ->and(config('laranail.validation.batch.max_values_per_group'))->toBeInt();
});

it('does not register a bare config key', function (): void {
    // `validation` is Laravel's own; `laranail` must not be flattened away.
    expect(config('validation'))->toBeNull()
        ->and(config('laranail-validation'))->toBeNull();
});

it('does not register any bare string rule alias', function (): void {
    // Aliases are opt-in and vendor-prefixed. With the default config nothing
    // generic may appear in the validator factory's extension map. Read the
    // real map by reflection — grepping the provider would not survive a
    // refactor of the registration code.
    $factory = resolve(ValidationFactory::class);

    $property = new ReflectionProperty($factory, 'extensions');
    /** @var array<string, mixed> $extensions */
    $extensions = $property->getValue($factory);

    $generic = array_values(array_filter(
        array_keys($extensions),
        static fn (string $name): bool => ! str_starts_with($name, 'laranail_'),
    ));

    expect($generic)->toBeEmpty(
        'bare validator extensions registered: '.implode(', ', $generic),
    );
});

it('applies the configured batch limit at boot', function (): void {
    expect(BatchDatabaseChecker::$maxValuesPerGroup)
        ->toBe(config('laranail.validation.batch.max_values_per_group'));
});
