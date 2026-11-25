<?php

declare(strict_types=1);

use Illuminate\Validation\Rule;
use Simtabi\Laranail\Validation\BatchDatabaseChecker;
use Simtabi\Laranail\Validation\Internal\BatchWhereClauses;

it('reads the operator-keyed clauses Laravel 13.43 and later store', function (): void {
    expect(BatchWhereClauses::of([
        ['column' => 'deleted_at', 'operator' => 'NULL', 'value' => null],
        ['column' => 'verified_at', 'operator' => 'NOT_NULL', 'value' => null],
        ['column' => 'team_id', 'operator' => '=', 'value' => '4'],
        ['column' => 'role', 'operator' => '!=', 'value' => 'guest'],
        // A literal 'NULL' under '=' is a string comparison, not whereNull().
        ['column' => 'status', 'operator' => '=', 'value' => 'NULL'],
    ]))->toBe([
        ['column' => 'deleted_at', 'operator' => 'NULL', 'value' => null],
        ['column' => 'verified_at', 'operator' => 'NOT_NULL', 'value' => null],
        ['column' => 'team_id', 'operator' => '=', 'value' => '4'],
        ['column' => 'role', 'operator' => '!=', 'value' => 'guest'],
        ['column' => 'status', 'operator' => '=', 'value' => 'NULL'],
    ]);
});

it('reads the value-encoded clauses Laravel 13.42 and earlier store', function (): void {
    expect(BatchWhereClauses::of([
        ['column' => 'deleted_at', 'value' => 'NULL'],
        ['column' => 'verified_at', 'value' => 'NOT_NULL'],
        ['column' => 'team_id', 'value' => '4'],
        ['column' => 'role', 'value' => '!guest'],
    ]))->toBe([
        ['column' => 'deleted_at', 'operator' => 'NULL', 'value' => null],
        ['column' => 'verified_at', 'operator' => 'NOT_NULL', 'value' => null],
        ['column' => 'team_id', 'operator' => '=', 'value' => '4'],
        ['column' => 'role', 'operator' => '!=', 'value' => 'guest'],
    ]);
});

it('refuses a clause it cannot replay instead of dropping it', function (mixed $wheres): void {
    expect(BatchWhereClauses::of($wheres))->toBeNull();
})->with([
    'not an array'     => ['nope'],
    'no column'        => [[['operator' => 'NULL', 'value' => null]]],
    'unknown operator' => [[['column' => 'age', 'operator' => '>', 'value' => '3']]],
    'non-scalar value' => [[['column' => 'age', 'value' => ['3']]]],
]);

it('reads the clauses the installed Laravel writes for withoutTrashed()', function (): void {
    $rule = Rule::exists('users', 'email')->withoutTrashed();
    $wheres = new ReflectionProperty($rule, 'wheres')->getValue($rule);

    expect($wheres)->toHaveCount(1)
        ->and(BatchWhereClauses::of($wheres))->toBe([
            ['column' => 'deleted_at', 'operator' => 'NULL', 'value' => null],
        ])
        ->and(BatchDatabaseChecker::isBatchable($rule))->toBeTrue();
});
