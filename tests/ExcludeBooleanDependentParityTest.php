<?php declare(strict_types=1);

use Illuminate\Support\Facades\Validator;
use Simtabi\Laranail\Validation\FluentRule;
use Simtabi\Laranail\Validation\FluentValidator;

/**
 * `exclude_if` / `exclude_unless` against a dependent DECLARED `boolean`.
 *
 * Laravel's `parseDependentRuleParameters()` converts a rule's `true`/`false`
 * parameters to real booleans whenever the dependent field carries a
 * `boolean` rule — `shouldConvertToBoolean($parameters[0])` — not only when
 * the submitted value already is one. The optimizer's top-level path skipped
 * that check, so `exclude_unless:notify,true` did not match a `notify` of `1`,
 * a value the `boolean` rule accepts.
 *
 * That direction is the harmful one: under `exclude_unless` a non-match
 * EXCLUDES, so the field disappeared from `validated()` while Laravel kept it
 * — a silent data loss on save rather than a visible validation error.
 *
 * These compare the two validators rather than asserting one, because the
 * requirement is parity with Laravel, not any particular verdict.
 */
final class ExcludeUnlessBooleanValidator extends FluentValidator
{
    /** @param  array<string, mixed>  $data */
    public function __construct(array $data)
    {
        parent::__construct($data, [
            'notify' => FluentRule::boolean(),
            'email' => FluentRule::string()->excludeUnless('notify', 'true')->required(),
        ]);
    }
}

final class ExcludeIfBooleanValidator extends FluentValidator
{
    /** @param  array<string, mixed>  $data */
    public function __construct(array $data)
    {
        parent::__construct($data, [
            'notify' => FluentRule::boolean(),
            'email' => FluentRule::string()->excludeIf('notify', 'true')->required(),
        ]);
    }
}

/** @return array<array-key, mixed> */
function vanillaExcluding(string $rule, mixed $notify): array
{
    return Validator::make(
        ['notify' => $notify, 'email' => 'a@b.com'],
        ['notify' => ['boolean'], 'email' => [$rule, 'required', 'string']],
    )->validate();
}

it('keeps exclude_unless in step with Laravel for every value boolean accepts', function (mixed $notify): void {
    // 1 and '1' are the cases that broke: accepted by `boolean`, not bools.
    $vanilla = vanillaExcluding('exclude_unless:notify,true', $notify);
    $optimized = new ExcludeUnlessBooleanValidator(['notify' => $notify, 'email' => 'a@b.com'])->validated();

    expect(array_key_exists('email', $optimized))->toBe(array_key_exists('email', $vanilla));
})->with([
    'int one' => 1,
    'string one' => '1',
    'real true' => true,
    'int zero' => 0,
    'string zero' => '0',
    'real false' => false,
]);

it('keeps exclude_if in step with Laravel for the same values', function (mixed $notify): void {
    $vanilla = vanillaExcluding('exclude_if:notify,true', $notify);
    $optimized = new ExcludeIfBooleanValidator(['notify' => $notify, 'email' => 'a@b.com'])->validated();

    expect(array_key_exists('email', $optimized))->toBe(array_key_exists('email', $vanilla));
})->with([
    'int one' => 1,
    'string one' => '1',
    'real true' => true,
    'int zero' => 0,
    'string zero' => '0',
    'real false' => false,
]);

it('still excludes when the dependent is not declared boolean', function (): void {
    // Without a `boolean` declaration Laravel does not convert either, so the
    // string comparison stands and the field is genuinely excluded. This is
    // the control: the fix must not make everything match.
    $data = ['notify' => 'nope', 'email' => 'a@b.com'];

    $vanilla = Validator::make($data, [
        'notify' => ['string'],
        'email' => ['exclude_unless:notify,true', 'required', 'string'],
    ])->validate();

    expect($vanilla)->not->toHaveKey('email');
});
