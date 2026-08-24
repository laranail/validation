<?php declare(strict_types=1);

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Simtabi\Laranail\Validation\Rules\Database\CompareToColumn;
use Simtabi\Laranail\Validation\Rules\Database\Comparison;

beforeEach(function (): void {
    config(['database.default' => 'testing']);
    config(['database.connections.testing' => ['driver' => 'sqlite', 'database' => ':memory:']]);

    Schema::connection('testing')->create('products', function (Blueprint $table): void {
        $table->id();
        $table->integer('max_quantity');
        $table->string('tier');
    });

    DB::connection('testing')->table('products')->insert([
        ['id' => 1, 'max_quantity' => 10, 'tier' => 'basic'],
        ['id' => 2, 'max_quantity' => 100, 'tier' => 'pro'],
    ]);
});

it('compares the value against the looked-up column across the operators', function (): void {
    $rule = static fn (Comparison $op): CompareToColumn => new CompareToColumn(
        'products', 'max_quantity', $op, 'id', 1,
    );

    expect(ruleAccepts($rule(Comparison::LessThanOrEqual), '10'))->toBeTrue()
        ->and(ruleAccepts($rule(Comparison::LessThanOrEqual), '11'))->toBeFalse()
        ->and(ruleAccepts($rule(Comparison::LessThan), '9'))->toBeTrue()
        ->and(ruleAccepts($rule(Comparison::LessThan), '10'))->toBeFalse()
        ->and(ruleAccepts($rule(Comparison::GreaterThanOrEqual), '10'))->toBeTrue()
        ->and(ruleAccepts($rule(Comparison::GreaterThan), '10'))->toBeFalse()
        ->and(ruleAccepts($rule(Comparison::Equal), '10'))->toBeTrue()
        ->and(ruleAccepts($rule(Comparison::NotEqual), '10'))->toBeFalse()
        ->and(ruleAccepts($rule(Comparison::NotEqual), '11'))->toBeTrue();
});

it('compares numerically, not lexicographically', function (): void {
    // '9' > '10' as strings — the failure a naive string comparison bakes in.
    $rule = new CompareToColumn('products', 'max_quantity', Comparison::LessThanOrEqual, 'id', 2);

    expect(ruleAccepts($rule, '99'))->toBeTrue()
        ->and(ruleAccepts($rule, '101'))->toBeFalse();
});

it('reads an @-prefixed key from a sibling field', function (): void {
    $rules = [
        'product_id' => 'required',
        'quantity' => [new CompareToColumn('products', 'max_quantity', Comparison::LessThanOrEqual, 'id', '@product_id')],
    ];

    expect(Validator::make(['product_id' => 1, 'quantity' => '10'], $rules)->passes())->toBeTrue()
        ->and(Validator::make(['product_id' => 1, 'quantity' => '11'], $rules)->passes())->toBeFalse()
        ->and(Validator::make(['product_id' => 2, 'quantity' => '11'], $rules)->passes())->toBeTrue();
});

it('fails when the referenced row does not exist', function (): void {
    // A missing row means the bound could not be checked; passing would
    // enforce nothing exactly when the data is most suspect.
    $rule = new CompareToColumn('products', 'max_quantity', Comparison::LessThanOrEqual, 'id', 999);

    expect(ruleAccepts($rule, '1'))->toBeFalse();
});

it('fails non-scalar values', function (): void {
    $rule = new CompareToColumn('products', 'max_quantity', Comparison::LessThanOrEqual, 'id', 1);

    expect(ruleAccepts($rule, ['10']))->toBeFalse()
        ->and(ruleAccepts($rule, null))->toBeFalse();
});

it('resolves operators from their string tokens for the alias form', function (): void {
    expect(Comparison::from('lte'))->toBe(Comparison::LessThanOrEqual)
        ->and(Comparison::from('neq'))->toBe(Comparison::NotEqual);
});
