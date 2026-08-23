<?php declare(strict_types=1);

use Illuminate\Translation\ArrayLoader;
use Illuminate\Translation\Translator;
use Simtabi\Laranail\Validation\Rules\Text\PersonName;
use Simtabi\Laranail\Validation\RuleSet;
use Simtabi\Laranail\Validation\Schemas\PersonNameSchema;

/*
 * The failure this file exists for is a pair, and each half looks like the fix
 * for the other.
 *
 * Drop `nullable` from the field carrying the requirement and every name that
 * omits a first name is refused — by the `string` rule, reporting "must be a
 * string", which names neither the rule nor the fix. Drop the requirement's
 * implicitness and a payload with no name at all validates cleanly, leaving
 * only the database CHECK to catch it, with a 500 instead of a message.
 *
 * Both have been live. So the matrix below runs every combination rather than
 * each field alone, and runs it through normalise() first — because that is
 * what callers do, and it turns an ABSENT key into a NULL one, which Laravel
 * treats differently.
 */

it('refuses a payload carrying no name at all', function (): void {
    $payloads = [
        'no keys at all' => [],
        'all empty strings' => ['first_name' => '', 'middle_name' => '', 'last_name' => ''],
        'all null' => ['first_name' => null, 'middle_name' => null, 'last_name' => null],
        'whitespace only' => ['first_name' => '   ', 'middle_name' => "\t", 'last_name' => ' '],
        'only unrelated keys' => ['email' => 'a@b.test'],
    ];

    foreach ($payloads as $label => $payload) {
        expect(PersonNameSchema::make()->toRuleSet()->check($payload)->passes())
            ->toBeFalse("accepted a payload with {$label}");
    }
});

it('accepts every combination that carries a name', function (): void {
    // All seven non-empty combinations the database CHECK permits, through the
    // path the application actually uses. The validator has to agree with the
    // schema exactly: anything holding one of the three must validate.
    $combinations = [
        'first only' => ['first_name' => 'Ada'],
        'middle only' => ['middle_name' => 'Prince'],
        'last only' => ['last_name' => 'Bono'],
        'first and middle' => ['first_name' => 'Ada', 'middle_name' => 'Byron'],
        'first and last' => ['first_name' => 'Ada', 'last_name' => 'Lovelace'],
        'middle and last' => ['middle_name' => 'Byron', 'last_name' => 'Lovelace'],
        'all three' => ['first_name' => 'Ada', 'middle_name' => 'Byron', 'last_name' => 'Lovelace'],
    ];

    $schema = PersonNameSchema::make();

    foreach ($combinations as $label => $combination) {
        $result = $schema->toRuleSet()->check($schema->normalise($combination));

        expect($result->passes())->toBeTrue("{$label}: " . ($result->errors()->first() ?? ''));
    }
});

it('accepts a null first name when another field carries the name', function (): void {
    // The regression, stated directly, and on an EXPLICIT null rather than an
    // absent key — that is the difference that hid it. An absent attribute
    // skips the non-implicit rules; a null one does not, and normalise()
    // produces null.
    $passes = PersonNameSchema::make()
        ->toRuleSet()
        ->check(['first_name' => null, 'middle_name' => null, 'last_name' => 'Lovelace'])
        ->passes();

    expect($passes)->toBeTrue();
});

it('reports one error for one missing name, not one per field', function (): void {
    $errors = PersonNameSchema::make()->toRuleSet()->check([])->errors();

    expect($errors->count())->toBe(1);
});

it('tells the user what to do rather than what the rule is', function (): void {
    // The stock message is "required when none of middle name / last name are
    // present", which describes the constraint.
    $message = PersonNameSchema::make()->toRuleSet()->check([])->errors()->first('first_name');

    expect($message)->toBe('Please provide at least one of first name, middle name or last name.');
});

it('names the fields it was actually given in that message', function (): void {
    $message = PersonNameSchema::make('given_name', 'family_name')
        ->toRuleSet()
        ->check([])
        ->errors()
        ->first('given_name');

    expect($message)->toBe('Please provide at least one of given name or family name.');
});

it('takes a custom message for the requirement', function (): void {
    $message = PersonNameSchema::make()
        ->requireAtLeastOne('We need something to call you.')
        ->toRuleSet()
        ->check([])
        ->errors()
        ->first('first_name');

    expect($message)->toBe('We need something to call you.');
});

// =========================================================================
// Any number of fields — the reason this is a schema and not three columns
// =========================================================================

it('works with a field set that is not first/middle/last', function (): void {
    // The reason this is a schema and not three columns. A person with four
    // given names, or a patronymic, or one name, is not an edge case — the
    // three-column split is a guess about a naming culture.
    $shapes = [
        'two fields' => [['given_name', 'family_name'], ['family_name' => 'Mokoena']],
        'four fields' => [
            ['first_name', 'second_name', 'third_name', 'last_name'],
            ['second_name' => 'Byron', 'last_name' => 'Lovelace'],
        ],
        'a patronymic' => [
            ['given_name', 'patronymic', 'family_name'],
            ['given_name' => 'Ivan', 'patronymic' => 'Ivanovich', 'family_name' => 'Ivanov'],
        ],
    ];

    foreach ($shapes as $label => [$fields, $data]) {
        $schema = PersonNameSchema::make(...$fields);
        $result = $schema->toRuleSet()->check($schema->normalise($data));

        expect($schema->fields())->toBe($fields)
            ->and($result->passes())->toBeTrue("{$label}: " . ($result->errors()->first() ?? ''));
    }
});

