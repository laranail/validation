<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Simtabi\Laranail\Validation\Contracts\TermList;
use Simtabi\Laranail\Validation\Providers\ValidationServiceProvider;
use Simtabi\Laranail\Validation\Rules\Profanity\NoProfanity;
use Simtabi\Laranail\Validation\RuleSet;
use Simtabi\Laranail\Validation\Tests\Fixtures\Consumer\AcmeServiceProvider;

/**
 * The Phase-1 exit criterion, end to end: one consumer provider adds a
 * rule + alias, binds a dataset contract, and listens on the event seam —
 * and every extension works through the package's ordinary surfaces, with
 * no core file touched.
 */
beforeEach(function (): void {
    AcmeServiceProvider::$observedFailures = [];
    app()->register(AcmeServiceProvider::class);
});

it("validates through the consumer rule's vendor-scoped alias", function (): void {
    config()->set('laranail.validation.aliases.enabled', true);
    app()->register(ValidationServiceProvider::class, force: true);

    expect(Validator::make(['n' => 4], ['n' => ['acme_even']])->passes())->toBeTrue()
        ->and(Validator::make(['n' => 3], ['n' => ['acme_even']])->passes())->toBeFalse();
});

it('feeds the consumer dataset through the contract', function (): void {
    $rule = new NoProfanity(resolve(TermList::class));

    expect(ruleAccepts($rule, 'a clean sentence'))->toBeTrue()
        ->and(ruleAccepts($rule, 'totally acmeforbidden content'))->toBeFalse();
});

it('routes failures to the consumer listener', function (): void {
    expect(fn () => RuleSet::from(['email' => 'required|email'])->validate([]))
        ->toThrow(ValidationException::class)
        ->and(AcmeServiceProvider::$observedFailures)->toContain('email');
});
