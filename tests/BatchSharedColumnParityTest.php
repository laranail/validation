<?php declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Simtabi\Laranail\Validation\FluentRule;
use Simtabi\Laranail\Validation\RuleSet;

/**
 * Two fields pointing at the same table and column.
 *
 * The batched groups were keyed on `table:column:ruleType` and ASSIGNED, so a
 * second field checking the same column replaced the first group outright.
 * The earlier field's values were then never queried, came back absent from
 * the lookup, and its `unique` rule reported "not taken" for a value that
 * was — a duplicate admitted. The giveaway was that the outcome depended on
 * declaration order.
 *
 * Two fields against one column is ordinary: a primary and a backup address
 * both checked against `users.email`.
 *
 * The key now also carries the rule's query shape, because the two rules are
 * only interchangeable if they would have queried identically — a `where()`
 * or an `ignore()` on one of them makes them different questions.
 */
function setupSharedColumnUsers(): void
{
    config(['database.default' => 'testing']);
    config(['database.connections.testing' => ['driver' => 'sqlite', 'database' => ':memory:']]);

    DB::connection('testing')->statement('DROP TABLE IF EXISTS users');
    DB::connection('testing')->statement(
        'CREATE TABLE users (id integer primary key autoincrement, email text, team_id integer)',
    );

    DB::connection('testing')->table('users')->insert([
        ['email' => 'taken@example.com', 'team_id' => 1],
        ['email' => 'other@example.com', 'team_id' => 2],
    ]);
}

/**
 * @param  array<array-key, mixed>  $data
 * @return array<array-key, string>
 */
function vanillaSharedColumnErrors(array $data): array
{
    return Validator::make($data, [
        'items.*.primary' => ['required', 'string', Rule::unique('testing.users', 'email')],
        'items.*.backup' => ['required', 'string', Rule::unique('testing.users', 'email')],
    ])->errors()->keys();
}

it('checks both fields, whichever order they are declared in', function (bool $primaryFirst): void {
    setupSharedColumnUsers();

    $primary = ['primary' => FluentRule::string()->required()->unique('testing.users', 'email')];
    $backup = ['backup' => FluentRule::string()->required()->unique('testing.users', 'email')];

    $rules = ['items' => FluentRule::array()->required()->each(
        $primaryFirst ? $primary + $backup : $backup + $primary,
    )];

    $data = ['items' => [['primary' => 'taken@example.com', 'backup' => 'free@example.com']]];

    // Declaration order must not change the answer. It did: whichever field
    // was declared last won the group and the other went unchecked.
    expect(RuleSet::from($rules)->check($data)->fails())->toBeTrue();
})->with([
    'primary declared first' => true,
    'backup declared first' => false,
]);

it('flags every taken value when both fields carry one', function (): void {
    setupSharedColumnUsers();

    $data = ['items' => [['primary' => 'taken@example.com', 'backup' => 'other@example.com']]];

    $result = RuleSet::from(['items' => FluentRule::array()->required()->each([
        'primary' => FluentRule::string()->required()->unique('testing.users', 'email'),
        'backup' => FluentRule::string()->required()->unique('testing.users', 'email'),
    ])])->check($data);

    expect($result->errors()->keys())->toHaveSameSize(vanillaSharedColumnErrors($data));
});

it('lets both through when neither value is taken', function (): void {
    // The control: merging the groups must not make everything fail.
    setupSharedColumnUsers();

    $result = RuleSet::from(['items' => FluentRule::array()->required()->each([
        'primary' => FluentRule::string()->required()->unique('testing.users', 'email'),
        'backup' => FluentRule::string()->required()->unique('testing.users', 'email'),
    ])])->check(['items' => [['primary' => 'free@example.com', 'backup' => 'spare@example.com']]]);

    expect($result->passes())->toBeTrue();
});

it('does not answer one rule from another rule’s result set', function (): void {
    // `unique(...)->where('team_id', 2)` asks a different question from a bare
    // unique on the same column. Sharing a group would answer one from the
    // other's rows; the lookup is keyed on table and column alone and cannot
    // tell them apart, so neither may be batched.
    setupSharedColumnUsers();

    $data = ['items' => [['primary' => 'taken@example.com', 'backup' => 'taken@example.com']]];

    $result = RuleSet::from(['items' => FluentRule::array()->required()->each([
        'primary' => FluentRule::string()->required()->unique('testing.users', 'email'),
        // The rule object, not the node's unique() helper: a scalar where()
        // lands in $wheres and stays batchable, whereas a closure constraint
        // would disable batching outright and never reach the shape key.
        'backup' => FluentRule::string()->required()->rule(
            Rule::unique('testing.users', 'email')->where('team_id', 2),
        ),
    ])])->check($data);

    // taken@example.com is on team 1, so it is taken for `primary` and free
    // for `backup`. One error, not zero and not two.
    expect($result->errors()->keys())->toHaveCount(1);
});
