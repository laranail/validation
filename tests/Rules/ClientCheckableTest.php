<?php declare(strict_types=1);

use Illuminate\Support\Facades\Validator;
use Simtabi\Laranail\Validation\Contracts\ClientCheckable;
use Simtabi\Laranail\Validation\Rules\Colour\CssColor;
use Simtabi\Laranail\Validation\Rules\Geo\Latitude;
use Simtabi\Laranail\Validation\Rules\Geo\Longitude;
use Simtabi\Laranail\Validation\Rules\Postal\PostalCode;
use Simtabi\Laranail\Validation\Rules\Text\CaseStyle;
use Simtabi\Laranail\Validation\Rules\Vendor\VendorIdentifier;

/**
 * A rule that advertises a browser-equivalent form is making a promise: run
 * this instead and get the same answer. These tests check the promise holds,
 * because the failure it prevents is the one client-side validation exists to
 * avoid — a green tick in the browser for input the server rejects.
 */

/**
 * Rules that cannot be constructed without arguments, and one set that works.
 *
 * Kept beside the discovery below so a new implementation with a required
 * argument fails loudly here rather than being quietly skipped.
 *
 * @return array<class-string, list<mixed>>
 */
function clientCheckableArguments(): array
{
    return [
        CaseStyle::class => [CaseStyle::KEBAB],
        VendorIdentifier::class => [VendorIdentifier::AWS_REGION],
    ];
}

/** @param  class-string  $class */
function makeClientCheckable(string $class): ClientCheckable
{
    $rule = new $class(...(clientCheckableArguments()[$class] ?? []));

    // Narrowed here rather than at each call site: without it every
    // clientRules() below is mixed, and the analyser cannot check any of the
    // shapes this file exists to check.
    expect($rule)->toBeInstanceOf(ClientCheckable::class);

    /** @var ClientCheckable $rule */
    return $rule;
}

/** @return list<class-string> */
function clientCheckableRules(): array
{
    return ruleClassesUnder(ClientCheckable::class);
}

it('advertises only rules the browser runner implements', function (): void {
    // The contract is not a place to invent rule names: the runner has a fixed
    // set, and a name it does not know is ignored, so the rule would route to
    // the server while appearing to have a browser form.
    $runnerKnows = [
        'regex', 'not_regex', 'numeric', 'integer', 'string', 'between', 'min', 'max',
        'size', 'in', 'not_in', 'starts_with', 'ends_with', 'digits', 'digits_between',
    ];

    foreach (clientCheckableRules() as $class) {
        foreach (makeClientCheckable($class)->clientRules() as $advertised) {
            expect($advertised['rule'])->toBeIn($runnerKnows, $class);
        }
    }
});

/**
 * The advertised rules as a native Laravel rule string.
 *
 * @param  list<array{rule: string, params: array<array-key, string>}>  $advertised
 * @return list<string>
 */
function asLaravelRules(array $advertised): array
{
    return array_map(static function (array $rule): string {
        $params = array_values($rule['params']);

        if ($params === []) {
            return $rule['rule'];
        }

        // Laravel's parser special-cases regex and does NOT split its
        // parameter on commas, which matters because a pattern routinely
        // contains one.
        return $rule['rule'] . ':' . implode(',', $params);
    }, $advertised);
}

it('gives the same verdict as the rule itself', function (): void {
    // The guard that makes the contract safe, and it evaluates the advertised
    // rules through LARAVEL'S OWN validator rather than matching a pattern by
    // hand. That is what lets it judge a multi-rule advertisement such as
    // Latitude's `numeric` + `between`, and it compares the thing that will
    // actually run rather than an approximation of it.
    $grid = [
        '', ' ', 'abc', 'ABC', 'abc-def', 'abc--def', '-abc', 'abc-', 'a b', "a\tb", "a\u{200B}b",
        '1.0.0', '1.0.0-alpha.1', '1.0', 'v1.0.0', '0x' . str_repeat('a', 40), '0x' . str_repeat('g', 40),
        'sub.domain', 'subdomain', str_repeat('a', 64), 'a_b', 'café', '123',
        'kebab-case', 'camelCase', 'PascalCase', 'snake_case',
        '12.34', '12.345', '-12.00', '+3', '1e3', '$12', '1,234.50',
        'us-east-1', 'US-EAST-1', 'G-ABCDE12345', 'g-abcde12345', 'common',
        'ab', 'abc', 'a__b', str_repeat('a', 33), '90210', '9021', 'SW1A 1AA',
        // Geo: the boundaries, just past them, and the shapes is_numeric takes.
        '0', '90', '-90', '90.1', '-90.1', '180', '-180', '180.1', '45.5', '1e2', '0x1A', 'Infinity',
        // Colour.
        '#fff', '#ffff', '#fffff', 'red', 'rebeccapurple', 'transparent', 'notacolour',
        'rgb(1,2,3)', 'rgb(300,0,0)', 'hsl(120, 50%, 50%)', 'hsv(1,2,3)',
    ];

    $mismatches = [];

    foreach (clientCheckableRules() as $class) {
        $rule = makeClientCheckable($class);
        $advertised = $rule->clientRules();

        if ($advertised === []) {
            continue;
        }

        $asRules = asLaravelRules($advertised);

        foreach ($grid as $value) {
            // The rule short-circuits on a blank value the way Laravel does,
            // and so would the advertised rules; comparing blanks would test
            // the framework, not the advertisement.
            if (trim($value) === '') {
                continue;
            }

            $ruleSaysPass = ruleAccepts($rule, $value);
            $advertisedSaysPass = Validator::make(['v' => $value], ['v' => $asRules])->passes();

            if ($ruleSaysPass !== $advertisedSaysPass) {
                $mismatches[] = sprintf('%s on %s: rule=%s advertised(%s)=%s',
                    class_basename($class), var_export($value, true),
                    var_export($ruleSaysPass, true), implode('|', $asRules),
                    var_export($advertisedSaysPass, true));
            }
        }
    }

    expect($mismatches)->toBeEmpty(implode('; ', $mismatches));
});

