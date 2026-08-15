<?php declare(strict_types=1);

use Illuminate\Contracts\Validation\Factory as ValidationFactory;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Auth\User;
use Illuminate\Support\Facades\Validator;
use Simtabi\Laranail\Validation\Support\RuleAliases;
use Simtabi\Laranail\Validation\ValidationServiceProvider;

/**
 * Rule families whose aliases ship with their own package rather than here.
 *
 * `Telecom` is the phone family: it depends on laranail/phone, is optional,
 * and registers alongside that dependency. Listing it here rather than in
 * RuleAliases::UNALIASED keeps this package's committed source free of a
 * reference to an optional family.
 */
const EXTERNALLY_ALIASED_NAMESPACES = ['Telecom'];

/**
 * Parameters an alias cannot be constructed without.
 *
 * The database aliases reject a missing or non-model class name rather than
 * deferring the failure to validation time, so the guards below have to hand
 * them something real.
 *
 * @return array<string, list<string>>
 */
function sampleParameters(): array
{
    return [
        'models_exist' => [User::class],
        'authorized' => ['do-something', User::class],
        'parity' => ['even'],
        'vendor_identifier' => ['aws_region'],
        'national_identifier' => ['nl'],
    ];
}

function enableAliases(string $prefix = 'laranail_'): void
{
    config()->set('laranail.validation.aliases.enabled', true);
    config()->set('laranail.validation.aliases.prefix', $prefix);

    app()->register(ValidationServiceProvider::class, force: true);
}

/** @return list<class-string<ValidationRule>> */
function ruleClasses(): array
{
    $classes = [];

    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator(dirname(__DIR__) . '/src/Rules')) as $file) {
        if (! $file instanceof SplFileInfo || $file->getExtension() !== 'php') {
            continue;
        }

        $relative = str_replace([dirname(__DIR__) . '/src/Rules/', '/', '.php'], ['', '\\', ''], $file->getPathname());
        $class = 'Simtabi\\Laranail\\Validation\\Rules\\' . $relative;

        if (class_exists($class) && is_a($class, ValidationRule::class, true)) {
            $classes[] = $class;
        }
    }

    return $classes;
}

// =========================================================================
// The opt-in gate
// =========================================================================

it('registers nothing at all by default', function (): void {
    // The validator's extension map is host-owned and last-writer-wins, so
    // claiming 37 names uninvited is the collision the convention forbids.
    $property = new ReflectionProperty(resolve(ValidationFactory::class), 'extensions');

    expect($property->getValue(resolve(ValidationFactory::class)))->toBeEmpty();
});

it('registers every alias once enabled, all prefixed', function (): void {
    enableAliases();

    $property = new ReflectionProperty(resolve(ValidationFactory::class), 'extensions');
    /** @var array<string, mixed> $extensions */
    $extensions = $property->getValue(resolve(ValidationFactory::class));

    expect(array_keys($extensions))->toHaveSameSize(RuleAliases::map())
        ->and(array_filter(array_keys($extensions), static fn (string $n): bool => ! str_starts_with($n, 'laranail_')))
        ->toBeEmpty();
});

it('honours a configured prefix, so an application can move ours out of the way', function (): void {
    enableAliases('acme_');

    expect(Validator::make(['a' => 'not-an-iban'], ['a' => ['acme_iban']])->passes())->toBeFalse();
});

// =========================================================================
// Execution
// =========================================================================

it('runs a parameterless rule through its alias', function (): void {
    enableAliases();

    expect(Validator::make(['a' => 'DE89370400440532013000'], ['a' => ['laranail_iban']])->passes())->toBeTrue()
        ->and(Validator::make(['a' => 'DE89370400440532013001'], ['a' => ['laranail_iban']])->passes())->toBeFalse();
});

it('shows the rule’s own message, not the raw key', function (): void {
    // Without handing the message over, Laravel falls back to
    // `validation.laranail_iban`, which no locale defines, and the user is
    // shown that key verbatim. This is the assertion that catches it.
    enableAliases();

    $error = Validator::make(['a' => 'nope'], ['a' => ['laranail_iban']])->errors()->first('a');

    expect($error)->not->toBeEmpty()
        ->not->toContain('validation.')
        ->not->toContain('laranail_iban');
});

it('forwards parameters, which is the whole reason for a custom registrar', function (): void {
    // package-tools' hasValidationRule() constructs the rule with no
    // arguments, so every one of these would validate against the wrong thing.
    enableAliases();

    expect(Validator::make(['a' => '90210'], ['a' => ['laranail_postal_code:US']])->passes())->toBeTrue()
        ->and(Validator::make(['a' => '90210'], ['a' => ['laranail_postal_code:GB']])->passes())->toBeFalse()
        ->and(Validator::make(['a' => 'camelCase'], ['a' => ['laranail_case_style:camel']])->passes())->toBeTrue()
        ->and(Validator::make(['a' => 'camelCase'], ['a' => ['laranail_case_style:snake']])->passes())->toBeFalse();
});

