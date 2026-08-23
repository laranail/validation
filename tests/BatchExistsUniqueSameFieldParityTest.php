<?php declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Simtabi\Laranail\Validation\RuleSet;

/**
 * One field carrying BOTH `exists` and `unique` against the same
 * table:column — the edit-form idiom: the value must exist, and must be
 * unique ignoring the row being edited.
 *
 * The batch collector used to keep only the FIRST batchable rule per field,
 * so the second rule's presence went unseen by the group-level conflict
 * detection. The registered lookup then answered BOTH rule types from one
 * query's result set, but the two rules ask different questions (`ignore()`
 * excludes a row from "taken"; `exists` must not exclude it). Whichever
 * rule was declared second got the wrong answer.
 *
 * A non-batchable second rule (no explicit column, or closure constraints)
 * is the same hole one step removed: it forms no group, the first rule's
 * lookup registers for the shared table:column, and the per-item
 * verification of the second rule is hijacked by that lookup instead of
 * reaching the real database.
 */
function setupExistsUniqueUsers(): void
{
    config(['database.default' => 'testing']);
    config(['database.connections.testing' => ['driver' => 'sqlite', 'database' => ':memory:']]);

    DB::connection('testing')->statement('DROP TABLE IF EXISTS users');
    DB::connection('testing')->statement(
        'CREATE TABLE users (id integer primary key autoincrement, email text)',
    );

    DB::connection('testing')->table('users')->insert([
        ['email' => 'own@example.com'],
        ['email' => 'other@example.com'],
    ]);
}

/**
 * @param  array<string, mixed>  $rules
 * @param  array<string, mixed>  $data
 */
function existsUniqueRuleSetVerdict(array $rules, array $data): bool
{
    try {
        RuleSet::from($rules)->validate($data);

        return true;
    } catch (ValidationException) {
        return false;
    }
}

/**
 * @param  array<string, mixed>  $rules
 * @param  array<string, mixed>  $data
 */
function existsUniqueVanillaVerdict(array $rules, array $data): bool
{
    return Validator::make($data, $rules)->passes();
}

it('answers exists + unique->ignore() on the same field like Laravel, in either declaration order', function (bool $existsFirst): void {
    setupExistsUniqueUsers();

    $exists = Rule::exists('testing.users', 'email');
    $unique = Rule::unique('testing.users', 'email')->ignore(1);

    $rules = [
        'items.*.email' => $existsFirst
            ? ['required', 'email', $exists, $unique]
            : ['required', 'email', $unique, $exists],
    ];

    // Editing row 1: its own email must pass exists AND pass unique
    // (ignored). Laravel passes; a lookup shared across the two rules
    // fails one of them depending on declaration order.
    $data = ['items' => [['email' => 'own@example.com']]];

    expect(existsUniqueVanillaVerdict($rules, $data))->toBeTrue();
    expect(existsUniqueRuleSetVerdict($rules, $data))->toBeTrue();

    // A genuinely taken email (another row's) must still FAIL unique.
    $taken = ['items' => [['email' => 'other@example.com']]];

    expect(existsUniqueVanillaVerdict($rules, $taken))->toBeFalse();
    expect(existsUniqueRuleSetVerdict($rules, $taken))->toBeFalse();

    // A non-existent email must still FAIL exists.
    $missing = ['items' => [['email' => 'nobody@example.com']]];

    expect(existsUniqueVanillaVerdict($rules, $missing))->toBeFalse();
    expect(existsUniqueRuleSetVerdict($rules, $missing))->toBeFalse();
})->with(['exists first' => [true], 'unique first' => [false]]);

it('does not let a batched lookup hijack a non-batchable rule on the same table:column', function (): void {
    setupExistsUniqueUsers();

    // The unique rule has NO explicit column (inferred from the attribute
    // leaf at validation time) so it cannot batch — but it consults the
    // presence verifier under the SAME users:email key the exists rule
    // registers its lookup for.
    $rules = [
        'items.*.email' => [
            'required',
            'email',
            Rule::exists('testing.users', 'email'),
            Rule::unique('testing.users')->ignore(1),
        ],
    ];

    // Editing row 1 with its own email: Laravel passes.
    $own = ['items' => [['email' => 'own@example.com']]];

    expect(existsUniqueVanillaVerdict($rules, $own))->toBeTrue();
    expect(existsUniqueRuleSetVerdict($rules, $own))->toBeTrue();

    // Another row's email: unique must fail.
    $taken = ['items' => [['email' => 'other@example.com']]];

    expect(existsUniqueVanillaVerdict($rules, $taken))->toBeFalse();
    expect(existsUniqueRuleSetVerdict($rules, $taken))->toBeFalse();
});
