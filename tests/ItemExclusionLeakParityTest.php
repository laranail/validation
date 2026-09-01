<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Validator;
use Simtabi\Laranail\Validation\FluentRule;
use Simtabi\Laranail\Validation\RuleSet;

/**
 * Exclusions must not carry from one array item to the next.
 *
 * A per-item validator is built once per distinct rule SHAPE and reused for
 * every item with that shape, with only the data swapped. Laravel's `passes()`
 * resets `$messages`, `$failedRules` and `$distinctValues`, and `setData()`
 * re-parses the data and re-sets the rules — but neither clears
 * `$excludeAttributes`, which `Validator::excludeAttribute()` only ever
 * appends to.
 *
 * So once any item excluded an attribute, every later item sharing that
 * validator inherited the exclusion: its own copy of the field was skipped and
 * dropped from the validated data, with no error raised.
 *
 * `exclude_if` / `exclude_unless` are pre-evaluated before the item validator
 * runs, so they never reach it — `ExcludeConditionExtractor::ACTIONS` lists
 * only those two. `exclude_without` (and `exclude_with`, `exclude`) are not,
 * and they keep an identical rule string across items while the DATA decides,
 * which is precisely the shape that shares a cached validator.
 */
function itemExclusionRules(): RuleSet
{
    return RuleSet::from(['items' => FluentRule::array()->required()->each([
        'email' => FluentRule::string()->rule('exclude_without:alias')->email(),
        'alias' => FluentRule::string()->sometimes(),
    ])]);
}

/**
 * @param  array<array-key, mixed>  $data
 * @return array<array-key, string>
 */
function vanillaExclusionErrors(array $data): array
{
    return Validator::make($data, [
        'items.*.email' => ['exclude_without:items.*.alias', 'email'],
        'items.*.alias' => ['sometimes', 'string'],
    ])->errors()->keys();
}

it('validates a later item even when an earlier one was excluded', function (): void {
    // Item 0 has no alias, so its email is excluded. Item 1 has one, so its
    // invalid email must still be reported. Before the fix, item 0's exclusion
    // carried over and this produced no errors at all.
    $data = ['items' => [
        ['email' => 'not-an-email'],
        ['email' => 'not-an-email', 'alias' => 'x'],
    ]];

    expect(itemExclusionRules()->check($data)->errors()->keys())->toBe(vanillaExclusionErrors($data));
});

it('gives the same answer whichever order the items arrive in', function (): void {
    // Order dependence was the giveaway: the reversed order always worked,
    // because the item that needed validating ran before anything was excluded.
    $excludedFirst = ['items' => [
        ['email' => 'not-an-email'],
        ['email' => 'not-an-email', 'alias' => 'x'],
    ]];

    $validatedFirst = ['items' => [
        ['email' => 'not-an-email', 'alias' => 'x'],
        ['email' => 'not-an-email'],
    ]];

    expect(itemExclusionRules()->check($excludedFirst)->errors())->toHaveCount(1)
        ->and(itemExclusionRules()->check($validatedFirst)->errors())->toHaveCount(1);
});

it('still excludes every item that genuinely qualifies', function (): void {
    // The control: clearing the carried-over exclusions must not stop
    // exclusion working. No item has an alias, so no email is validated.
    $data = ['items' => [
        ['email' => 'not-an-email'],
        ['email' => 'also-not-an-email'],
    ]];

    expect(itemExclusionRules()->check($data)->passes())->toBeTrue()
        ->and(vanillaExclusionErrors($data))->toBeEmpty();
});

it('reports every item that fails, across a shared validator', function (): void {
    $data = ['items' => [
        ['email' => 'not-an-email'],
        ['email' => 'bad-one', 'alias' => 'x'],
        ['email' => 'bad-two', 'alias' => 'y'],
    ]];

    expect(itemExclusionRules()->check($data)->errors()->keys())->toBe(vanillaExclusionErrors($data));
});
