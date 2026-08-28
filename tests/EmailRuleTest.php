<?php

declare(strict_types=1);

use Illuminate\Validation\Rules\Email;
use Illuminate\Validation\Rules\Exists;
use Illuminate\Validation\Rules\Unique;
use Simtabi\Laranail\Validation\FluentRule;
use Illuminate\Contracts\Database\Query\Builder;
use Illuminate\Contracts\Validation\ValidationRule;

// =========================================================================
// EmailRule
// =========================================================================

it('validates email with EmailRule', function (): void {
    $v = makeValidator(['email' => 'user@example.com'], ['email' => FluentRule::email()->required()]);
    expect($v->passes())->toBeTrue();

    $v = makeValidator(['email' => 'not-an-email'], ['email' => FluentRule::email()->required()]);
    expect($v->passes())->toBeFalse();
});

it('compiles EmailRule with modes', function (): void {
    expect(FluentRule::email()->compiledRules())->toBe('string|email')
        ->and(FluentRule::email()->rfcCompliant()->compiledRules())->toBe('string|email:rfc')
        ->and(FluentRule::email()->strict()->compiledRules())->toBe('string|email:strict')
        ->and(FluentRule::email()->rfcCompliant()->preventSpoofing()->compiledRules())->toBe('string|email:rfc,spoof')
        ->and(FluentRule::email()->validateMxRecord()->compiledRules())->toBe('string|email:dns')
        ->and(FluentRule::email()->withNativeValidation()->compiledRules())->toBe('string|email:filter')
        ->and(FluentRule::email()->withNativeValidation(allowUnicode: true)->compiledRules())->toBe('string|email:filter_unicode');
});

it('compiles EmailRule with field modifiers', function (): void {
    expect(FluentRule::email()->required()->max(255)->compiledRules())->toBe('required|string|max:255|email');
});

it('validates EmailRule rejects non-string', function (): void {
    $validator = makeValidator(['email' => 123], ['email' => FluentRule::email()->required()]);
    expect($validator->passes())->toBeFalse();
});

// Unique and Exists compile to array form, never a pipe string: their
// __toString() drops closure-based wheres, so isSafeToStringify() excludes them
// whether or not a closure is present. Laravel handles the object directly.

it('EmailRule compiles unique', function (): void {
    $rule = FluentRule::email()->required()->unique('users', 'email');

    expect($rule->compiledRules())->toBeArray()
        ->and($rule->toArray())->toContain('required', 'string', 'email')
        ->and(rulesOfType($rule->toArray(), Unique::class))->toHaveCount(1);
});

it('EmailRule compiles exists', function (): void {
    $rule = FluentRule::email()->required()->exists('users', 'email');

    expect($rule->compiledRules())->toBeArray()
        ->and($rule->toArray())->toContain('required', 'string', 'email')
        ->and(rulesOfType($rule->toArray(), Exists::class))->toHaveCount(1);
});

it('EmailRule compiles confirmed', function (): void {
    expect(FluentRule::email()->confirmed()->compiledRules())->toBe('string|confirmed|email');
});

it('EmailRule compiles same and different', function (): void {
    expect(FluentRule::email()->same('backup_email')->compiledRules())->toBe('string|same:backup_email|email')
        ->and(FluentRule::email()->different('old_email')->compiledRules())->toBe('string|different:old_email|email');
});

it('EmailRule compiledRules returns array for non-Stringable rule', function (): void {
    $nonStringable = new class implements ValidationRule
    {
        public function validate(string $attribute, mixed $value, Closure $fail): void {}
    };

    $compiled = FluentRule::email()->rule($nonStringable)->compiledRules();
    expect($compiled)->toBeArray()
        ->toMatchArray([0 => 'string', 1 => 'email', 2 => $nonStringable]);
});

