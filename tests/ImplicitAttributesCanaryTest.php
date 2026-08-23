<?php declare(strict_types=1);

use Illuminate\Support\Facades\Validator as ValidatorFacade;
use Illuminate\Validation\Validator;

/**
 * Canary for the package's one reach into Laravel's internals.
 *
 * `RuleSet::validateStandard()` and `HasFluentRules` write the PRIVATE
 * `Validator::$implicitAttributes` via reflection, because the package
 * expands wildcards itself and Laravel offers no public way to hand the
 * expansion back. A framework release could rename the property, change
 * its shape, or stop consulting it — and reflection makes every one of
 * those silent everywhere except here.
 *
 * The nightly 13.x-dev job runs this against tomorrow's Laravel.
 */
it('finds the private property the wildcard path writes via reflection', function (): void {
    $property = new ReflectionProperty(Validator::class, 'implicitAttributes');

    // Untyped in the framework; the shape contract is its array default —
    // the package writes array<pattern, list<expanded-attribute>>.
    expect($property->getDefaultValue())->toBe([]);
});

it('is still consulted for attribute resolution when written — the write is load-bearing', function (): void {
    // Reproduce exactly what the package does: build a validator over
    // ALREADY-EXPANDED wildcard attributes, write the expansion map through
    // the same reflection expression, and watch a custom :attribute name
    // declared for the PATTERN reach the message of a concrete row. If
    // Laravel stops consulting the property, the message falls back to the
    // leaf-derived name and this fails — the canary's job.
    $validator = ValidatorFacade::make(
        ['items' => [['email' => 'not-an-email']]],
        ['items.0.email' => 'email'],
        [],
        ['items.*.email' => 'customer email address'],
    );

    new ReflectionProperty(Validator::class, 'implicitAttributes')
        ->setValue($validator, ['items.*.email' => ['items.0.email']]);

    expect($validator->passes())->toBeFalse()
        ->and($validator->errors()->first('items.0.email'))->toContain('customer email address');
});