it('still requires a name when there is only one field to put it in', function (): void {
    // `required_without_all` with nothing to refer to is not a weaker rule, it
    // is a broken one — so a single-field schema uses `required` instead.
    $schema = PersonNameSchema::make('full_name');

    expect($schema->toRuleSet()->check([])->passes())->toBeFalse()
        ->and($schema->toRuleSet()->check(['full_name' => 'Ada Lovelace'])->passes())->toBeTrue();
});

it('normalises exactly the fields it was declared with', function (): void {
    expect(PersonNameSchema::make('given_name', 'family_name')->normalise([
        'given_name' => '  Ada  ',
        'family_name' => '   ',
        'email' => 'ignored@example.test',
    ]))->toBe([
        'given_name' => 'Ada',
        'family_name' => null,
    ]);
});

it('fills every declared field even when the payload omits them', function (): void {
    // A partial row is how an untouched optional input becomes '' in the
    // database: present, not null, sorting before every real value.
    expect(PersonNameSchema::make()->normalise([]))->toBe([
        'first_name' => null,
        'middle_name' => null,
        'last_name' => null,
    ]);
});

it('refuses a field declared twice', function (): void {
    PersonNameSchema::make('first_name', 'first_name');
})->throws(InvalidArgumentException::class);

// =========================================================================
// Multiple names in ONE field
// =========================================================================

it('accepts several names in one field by default', function (string $value): void {
    // The default that matters: a person with three given names types them
    // into the one box the form offers.
    expect(PersonNameSchema::make()->toRuleSet()->check(['first_name' => $value])->passes())
        ->toBeTrue();
})->with([
    'two' => ['Ada Byron'],
    'three' => ['Ada Byron Gordon'],
    'doubled space' => ['Ada  Byron'],
    'compound family name' => ['Ait Ben Haddou'],
    'hyphenated' => ['Jean-Luc'],
    'apostrophe' => ["O'Neill"],
    'non-latin' => ['李 明'],
]);

it('can insist on exactly one name per field', function (): void {
    $schema = PersonNameSchema::make()->singleNamePerField();

    expect($schema->toRuleSet()->check(['first_name' => 'Ada'])->passes())->toBeTrue()
        ->and($schema->toRuleSet()->check(['first_name' => 'Ada Byron'])->passes())->toBeFalse();
});

it('can insist on a minimum, for a single full-name field', function (): void {
    $schema = PersonNameSchema::make('full_name')->names(min: 2);

    expect($schema->toRuleSet()->check(['full_name' => 'Ada Lovelace'])->passes())->toBeTrue()
        ->and($schema->toRuleSet()->check(['full_name' => 'Ada'])->passes())->toBeFalse();
});

it('says the count is wrong rather than blaming the characters', function (): void {
    $message = PersonNameSchema::make('full_name')
        ->names(min: 2)
        ->toRuleSet()
        ->check(['full_name' => 'Ada'])
        ->errors()
        ->first('full_name');

    expect($message)->toBe('The full name must contain at least 2 names.');
});

// =========================================================================
// The list form
// =========================================================================

it('validates a name held as an unbounded list', function (): void {
    $schema = PersonNameSchema::list('names');

    expect($schema->toRuleSet()->check(['names' => ['Ada', 'Byron', 'Lovelace']])->passes())->toBeTrue()
        ->and($schema->toRuleSet()->check(['names' => []])->passes())->toBeFalse()
        ->and($schema->toRuleSet()->check([])->passes())->toBeFalse()
        ->and($schema->toRuleSet()->check(['names' => ['Ada', '4chan']])->passes())->toBeFalse();
});

it('caps how many list entries one submission may carry', function (): void {
    $schema = PersonNameSchema::list('names', max: 3);

    expect($schema->toRuleSet()->check(['names' => ['A', 'B', 'C']])->passes())->toBeTrue()
        ->and($schema->toRuleSet()->check(['names' => ['A', 'B', 'C', 'D']])->passes())->toBeFalse();
});

it('drops blanks when normalising a list', function (): void {
    expect(PersonNameSchema::list()->normaliseList(['  Ada ', '', '   ', 'Lovelace']))
        ->toBe(['Ada', 'Lovelace']);
});

// =========================================================================
// Character checking
// =========================================================================

it('refuses input that is not a name at all', function (string $value): void {
    expect(PersonNameSchema::make()->toRuleSet()->check(['first_name' => $value])->passes())
        ->toBeFalse();
})->with([
    'digits' => ['Henry 8'],
    'markup' => ['<script>'],
    'an email' => ['ada@example.test'],
    'punctuation only' => ["'-."],
]);

