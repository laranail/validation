<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Validator;
use Simtabi\Laranail\Validation\FluentRule;
use Simtabi\Laranail\Validation\Builder\Nodes\EmailRule;
use Simtabi\Laranail\Validation\Rules\Email\NotRoleEmail;
use Simtabi\Laranail\Validation\Rules\Email\EmailDomainIs;
use Simtabi\Laranail\Validation\Rules\Email\EmailDomainIsNot;
use Simtabi\Laranail\Validation\Rules\Email\NotDisposableEmail;

/**
 * The email node's own rule methods.
 *
 * These are the first rules from the extended library to get a named builder
 * method, so the tests also pin the two things that makes possible: the node
 * must compile to array form (a rule object is not Stringable, and the old
 * compiledRules() override would have stringified the set and dropped it),
 * and the rules must resolve their list from the container rather than
 * requiring the caller to hand one over.
 */
it('compiles to array form and keeps the rule object', function (): void {
    // Not a smoke test: EmailRule used to override compiledRules() and
    // pipe-join unconditionally, which would silently drop every one of these.
    $compiled = FluentRule::email()->notDisposable()->compiledRules();

    expect($compiled)->toBeArray()
        ->and($compiled)->toContain('email')
        ->and(rulesOfType(compiledArray($compiled), NotDisposableEmail::class))->toHaveCount(1);
});

it('exposes each library rule under its own method', function (Closure $build, string $class): void {
    $compiled = compiledArray($build(FluentRule::email())->compiledRules());

    $matching = array_filter($compiled, static fn (object|string $r): bool => is_object($r) && $r::class === $class);

    expect($matching)->toHaveCount(1);
})->with([
    'notDisposable' => [fn (EmailRule $e): EmailRule => $e->notDisposable(), NotDisposableEmail::class],
    'notRole'       => [fn (EmailRule $e): EmailRule => $e->notRole(), NotRoleEmail::class],
    'domainIs'      => [fn (EmailRule $e): EmailRule => $e->domainIs(['example.com']), EmailDomainIs::class],
    'domainIsNot'   => [fn (EmailRule $e): EmailRule => $e->domainIsNot(['spam.test']), EmailDomainIsNot::class],
]);

it('accepts a single domain as a string', function (): void {
    $rules = FluentRule::email()->domainIs('example.com')->compiledRules();

    expect(Validator::make(['e' => 'a@example.com'], ['e' => $rules])->passes())->toBeTrue()
        ->and(Validator::make(['e' => 'a@other.test'], ['e' => $rules])->passes())->toBeFalse();
});

it('validates end to end through the builder', function (): void {
    $rules = FluentRule::email()->required()->notDisposable()->notRole()->compiledRules();

    expect(Validator::make(['e' => 'alice@example.com'], ['e' => $rules])->passes())->toBeTrue()
        ->and(Validator::make(['e' => 'alice@mailinator.com'], ['e' => $rules])->passes())->toBeFalse()
        ->and(Validator::make(['e' => 'info@example.com'], ['e' => $rules])->passes())->toBeFalse();
});

it('resolves the list lazily, so a rule set can be built before the container has one', function (): void {
    // The constructor must not touch the container: a rule set built in a
    // queued job or a data provider is constructed long before validation.
    $rule = new NotDisposableEmail;

    expect((fn (): mixed => $this->domains)->call($rule))->toBeNull()
        ->and(ruleAccepts($rule, 'alice@mailinator.com'))
        ->toBeFalse();
});

it('combines with the native email modes rather than replacing them', function (): void {
    $compiled = FluentRule::email()->strict()->domainIs(['example.com'])->compiledRules();

    $rules = compiledArray($compiled);

    expect(rulesOfType($rules, EmailDomainIs::class))->toHaveCount(1)
        ->and(implode(' ', array_filter($rules, is_string(...))))->toContain('email');
});
