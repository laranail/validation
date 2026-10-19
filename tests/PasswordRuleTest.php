<?php

declare(strict_types=1);

use Illuminate\Validation\Rules\Password;
use Simtabi\Laranail\Validation\FluentRule;
use Simtabi\Laranail\Package\Tools\Testing\TestCredentials;

// =========================================================================
// PasswordRule
// =========================================================================

it('validates password with PasswordRule', function (): void {
    $validator = makeValidator(
        ['password' => TestCredentials::password('valid')],
        ['password' => FluentRule::password()->required()->letters()->mixedCase()->numbers()->symbols()],
    );
    expect($validator->passes())->toBeTrue();
});

it('rejects weak password', function (): void {
    $validator = makeValidator(
        // @credential-literal-exempt: a deliberately weak input the rule must reject
        ['password' => 'weak'],
        ['password' => FluentRule::password(8)->required()->letters()->numbers()],
    );
    expect($validator->passes())->toBeFalse();
});

it('validates password min length', function (): void {
    $v = makeValidator(
        // @credential-literal-exempt: exactly the 8-character minimum, the boundary under test
        ['password' => 'abcdefgh'],
        ['password' => FluentRule::password(8)->required()],
    );
    expect($v->passes())->toBeTrue();

    $v = makeValidator(
        // @credential-literal-exempt: shorter than the minimum length the rule under test enforces
        ['password' => 'short'],
        ['password' => FluentRule::password(8)->required()],
    );
    expect($v->passes())->toBeFalse();
});

it('validates password max length', function (): void {
    $validator = makeValidator(
        ['password' => str_repeat('a', 256)],
        ['password' => FluentRule::password()->required()->max(255)],
    );
    expect($validator->passes())->toBeFalse();
});

it('password uncompromised configures the Password rule', function (): void {
    $passwordRule = FluentRule::password()->uncompromised(5);
    $compiled = $passwordRule->compiledRules();
    $password = $compiled[1];
    expect($password)->toBeInstanceOf(Password::class);
    /** @var Password $password */
    expect(new ReflectionProperty($password, 'uncompromised')->getValue($password))->toBeTrue();
    expect(new ReflectionProperty($password, 'compromisedThreshold')->getValue($password))->toBe(5);
});

it('password canCompile returns false', function (): void {
    expect(FluentRule::password()->canCompile())->toBeFalse()
        ->and(FluentRule::password()->required()->canCompile())->toBeFalse();
});

it('password compiledRules includes Password object', function (): void {
    $compiled = FluentRule::password()->required()->compiledRules();
    expect($compiled)->toBeArray()
        ->toMatchArray([0 => 'required', 1 => 'string'])
        ->and($compiled[2])->toBeInstanceOf(Password::class);
});

it('password supports field modifiers', function (): void {
    $v = makeValidator([], ['password' => FluentRule::password()->required()]);
    expect($v->passes())->toBeFalse();

    $v = makeValidator(['password' => null], ['password' => FluentRule::password()->nullable()]);
    expect($v->passes())->toBeTrue();
});

it('password rejects non-string', function (): void {
    $validator = makeValidator(['password' => 12345678], ['password' => FluentRule::password()->required()]);
    expect($validator->passes())->toBeFalse();
});

it('password combined chain rejects each near-miss independently', function (): void {
    $passwordRule = FluentRule::password()->required()->letters()->mixedCase()->numbers()->symbols();

    // Missing uppercase
    // @credential-literal-exempt: crafted to miss exactly one required character class
    $v = makeValidator(['password' => 'abcdefg1!'], ['password' => $passwordRule]);
    expect($v->passes())->toBeFalse();

    // Missing number
    // @credential-literal-exempt: crafted to miss exactly one required character class
    $v = makeValidator(['password' => 'Abcdefg!!'], ['password' => $passwordRule]);
    expect($v->passes())->toBeFalse();

    // Missing symbol
    // @credential-literal-exempt: crafted to miss exactly one required character class
    $v = makeValidator(['password' => 'Abcdefg12'], ['password' => $passwordRule]);
    expect($v->passes())->toBeFalse();

    // Missing letter
    // @credential-literal-exempt: crafted to miss exactly one required character class
    $v = makeValidator(['password' => '12345678!'], ['password' => $passwordRule]);
    expect($v->passes())->toBeFalse();

    // All satisfied
    $v = makeValidator(['password' => TestCredentials::password('complete')], ['password' => $passwordRule]);
    expect($v->passes())->toBeTrue();
});

it('password letters requires at least one letter', function (): void {
    // @credential-literal-exempt: digits only, so the letters() rule must reject it
    $v = makeValidator(['password' => '12345678'], ['password' => FluentRule::password()->required()->letters()]);
    expect($v->passes())->toBeFalse();

    $v = makeValidator(['password' => TestCredentials::password('lettered')], ['password' => FluentRule::password()->required()->letters()]);
    expect($v->passes())->toBeTrue();
});

it('password mixedCase requires upper and lower', function (): void {
    // @credential-literal-exempt: lower case only, so the mixedCase() rule must reject it
    $v = makeValidator(['password' => 'alllowercase'], ['password' => FluentRule::password()->required()->mixedCase()]);
    expect($v->passes())->toBeFalse();

    $v = makeValidator(['password' => TestCredentials::password('mixed')], ['password' => FluentRule::password()->required()->mixedCase()]);
    expect($v->passes())->toBeTrue();
});