it('can be told to accept digits', function (): void {
    expect(PersonNameSchema::make()->allowDigits()->toRuleSet()->check(['first_name' => 'Henry 8'])->passes())
        ->toBeTrue();
});

it('can drop the character check for a table whose rows would not pass it', function (): void {
    expect(PersonNameSchema::make()->withoutCharacterCheck()->toRuleSet()->check(['first_name' => 'Henry 8'])->passes())
        ->toBeTrue();
});

it('still limits length', function (): void {
    $long = str_repeat('a', 256);

    foreach (PersonNameSchema::make()->fields() as $field) {
        expect(PersonNameSchema::make()->toRuleSet()->check([$field => $long])->passes())
            ->toBeFalse("{$field} accepted 256 characters");
    }
});

// =========================================================================
// Presence modes
// =========================================================================

it('can require every field', function (): void {
    $schema = PersonNameSchema::make('given_name', 'family_name')->requireAll();

    expect($schema->toRuleSet()->check(['given_name' => 'Ada'])->passes())->toBeFalse()
        ->and($schema->toRuleSet()->check(['given_name' => 'Ada', 'family_name' => 'Lovelace'])->passes())->toBeTrue();
});

it('can require nothing, for a partial update', function (): void {
    expect(PersonNameSchema::make()->optional()->toRuleSet()->check([])->passes())->toBeTrue();
});

// =========================================================================
// The schema is a value, and composes
// =========================================================================

it('does not let one caller mutate a shared schema', function (): void {
    $base = PersonNameSchema::make();
    $strict = $base->singleNamePerField();

    expect($base->toRuleSet()->check(['first_name' => 'Ada Byron'])->passes())->toBeTrue()
        ->and($strict->toRuleSet()->check(['first_name' => 'Ada Byron'])->passes())->toBeFalse();
});

it('merges into a wider rule set', function (): void {
    $rules = PersonNameSchema::make('given_name', 'family_name')
        ->toRuleSet()
        ->merge(['email' => 'required|email']);

    expect($rules->check(['given_name' => 'Ada', 'email' => 'ada@example.test'])->passes())->toBeTrue()
        ->and($rules->check(['given_name' => 'Ada'])->passes())->toBeFalse();
});

// =========================================================================
// The rule underneath
// =========================================================================

it('advertises a browser form that agrees with the PHP check', function (int $min, ?int $max, bool $digits): void {
    /*
     * The contract is EXACT equivalence, so the advertised patterns are run
     * against the rule they claim to reproduce rather than against a belief
     * about what they match — over every bound the rule can be built with, not
     * just one. An earlier version of this test pinned a single (1, 2) pair,
     * and (1, anything) never reaches the minimum branch at all: the bound
     * could be replaced with a constant and the suite stayed green.
     */
    $values = [
        'Ada', 'Ada Byron', 'Ada Byron Gordon', 'Ada Byron Gordon King', ' Ada ', 'Ada  Byron',
        "O'Neill", 'Jean-Luc', 'Müller', '李 明', 'Henry 8', 'Henry 8 Tudor', '<script>', "'-.",
        '', '   ', 'ada@example.test',
    ];

    $rule = new PersonName($digits, $min, $max);

    foreach ($values as $value) {
        $advertised = array_all(
            $rule->clientRules(),
            static fn (array $client): bool => preg_match((string) $client['params']['pattern'], $value) === 1,
        );

        expect($advertised)->toBe(
            PersonName::passes($value, $digits, $min, $max),
            sprintf('(%d, %s, digits=%s) disagreed on "%s"', $min, $max ?? 'null', $digits ? 'yes' : 'no', $value),
        );
    }
})->with([
    'unbounded' => [1, null, false],
    'single' => [1, 1, false],
    'at most two' => [1, 2, false],
    'at least two' => [2, null, false],
    'at least three' => [3, null, false],
    'between two and three' => [2, 3, false],
    'digits allowed' => [1, null, true],
    'digits allowed, bounded' => [2, 3, true],
]);

it('shows a sentence rather than a raw key if the namespace is not registered', function (): void {
    // `trans()` hands the key back when the translation namespace is missing,
    // and a stale bootstrap/cache/packages.php in a consuming application is
    // enough to cause it. Every other message in this package goes out through
    // `$fail(...)->translate()`; this one is resolved at rule-build time, so it
    // is the one that could put "laranail-validation::validation.…" on a form.
    // A translator with an empty ArrayLoader has no namespaces at all, so
    // trans() hands every key straight back — the same thing a consuming
    // application sees when the package's provider has not been discovered.
    app()->instance('translator', new Translator(new ArrayLoader(), 'en'));

    [, $messages] = RuleSet::compileWithMetadata(
        PersonNameSchema::make('given_name', 'family_name')->rules(),
    );

    $message = $messages['given_name.required_without_all'] ?? '';

    expect($message)->not->toContain('laranail-validation::')
        ->and($message)->toBe('Please provide at least one of given name or family name.');
});
