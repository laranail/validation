<?php

declare(strict_types=1);

use Illuminate\Support\MessageBag;
use Simtabi\Laranail\Validation\Check;
use Simtabi\Laranail\Validation\RuleSet;
use Simtabi\Laranail\Validation\Validation;
use Illuminate\Validation\ValidationException;
use Simtabi\Laranail\Validation\Rules\Banking\Iban;

/**
 * §6.10 ergonomics: Check for one-off boolean guards without building a
 * validator, Validation::fake() for consumer tests, and toSchema()'s
 * fail-fast guard.
 */

// ---------------------------------------------------------------------------
// Check — explicit statics, no magic
// ---------------------------------------------------------------------------

it('answers one-off guards as booleans', function (): void {
    expect(Check::iban('DE89370400440532013000'))->toBeTrue()
        ->and(Check::iban('DE00 not an iban'))->toBeFalse()
        ->and(Check::slug('my-slug'))->toBeTrue()
        ->and(Check::slug("my-slug\n"))->toBeFalse()
        ->and(Check::luhn('79927398713'))->toBeTrue()
        ->and(Check::semVer('1.2.3'))->toBeTrue()
        ->and(Check::semVer('1.2'))->toBeFalse()
        ->and(Check::username('alice'))->toBeTrue()
        ->and(Check::username('admin'))->toBeFalse()
        ->and(Check::latitude('45.5'))->toBeTrue()
        ->and(Check::latitude('91'))->toBeFalse();
});

it('answers regex guards with the same pattern contract as matches()', function (): void {
    expect(Check::regex('123-Ab', '^\d{3}-[A-Za-z]{2}$'))->toBeTrue()
        ->and(Check::regex("123-Ab\n", '^\d{3}-[A-Za-z]{2}$'))->toBeFalse()
        ->and(Check::regex('ABC', '/^[a-z]+$/i'))->toBeTrue();
});

it('runs any rule object through the generic escape hatch', function (): void {
    expect(Check::rule(new Iban, 'DE89370400440532013000'))->toBeTrue()
        ->and(Check::rule(new Iban, 'nope'))->toBeFalse();
});

// ---------------------------------------------------------------------------
// Validation::fake()
// ---------------------------------------------------------------------------

it('records passing runs for assertion', function (): void {
    $fake = Validation::fake();

    RuleSet::from(['name' => 'required|string'])->validate(['name' => 'Ada']);

    $fake->assertValidated();
    $fake->assertValidated(fn (array $validated): bool => $validated === ['name' => 'Ada']);
    $fake->assertNothingFailed();
});

it('records failing runs for assertion', function (): void {
    $fake = Validation::fake();

    expect(fn () => RuleSet::from(['name' => 'required'])->validate([]))
        ->toThrow(ValidationException::class);

    $fake->assertFailed();
    $fake->assertFailed(fn (MessageBag $errors): bool => $errors->has('name'));
});

it('asserts the silence too', function (): void {
    $fake = Validation::fake();

    $fake->assertNothingValidated();
    $fake->assertNothingFailed();
});

// ---------------------------------------------------------------------------
// toSchema() guard
// ---------------------------------------------------------------------------

it('toSchema() exports the wire schema in one call', function (): void {
    $schema = RuleSet::from([
        'email' => 'required|email|unique:users',
        'age'   => 'nullable|integer|min:18',
    ])->toSchema(attributes: ['email' => 'work email']);

    expect($schema['version'])->toBe(1)
        ->and(array_column($schema['fields']['email']['client'], 'rule'))->toBe(['required', 'email'])
        ->and($schema['fields']['email']['server'])->toBe(['unique'])
        ->and($schema['fields']['email']['attribute'])->toBe('work email')
        ->and(array_column($schema['fields']['age']['client'], 'rule'))->toContain('integer');

    // The stripping guarantee holds through this surface too: no table name
    // on the wire.
    expect(json_encode($schema, JSON_THROW_ON_ERROR))->not->toContain('users');
});
