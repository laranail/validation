<?php declare(strict_types=1);

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Facades\Validator;
use Simtabi\Laranail\Validation\Contracts\ClientCheckable;
use Simtabi\Laranail\Validation\Rules\Banking\Iban;
use Simtabi\Laranail\Validation\Rules\Telecom\Phone;
use Simtabi\Laranail\Validation\Rules\Text\Slug;
use Simtabi\Laranail\Validation\RuleSet;
use Simtabi\Laranail\Validation\Support\RuleRegistrar;
use Simtabi\Laranail\Validation\Tests\Fixtures\Registry\EvenNumber;
use Simtabi\Laranail\Validation\ValidationServiceProvider;

/**
 * The §5.2.2 registry — one place the alias map, the console command, the
 * docs tooling and a consumer's own provider all see the same rule set.
 * The package's own rules are discovered, not hand-listed; a consumer
 * registers additions without touching core.
 */
it('discovers every shipped rule class', function (): void {
    $classes = resolve(RuleRegistrar::class)->classes();

    expect($classes)->toContain(Iban::class)
        ->toContain(Phone::class)
        ->and(count($classes))->toBe(79);
});

it('accepts a consumer registration with an alias factory', function (): void {
    resolve(RuleRegistrar::class)->register(
        EvenNumber::class,
        alias: 'acme_even',
        factory: fn (array $parameters): EvenNumber => new EvenNumber(),
    );

    expect(resolve(RuleRegistrar::class)->classes())->toContain(EvenNumber::class);

    // Re-registering the provider wires the custom alias into the live
    // validator registry, beside the package's own prefixed aliases.
    config()->set('laranail.validation.aliases.enabled', true);
    app()->register(ValidationServiceProvider::class, force: true);

    expect(Validator::make(['n' => 4], ['n' => ['acme_even']])->passes())->toBeTrue()
        ->and(Validator::make(['n' => 3], ['n' => ['acme_even']])->passes())->toBeFalse();
});

it('merges rule classes tagged into the container', function (): void {
    app()->bind(EvenNumber::class);
    app()->tag([EvenNumber::class], 'laranail.validation.rules');

    expect(resolve(RuleRegistrar::class)->classes())->toContain(EvenNumber::class);
});

it('reports which registered rules advertise a browser form', function (): void {
    $clientCheckable = resolve(RuleRegistrar::class)->clientCheckable();

    expect($clientCheckable)->toContain(Slug::class)
        ->not->toContain(Iban::class)
        ->and(count($clientCheckable))->toBe(16);
});

it('is one singleton, so every consumer sees the same registry', function (): void {
    resolve(RuleRegistrar::class)->register(EvenNumber::class);

    expect(resolve(RuleRegistrar::class)->classes())->toContain(EvenNumber::class)
        ->and(in_array(EvenNumber::class, resolve(RuleRegistrar::class)->classes(), true))->toBeTrue();
});

it('implementing ClientCheckable is the whole browser opt-in for a custom rule', function (): void {
    // No registry call needed for the wire: the exporter reads the
    // interface off the rule object itself.
    $rule = new class implements ClientCheckable, ValidationRule {
        public function validate(string $attribute, mixed $value, Closure $fail): void {}

        public function clientRules(): array
        {
            return [['rule' => 'integer', 'params' => []]];
        }
    };

    $schema = RuleSet::from(['n' => [$rule]])->toSchema();

    expect(array_column($schema['fields']['n']['client'], 'rule'))->toBe(['integer']);
});