it('is implemented only where the browser form is exactly equivalent', function (): void {
    // A checksum rule must never advertise a shape-only pattern: it would pass
    // a mistyped account number in the browser and fail it on the server.
    //
    // Asked of the DISCOVERED set rather than with is_a() on literal class
    // names — the analyser can fold those to a constant and the assertion
    // stops meaning anything.
    $mustNotAdvertise = [
        'Banking\\Iban', 'Banking\\Luhn', 'Banking\\Isin', 'Identifiers\\Imei',
        'Identifiers\\Vin', 'Codes\\Isbn', 'Codes\\Gtin', 'Crypto\\BitcoinAddress',
        'Fiscal\\NationalIdentifier', 'Database\\Authorized', 'Database\\ModelsExist',
        'Network\\DeliverableEmail',
    ];

    $advertising = clientCheckableRules();
    $offenders = [];

    foreach ($mustNotAdvertise as $suffix) {
        $class = 'Simtabi\\Laranail\\Validation\\Rules\\' . $suffix;

        expect(class_exists($class))->toBeTrue("{$class} no longer exists — update this list");

        if (in_array($class, $advertising, true)) {
            $offenders[] = $suffix;
        }
    }

    expect($offenders)->toBeEmpty(
        'performs a checksum, a query or IO but advertises a browser form: ' . implode(', ', $offenders),
    );
});

it('has at least one implementation, so the contract is not decorative', function (): void {
    expect(clientCheckableRules())->not->toBeEmpty();
});

it('advertises nothing when the country is only known at validation time', function (): void {
    // A reference() instance takes its country from a sibling field, so which
    // pattern applies cannot be decided while exporting. Advertising one
    // would mean picking a country at random.
    $referenced = PostalCode::reference('country');

    expect($referenced->clientRules())->toBeEmpty()
        ->and(new PostalCode(['US'])->clientRules())->not->toBeEmpty();
});

it('combines several countries without one pattern’s flags leaking onto another', function (): void {
    // GB and CA are case-insensitive, US is not. Concatenating them naively
    // would either lose a flag or apply it to all three.
    $advertised = new PostalCode(['US', 'CA'])->clientRules();

    expect($advertised)->toHaveCount(1);

    foreach (['90210' => true, 'K1A 0B1' => true, 'k1a 0b1' => true, 'nonsense' => false] as $value => $expected) {
        expect(preg_match($advertised[0]['params']['pattern'], (string) $value) === 1)->toBe($expected, (string) $value);
    }
});

it('expresses a numeric range as numeric plus between, not as a regex', function (string $class, string $bound): void {
    // The reason the contract returns a list. A bounded numeric range can be
    // contorted into a pattern, but it is unreadable, has to be rewritten per
    // bound, and getting the boundary wrong means disagreeing with the server
    // on exactly the values that matter.
    $advertised = new $class()->clientRules();

    expect(array_column($advertised, 'rule'))->toBe(['numeric', 'between'])
        ->and($advertised[1]['params'])->toBe(['min' => "-{$bound}", 'max' => $bound]);
})->with([
    [Latitude::class, '90'],
    [Longitude::class, '180'],
]);

it('covers the named colours rather than omitting them', function (): void {
    // Leaving them out would mean a browser rejecting `red`. 150 literal names
    // is about 2 KB of pattern, in a package that ships an 8,201-entry domain
    // list — the size was never the real objection.
    $pattern = new CssColor()->clientRules()[0]['params']['pattern'];

    foreach (['red', 'rebeccapurple', 'transparent', 'currentcolor', '#fff', 'rgb(1,2,3)'] as $value) {
        expect(preg_match($pattern, $value))->toBe(1, $value);
    }

    expect(preg_match($pattern, 'notacolour'))->toBe(0);
});

it('declares the interface on every rule that has the method', function (): void {
    // Discovery here is by INTERFACE, so a rule carrying clientRules() without
    // declaring ClientCheckable is invisible to every other test in this file:
    // it looks implemented, is never checked against its own rule, and is
    // never exported. Latitude and Longitude were in exactly that state —
    // the method was added and the implements clause was not, and nothing
    // failed.
    $undeclared = [];

    foreach (ruleClassesUnder() as $class) {
        if (! method_exists($class, 'clientRules')) {
            continue;
        }

        if (! is_a($class, ClientCheckable::class, true)) {
            $undeclared[] = $class;
        }
    }

    expect($undeclared)->toBeEmpty('has clientRules() but does not implement ClientCheckable: ' . implode(', ', $undeclared));
});