it('password numbers requires at least one number', function (): void {
    // @credential-literal-exempt: no digit, so the numbers() rule must reject it
    $v = makeValidator(['password' => 'NoNumbers!'], ['password' => FluentRule::password()->required()->numbers()]);
    expect($v->passes())->toBeFalse();

    $v = makeValidator(['password' => TestCredentials::password('numbered')], ['password' => FluentRule::password()->required()->numbers()]);
    expect($v->passes())->toBeTrue();
});

it('password symbols requires at least one symbol', function (): void {
    // @credential-literal-exempt: no symbol, so the symbols() rule must reject it
    $v = makeValidator(['password' => 'NoSymbols1'], ['password' => FluentRule::password()->required()->symbols()]);
    expect($v->passes())->toBeFalse();

    $v = makeValidator(['password' => TestCredentials::password('symbolic')], ['password' => FluentRule::password()->required()->symbols()]);
    expect($v->passes())->toBeTrue();
});

it('uses Password::default() when no min specified', function (): void {

    Password::defaults(fn () => Password::min(10)->letters()->numbers());

    try {
        // @credential-literal-exempt: shorter than the configured default minimum of 10
        $v = makeValidator(['password' => 'short1'], ['password' => FluentRule::password()->required()]);
        expect($v->passes())->toBeFalse(); // too short (min 10)

        $v = makeValidator(['password' => TestCredentials::password('sufficient')], ['password' => FluentRule::password()->required()]);
        expect($v->passes())->toBeTrue();
    } finally {
        Password::$defaultCallback = null;
    }
});

it('overrides defaults when explicit min is passed', function (): void {
    Password::defaults(fn () => Password::min(20)->letters());

    try {
        $v = makeValidator(['password' => TestCredentials::password('brief', 8)], ['password' => FluentRule::password(min: 6)->required()]);
        expect($v->passes())->toBeTrue();
    } finally {
        Password::$defaultCallback = null;
    }
});

it('defaults: false ignores app password defaults', function (): void {
    Password::defaults(fn () => Password::min(20)->letters()->numbers());

    try {
        // defaults: false → Password::min(8), ignores the configured min:20 + letters + numbers
        $v = makeValidator(['password' => TestCredentials::password('plain', 14)], ['password' => FluentRule::password(defaults: false)->required()]);
        expect($v->passes())->toBeTrue(); // passes with min:8, no letters/numbers requirement

        // With defaults: true (default) → applies min:20 + letters + numbers
        $v = makeValidator(['password' => TestCredentials::password('plain', 14)], ['password' => FluentRule::password()->required()]);
        expect($v->passes())->toBeFalse(); // fails: too short (min:20)
    } finally {
        Password::$defaultCallback = null;
    }
});

it('min() sets minimum length via chain', function (): void {
    // @credential-literal-exempt: shorter than the minimum length the rule under test enforces
    $v = makeValidator(['password' => 'short'], ['password' => FluentRule::password()->min(8)->required()]);
    expect($v->passes())->toBeFalse();

    $v = makeValidator(['password' => TestCredentials::password('long')], ['password' => FluentRule::password()->min(8)->required()]);
    expect($v->passes())->toBeTrue();
});

it('min() and max() work together', function (): void {
    $rule = FluentRule::password()->min(8)->max(20)->required();

    // @credential-literal-exempt: shorter than the minimum length the rule under test enforces
    $v = makeValidator(['password' => 'short'], ['password' => $rule]);
    expect($v->passes())->toBeFalse(); // too short

    $v = makeValidator(['password' => TestCredentials::password('excessive', 41)], ['password' => $rule]);
    expect($v->passes())->toBeFalse(); // too long

    $v = makeValidator(['password' => TestCredentials::password('balanced', 12)], ['password' => $rule]);
    expect($v->passes())->toBeTrue();
});

it('min() preserves other password rules', function (): void {
    $rule = FluentRule::password()->min(10)->letters()->numbers();

    // @credential-literal-exempt: digits only, so the letters() rule must reject it
    $v = makeValidator(['password' => '1234567890'], ['password' => $rule]);
    expect($v->passes())->toBeFalse(); // no letters

    // @credential-literal-exempt: letters only, so the numbers() rule must reject it
    $v = makeValidator(['password' => 'abcdefghij'], ['password' => $rule]);
    expect($v->passes())->toBeFalse(); // no numbers

    $v = makeValidator(['password' => TestCredentials::password('alphanumeric')], ['password' => $rule]);
    expect($v->passes())->toBeTrue();
});

it('confirmed() works on password rule', function (): void {
    expect(TestCredentials::password('mismatch'))->not->toBe(TestCredentials::password('confirmed'));

    $rule = FluentRule::password()->required()->confirmed();

    $v = makeValidator(
        ['password' => TestCredentials::password('confirmed'), 'password_confirmation' => TestCredentials::password('confirmed')],
        ['password' => $rule],
    );
    expect($v->passes())->toBeTrue();

    $v = makeValidator(
        ['password' => TestCredentials::password('confirmed'), 'password_confirmation' => TestCredentials::password('mismatch')],
        ['password' => $rule],
    );
    expect($v->passes())->toBeFalse();
});