it('EmailRule with modes validates and compiles correctly', function (): void {
    $emailRule = FluentRule::email()->rfcCompliant()->preventSpoofing()->required();

    // Modes are included in compiled output
    expect($emailRule->compiledRules())->toBe('required|string|email:rfc,spoof');

    // Basic validation still works with modes active
    $v = makeValidator(['email' => 'user@example.com'], ['email' => $emailRule]);
    expect($v->passes())->toBeTrue();

    $v = makeValidator(['email' => 'not-an-email'], ['email' => $emailRule]);
    expect($v->passes())->toBeFalse();
});

// =========================================================================
// Email::default() integration
// =========================================================================

it('uses Email::default() when app defaults are configured', function (): void {
    Email::defaults(fn () => new Email()->rfcCompliant());

    try {
        $compiled = FluentRule::email()->compiledRules();
        expect($compiled)->toBeArray()
            ->and($compiled[0])->toBe('string')
            ->and($compiled[1])->toBeInstanceOf(Email::class);
    } finally {
        Email::$defaultCallback = null;
    }
});

it('uses basic email when no app defaults configured', function (): void {
    expect(FluentRule::email()->compiledRules())->toBe('string|email');
});

it('defaults: false ignores app defaults', function (): void {
    Email::defaults(fn () => new Email()->rfcCompliant()->validateMxRecord());

    try {
        expect(FluentRule::email(defaults: false)->compiledRules())->toBe('string|email');
    } finally {
        Email::$defaultCallback = null;
    }
});

it('explicit modes override app defaults', function (): void {
    Email::defaults(fn () => new Email()->validateMxRecord());

    try {
        // Explicit strict() should use modes, not Email::default()
        expect(FluentRule::email()->strict()->compiledRules())->toBe('string|email:strict');
    } finally {
        Email::$defaultCallback = null;
    }
});

it('defaults: false with label works', function (): void {
    Email::defaults(fn () => new Email()->validateMxRecord());

    try {
        $rule = FluentRule::email('Email Address', defaults: false)->required();
        expect($rule->compiledRules())->toBe('required|string|email')
            ->and($rule->getLabel())->toBe('Email Address');
    } finally {
        Email::$defaultCallback = null;
    }
});

// -------------------------------------------------------------------------
// Exists/Unique carry closure-based wheres in $using, which __toString() never
// emits (DatabaseRule::formatWheres only reads $wheres). Stringifying them
// silently drops the constraint — a soft-delete or tenant scope disappears and
// `exists` then passes rows it was written to exclude. SelfValidates guards
// this via isSafeToStringify(); EmailRule must not bypass that guard.
// -------------------------------------------------------------------------

it('keeps exists() as an object when it carries a closure constraint', function (): void {
    $rule = FluentRule::email()->required()->exists(
        'users',
        'email',
        fn (Exists $exists): Exists => $exists->where(fn (Builder $q): Builder => $q->whereNull('deleted_at')),
    );

    // If this were stringified the closure would be gone: DatabaseRule routes a
    // Closure to $using, and __toString() only formats $wheres.
    expect($rule->compiledRules())->toBeArray()
        ->and(rulesOfType($rule->toArray(), Exists::class))->toHaveCount(1);
});

it('keeps unique() as an object when it carries a closure constraint', function (): void {
    $rule = FluentRule::email()->required()->unique(
        'users',
        'email',
        fn (Unique $unique): Unique => $unique->where(fn (Builder $q): Builder => $q->whereNull('deleted_at')),
    );

    expect($rule->compiledRules())->toBeArray()
        ->and(rulesOfType($rule->toArray(), Unique::class))->toHaveCount(1);
});

it('still pipe-joins when every rule is safely stringifiable', function (): void {
    // The guard must not make everything fall back to array form — plain string
    // rules and In/NotIn still compile to the fast pipe-string path.
    expect(FluentRule::email()->required()->max(255)->compiledRules())
        ->toBe('required|string|max:255|email')
        ->and(FluentRule::email()->required()->in(['a@x.test', 'b@x.test'])->compiledRules())
        ->toBeString();
});
