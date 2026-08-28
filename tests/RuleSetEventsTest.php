<?php

declare(strict_types=1);

use Illuminate\Validation\Validator;
use Illuminate\Support\Facades\Event;
use Simtabi\Laranail\Validation\RuleSet;
use Simtabi\Laranail\Validation\FluentRule;
use Illuminate\Validation\ValidationException;
use Simtabi\Laranail\Validation\Events\RuleSetCompiling;
use Simtabi\Laranail\Validation\Events\ValidationFailed;
use Simtabi\Laranail\Validation\Events\ValidationStarting;
use Simtabi\Laranail\Validation\Events\ValidationCompleted;

/**
 * The event vocabulary and closure hooks of §5.6 — the seams a consumer
 * listens on without touching core. Every test drives a REAL dispatch (a
 * registered listener changing an outcome), not just assertDispatched,
 * because the events' point is that listening does something.
 */
it('lets a RuleSetCompiling listener mutate the rules before validation', function (): void {
    Event::listen(RuleSetCompiling::class, function (RuleSetCompiling $event): void {
        $event->rules['added_by_listener'] = 'required|string';
    });

    try {
        RuleSet::from(['name' => 'required|string'])->validate(['name' => 'Ada']);

        $this->fail('The listener-added rule was expected to fail validation.');
    } catch (ValidationException $validationException) {
        expect($validationException->errors())->toHaveKey('added_by_listener');
    }
});

it('lets a RuleSetCompiling listener mutate messages and attributes', function (): void {
    Event::listen(RuleSetCompiling::class, function (RuleSetCompiling $event): void {
        $event->attributes['email'] = 'work email';
        $event->messages['email.required'] = 'The :attribute is mandatory.';
    });

    try {
        RuleSet::from(['email' => 'required|email'])->validate([]);

        $this->fail('Validation was expected to fail.');
    } catch (ValidationException $validationException) {
        expect($validationException->errors()['email'][0])->toBe('The work email is mandatory.');
    }
});

it('does not permanently mutate the rule set through the event', function (): void {
    $ruleSet = RuleSet::from(['name' => 'required|string']);
    $calls = 0;

    Event::listen(RuleSetCompiling::class, function (RuleSetCompiling $event) use (&$calls): void {
        $calls++;

        if ($calls === 1) {
            $event->rules['once_only'] = 'required';
        }
    });

    expect(fn () => $ruleSet->validate(['name' => 'Ada']))->toThrow(ValidationException::class);

    // Second run: the listener no longer adds the rule; a permanently
    // mutated instance would still carry it and fail again.
    expect($ruleSet->validate(['name' => 'Ada']))->toBe(['name' => 'Ada']);
});

it('fires Starting and Completed around a passing run, and never Failed', function (): void {
    $fired = [];

    Event::listen(ValidationStarting::class, function () use (&$fired): void {
        $fired[] = 'starting';
    });
    Event::listen(ValidationCompleted::class, function (ValidationCompleted $event) use (&$fired): void {
        $fired[] = 'completed';
        expect($event->validated)->toBe(['name' => 'Ada']);
    });
    Event::listen(ValidationFailed::class, function () use (&$fired): void {
        $fired[] = 'failed';
    });

    RuleSet::from(['name' => 'required|string'])->validate(['name' => 'Ada']);

    expect($fired)->toBe(['starting', 'completed']);
});

it('fires Failed with the errors on a failing run, and not Completed', function (): void {
    $fired = [];

    Event::listen(ValidationCompleted::class, function () use (&$fired): void {
        $fired[] = 'completed';
    });
    Event::listen(ValidationFailed::class, function (ValidationFailed $event) use (&$fired): void {
        $fired[] = 'failed';
        expect($event->errors->has('name'))->toBeTrue();
    });

    expect(fn () => RuleSet::from(['name' => 'required|string'])->validate([]))
        ->toThrow(ValidationException::class)
        ->and($fired)->toBe(['failed']);
});

it('fires the same lifecycle through check()', function (): void {
    $fired = [];

    Event::listen(ValidationFailed::class, function () use (&$fired): void {
        $fired[] = 'failed';
    });

    $result = RuleSet::from(['name' => 'required'])->check([]);

    expect($result->fails())->toBeTrue()
        ->and($fired)->toBe(['failed']);
});

it('before() transforms the data ahead of validation', function (): void {
    $validated = RuleSet::from(['name' => 'required|string|min:3'])
        ->before(fn (array $data): array => ['name' => trim(is_string($data['name']) ? $data['name'] : '')] + $data)
        ->validate(['name' => '  Ada  ']);

    expect($validated)->toBe(['name' => 'Ada']);
});

it("after() receives the validator and can add an error, like Laravel's own", function (): void {
    $ruleSet = RuleSet::from(['name' => 'required|string'])
        ->after(function (Validator $validator): void {
            $validator->errors()->add('name', 'Rejected after the rules ran.');
        });

    try {
        $ruleSet->validate(['name' => 'Ada']);

        $this->fail('The after() hook was expected to fail the run.');
    } catch (ValidationException $validationException) {
        expect($validationException->errors()['name'][0])->toBe('Rejected after the rules ran.');
    }
});

it('after() keeps wildcard rules correct on the vanilla route it forces', function (): void {
    $afterRan = false;

    $ruleSet = RuleSet::from(['items' => FluentRule::array()->required(), 'items.*.qty' => 'required|integer|min:1'])
        ->after(function (Validator $validator) use (&$afterRan): void {
            $afterRan = true;
        });

    expect(fn () => $ruleSet->validate(['items' => [['qty' => 0]]]))
        ->toThrow(ValidationException::class)
        ->and($afterRan)->toBeTrue();
});
