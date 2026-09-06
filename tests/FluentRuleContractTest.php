<?php

declare(strict_types=1);

use Simtabi\Laranail\Validation\FluentRule;
use Illuminate\Contracts\Validation\ValidationRule;
use Simtabi\Laranail\Validation\Builder\Nodes\DateRule;
use Simtabi\Laranail\Validation\Builder\Nodes\FileRule;
use Simtabi\Laranail\Validation\Builder\Nodes\ArrayRule;
use Simtabi\Laranail\Validation\Builder\Nodes\EmailRule;
use Simtabi\Laranail\Validation\Builder\Nodes\FieldRule;
use Simtabi\Laranail\Validation\Builder\Nodes\ImageRule;
use Simtabi\Laranail\Validation\Builder\Nodes\StringRule;
use Simtabi\Laranail\Validation\Builder\Nodes\BooleanRule;
use Simtabi\Laranail\Validation\Builder\Nodes\NumericRule;
use Simtabi\Laranail\Validation\Builder\Nodes\AcceptedRule;
use Simtabi\Laranail\Validation\Builder\Nodes\PasswordRule;
use Simtabi\Laranail\Validation\Contracts\FluentRuleContract;

// =========================================================================
// FluentRuleContract — medium-contract marker interface implemented by
// every *Rule class shipped in Rules/*. Purpose: collapse unwieldy
// `FieldRule|StringRule|NumericRule|…` return-type unions in downstream
// FormRequests/Livewire components to a single stable type.
//
// Most of this contract's guarantees are enforced at the type-system
// level (PHPStan verifies statically). The tests below cover two things
// statics can't: (a) full runtime reflection audit of every shipped
// rule class, (b) end-to-end validation flow via the interface's
// shared method surface.
// =========================================================================

/**
 * Every rule-class shipped under `Rules/*` — keep in sync when a new
 * family lands (currently 11 classes).
 *
 * @return array<int, class-string<FluentRuleContract>>
 */
function allRuleClasses(): array
{
    return [
        AcceptedRule::class,
        ArrayRule::class,
        BooleanRule::class,
        DateRule::class,
        EmailRule::class,
        FieldRule::class,
        FileRule::class,
        ImageRule::class,
        NumericRule::class,
        PasswordRule::class,
        StringRule::class,
    ];
}

it('every shipped rule class has FluentRuleContract in its runtime interface list', function (): void {
    // Reflection audit — catches cases where a new rule class is added
    // without implementing the contract (easy to forget for type-hint
    // reliance downstream).
    foreach (allRuleClasses() as $class) {
        $interfaces = class_implements($class);
        expect($interfaces)->toHaveKey(FluentRuleContract::class);
    }
});

it('FluentRuleContract extends Laravel ValidationRule so ValidationRule-typed consumers keep working', function (): void {
    $interfaces = class_implements(FieldRule::class);
    expect($interfaces)->toHaveKey(FluentRuleContract::class)
        ->and($interfaces)->toHaveKey(ValidationRule::class);
});

it('end-to-end: validation works through the FluentRuleContract type alias', function (): void {
    // Simulates a downstream FormRequest whose rules() returns
    // `array<string, FluentRuleContract>` instead of a concrete-type union.
    /** @var array<string, FluentRuleContract> $rules */
    $rules = [
        'name'  => FluentRule::string()->required()->min(2),
        'age'   => FluentRule::numeric()->nullable()->integer()->min(0),
        'email' => FluentRule::email()->required(),
        'agree' => FluentRule::accepted(),
    ];

    $validator = makeValidator(
        ['name' => 'Alice', 'age' => 30, 'email' => 'alice@example.com', 'agree' => true],
        $rules,
    );

    expect($validator->passes())->toBeTrue();
});

it('interface surface is callable via contract-typed reference (chaining preserves contract)', function (): void {
    $build = (fn (FluentRuleContract $rule): FluentRuleContract => $rule
        ->required()
        ->nullable()
        ->bail()
        ->label('Test')
        ->message('Must match.'));

    $result = $build(FluentRule::string());

    // Behavioral proof: the contract-typed chain produced a rule that
    // fails for a value violating it and passes for one that satisfies.
    expect(makeValidator(['x' => null], ['x' => $result])->fails())->toBeTrue()
        ->and(makeValidator(['x' => 'ok'], ['x' => $result])->passes())->toBeTrue();
});

it('when() and unless() are callable on a contract-typed reference (Conditionable surface)', function (): void {
    // Codex regression coverage: downstream helpers accepting a
    // FluentRuleContract must be able to chain ->when() / ->unless()
    // without narrowing back to a concrete rule class.
    //
    // `when()` with a callback returns `static`; chain continues. Since
    // the static return on a `FluentRuleContract` reference narrows to
    // `FluentRuleContract`, the callback receives `$r` typed as the
    // concrete class via the trait's template binding.
    // The callback receives `$this` — at the interface level it's typed as
    // FluentRuleContract, so we narrow via an instanceof guard before calling
    // a type-specific method. Downstream consumers pick their own concrete
    // type when they know it.
    $apply = function (FluentRuleContract $rule, bool $strict): FluentRuleContract {
        /** @var FluentRuleContract $result */
        $result = $rule
            ->required()
            ->when($strict, static fn (FluentRuleContract $r): FluentRuleContract => $r instanceof StringRule ? $r->min(8) : $r)
            ->unless($strict, static fn (FluentRuleContract $r): FluentRuleContract => $r instanceof StringRule ? $r->min(2) : $r);

        return $result;
    };

    $strictRule = $apply(FluentRule::string(), true);
    $laxRule = $apply(FluentRule::string(), false);

    expect(makeValidator(['x' => 'short'], ['x' => $strictRule])->fails())->toBeTrue()
        ->and(makeValidator(['x' => 'longenough'], ['x' => $strictRule])->passes())->toBeTrue()
        ->and(makeValidator(['x' => 'short'], ['x' => $laxRule])->passes())->toBeTrue()
        ->and(makeValidator(['x' => 'x'], ['x' => $laxRule])->fails())->toBeTrue();
});
