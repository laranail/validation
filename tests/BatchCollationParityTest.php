<?php declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Simtabi\Laranail\Validation\FluentRule;
use Simtabi\Laranail\Validation\FluentValidator;
use Simtabi\Laranail\Validation\RuleSet;

/**
 * The batched presence check must agree with the database, not with PHP.
 *
 * `BatchPresenceQuery` matches with `whereIn`, so the DATABASE decides what
 * equals what — its collation, its padding, its numeric coercion. It then
 * plucks the STORED values back and `PrecomputedPresenceVerifier` compares
 * them to the submitted values as exact PHP array keys.
 *
 * Those two comparisons are not the same one. On any case-insensitive
 * collation — MySQL's `utf8mb4_0900_ai_ci` default, SQL Server's default,
 * a Postgres `citext` column, SQLite `COLLATE NOCASE` — the query matches a
 * row that the PHP lookup then fails to find, and the rule reports "not
 * taken" for a value the database considers taken.
 *
 * On `unique` that admits a duplicate. Vanilla Laravel, which asks the
 * database directly, rejects it. These tests compare the two answers rather
 * than asserting one in isolation, because the requirement is parity.
 */
function setupCollatedUsers(): void
{
    config(['database.default' => 'testing']);
    config(['database.connections.testing' => ['driver' => 'sqlite', 'database' => ':memory:']]);

    // Raw DDL: the collation has to be on the column for SQLite to apply it
    // to a bare `WHERE email = ?`, which is what whereIn compiles to.
    DB::connection('testing')->statement('DROP TABLE IF EXISTS users');
    DB::connection('testing')->statement(
        'CREATE TABLE users (id integer primary key autoincrement, email text collate nocase)',
    );

    DB::connection('testing')->table('users')->insert([
        ['email' => 'alice@example.com'],
        ['email' => 'bob@example.com'],
    ]);
}

/** The batched path, which only FluentValidator/HasFluentRules/RuleSet reach. */
final class CollatedUniqueValidator extends FluentValidator
{
    /** @param  array<string, mixed>  $data */
    public function __construct(array $data)
    {
        parent::__construct($data, [
            'items' => FluentRule::array()->required()->each([
                'email' => FluentRule::string()->required()->unique('testing.users', 'email'),
            ]),
        ]);
    }
}

function batchedUniquePasses(string $email): bool
{
    try {
        new CollatedUniqueValidator(['items' => [['email' => $email]]])->validated();

        return true;
    } catch (ValidationException) {
        return false;
    }
}

function vanillaUniquePasses(string $email): bool
{
    return Validator::make(
        ['email' => $email],
        ['email' => ['required', Rule::unique('testing.users', 'email')]],
    )->passes();
}

it('agrees with the database when the collation is case-insensitive', function (string $email): void {
    // The database considers these taken. If the batched path disagrees, a
    // duplicate row gets written on a column the DB treats as unique.
    setupCollatedUsers();

    expect(batchedUniquePasses($email))->toBe(vanillaUniquePasses($email));
})->with([
    'exact' => 'alice@example.com',
    'upper' => 'ALICE@EXAMPLE.COM',
    'mixed' => 'Alice@Example.com',
    'free' => 'dave@example.com',
]);

it('agrees with the database on exists, for the same reason inverted', function (string $email): void {
    setupCollatedUsers();

    $batched = RuleSet::from([
        'email' => FluentRule::string()->required()->exists('testing.users', 'email'),
    ])->check(['email' => $email])->passes();

    $vanilla = Validator::make(
        ['email' => $email],
        ['email' => ['required', Rule::exists('testing.users', 'email')]],
    )->passes();

    expect($batched)->toBe($vanilla);
})->with([
    'exact' => 'alice@example.com',
    'upper' => 'ALICE@EXAMPLE.COM',
    'absent' => 'nobody@example.com',
]);