it('accepts a multi-value parameter list', function (): void {
    enableAliases();

    expect(Validator::make(['a' => 'a@example.com'], ['a' => ['laranail_email_domain_is:example.com,*.example.com']])->passes())->toBeTrue()
        ->and(Validator::make(['a' => 'a@mail.example.com'], ['a' => ['laranail_email_domain_is:example.com,*.example.com']])->passes())->toBeTrue()
        ->and(Validator::make(['a' => 'a@other.test'], ['a' => ['laranail_email_domain_is:example.com,*.example.com']])->passes())->toBeFalse();
});

it('keeps DataAwareRule working through the alias', function (): void {
    // PostalCode reads the country from a sibling field. The alias path runs
    // through InvokableValidationRule precisely so setData() still happens.
    enableAliases();

    // `@country` names the sibling to read; a bare parameter would be
    // ambiguous between an ISO code and a field of the same name.
    $rules = ['country' => 'required', 'zip' => 'laranail_postal_code:@country'];

    expect(Validator::make(['country' => 'US', 'zip' => '90210'], $rules)->passes())->toBeTrue()
        ->and(Validator::make(['country' => 'GB', 'zip' => '90210'], $rules)->passes())->toBeFalse();
});

it('resolves a container-backed rule through the alias', function (): void {
    enableAliases();

    expect(Validator::make(['a' => 'alice@mailinator.com'], ['a' => ['laranail_not_disposable_email']])->passes())->toBeFalse()
        ->and(Validator::make(['a' => 'alice@example.com'], ['a' => ['laranail_not_disposable_email']])->passes())->toBeTrue();
});

// =========================================================================
// Drift guards — both directions
// =========================================================================

it('maps only rule classes that exist', function (): void {
    foreach (RuleAliases::map() as $suffix => $factory) {
        expect($factory(sampleParameters()[$suffix] ?? []))->toBeInstanceOf(ValidationRule::class, "alias {$suffix}");
    }
});

it('behaves exactly like the rule built with no arguments', function (): void {
    // The docblock promises "an alias with no parameter behaves exactly like
    // `new Rule()`". Restating a rule's defaults in the factory is how that
    // breaks: laranail_username capped at 30 while Username's own default was
    // 32, so the alias quietly validated something different.
    $drifted = [];

    foreach (RuleAliases::map() as $suffix => $factory) {
        $viaAlias = $factory(sampleParameters()[$suffix] ?? []);
        $class = $viaAlias::class;

        // Only rules constructible with no arguments have a "default" to
        // compare against; the rest are told what to validate.
        if (new ReflectionClass($class)->getConstructor()?->getNumberOfRequiredParameters() > 0) {
            continue;
        }

        $direct = new $class();

        if (print_r($viaAlias, true) !== print_r($direct, true)) {
            $drifted[] = $suffix;
        }
    }

    expect($drifted)->toBeEmpty('aliases whose defaults differ from the rule: ' . implode(', ', $drifted));
});

it('refuses a database alias whose model parameter is not a model', function (): void {
    // Better here than at validation time, where it surfaces on a user request
    // as a class-not-found with no mention of the rule that caused it.
    $factory = RuleAliases::map()['models_exist'];

    expect(static fn (): mixed => $factory(['NotAModel']))
        ->toThrow(InvalidArgumentException::class, 'is not an Eloquent model');
});

it('leaves no rule without an alias or a stated reason', function (): void {
    // The guard that makes the alias surface trustworthy: a rule added later
    // cannot quietly ship without a string form, and a rule deliberately left
    // out has to say so in UNALIASED rather than just be forgotten.
    $aliased = [];

    foreach (RuleAliases::map() as $suffix => $factory) {
        $aliased[] = $factory(sampleParameters()[$suffix] ?? [])::class;
    }

    $missing = [];

    foreach (ruleClasses() as $class) {
        $family = explode('\\', str_replace('Simtabi\\Laranail\\Validation\\Rules\\', '', $class))[0];

        if (in_array($family, EXTERNALLY_ALIASED_NAMESPACES, true)) {
            continue;
        }

        if (! in_array($class, $aliased, true) && ! in_array($class, RuleAliases::UNALIASED, true)) {
            $missing[] = $class;
        }
    }

    expect($missing)->toBeEmpty('rules with no alias and no stated reason: ' . implode(', ', $missing));
});
