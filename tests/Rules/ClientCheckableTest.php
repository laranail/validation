<?php declare(strict_types=1);

use Simtabi\Laranail\Validation\Contracts\ClientCheckable;
use Simtabi\Laranail\Validation\Rules\Banking\Iban;
use Simtabi\Laranail\Validation\Rules\Banking\Isin;
use Simtabi\Laranail\Validation\Rules\Banking\Luhn;
use Simtabi\Laranail\Validation\Rules\Codes\Gtin;
use Simtabi\Laranail\Validation\Rules\Codes\Isbn;
use Simtabi\Laranail\Validation\Rules\Crypto\BitcoinAddress;
use Simtabi\Laranail\Validation\Rules\Database\Authorized;
use Simtabi\Laranail\Validation\Rules\Database\ModelsExist;
use Simtabi\Laranail\Validation\Rules\Fiscal\NationalIdentifier;
use Simtabi\Laranail\Validation\Rules\Geo\Latitude;
use Simtabi\Laranail\Validation\Rules\Geo\Longitude;
use Simtabi\Laranail\Validation\Rules\Identifiers\Imei;
use Simtabi\Laranail\Validation\Rules\Identifiers\Vin;
use Simtabi\Laranail\Validation\Rules\Network\DeliverableEmail;
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
function makeClientCheckable(string $class): object
{
    return new $class(...(clientCheckableArguments()[$class] ?? []));
}

/** @return list<class-string> */
function clientCheckableRules(): array
{
    $found = [];

    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator(dirname(__DIR__, 2) . '/src/Rules')) as $file) {
        if (! $file instanceof SplFileInfo || $file->getExtension() !== 'php') {
            continue;
        }

        $relative = str_replace([dirname(__DIR__, 2) . '/src/Rules/', '/', '.php'], ['', '\\', ''], $file->getPathname());
        $class = 'Simtabi\\Laranail\\Validation\\Rules\\' . $relative;

        if (class_exists($class) && is_a($class, ClientCheckable::class, true)) {
            $found[] = $class;
        }
    }

    sort($found);

    return $found;
}

it('advertises a rule the browser runner actually implements', function (): void {
    // The contract is not a place to invent rule names: the runner has a fixed
    // set, and a name it does not know would silently do nothing.
    $runnerKnows = ['regex', 'not_regex', 'in', 'not_in', 'starts_with', 'ends_with'];

    foreach (clientCheckableRules() as $class) {
        $advertised = makeClientCheckable($class)->clientRule();

        if ($advertised === null) {
            continue;
        }

        expect($advertised['rule'])->toBeIn($runnerKnows, $class);
    }
});

it('gives the same verdict as the rule itself', function (): void {
    // The guard that makes the contract safe. If a rule's own logic changes
    // and its advertised pattern does not, this fails rather than shipping a
    // browser that disagrees with the server.
    $grid = [
        '', ' ', 'abc', 'ABC', 'abc-def', 'abc--def', '-abc', 'abc-', 'a b', "a\tb", "a\u{200B}b",
        '1.0.0', '1.0.0-alpha.1', '1.0', 'v1.0.0', '0x' . str_repeat('a', 40), '0x' . str_repeat('g', 40),
        'sub.domain', 'subdomain', str_repeat('a', 64), 'a_b', 'café', '123',
        // For the parameterised implementations.
        'kebab-case', 'camelCase', 'PascalCase', 'snake_case',
        '12.34', '12.345', '-12.00', '+3', '1e3', '$12', '1,234.50',
        'us-east-1', 'US-EAST-1', 'G-ABCDE12345', 'g-abcde12345', 'common',
        'ab', 'abc', 'a__b', str_repeat('a', 33), '90210', '9021', 'SW1A 1AA',
    ];

    $mismatches = [];

    foreach (clientCheckableRules() as $class) {
        $rule = makeClientCheckable($class);
        $advertised = $rule->clientRule();

        if ($advertised === null) {
            continue;
        }

        foreach ($grid as $value) {
            $ruleSaysPass = ruleAccepts($rule, $value);

            $matches = preg_match($advertised['params']['pattern'], $value) === 1;
            $patternSaysPass = $advertised['rule'] === 'not_regex' ? ! $matches : $matches;

            // The rule short-circuits on a blank value the way Laravel does;
            // the pattern alone has no such notion, so blanks are compared
            // only through the rule.
            if (trim($value) === '') {
                continue;
            }

            if ($ruleSaysPass !== $patternSaysPass) {
                $mismatches[] = sprintf('%s on %s: rule=%s pattern=%s',
                    class_basename($class), var_export($value, true),
                    var_export($ruleSaysPass, true), var_export($patternSaysPass, true));
            }
        }
    }

    expect($mismatches)->toBeEmpty(implode('; ', $mismatches));
});

it('is implemented only where the browser form is exactly equivalent', function (): void {
    // A checksum rule must never advertise a shape-only pattern: it would pass
    // a mistyped account number in the browser and fail it on the server.
    $mustNotBeClientCheckable = [
        Iban::class,
        Luhn::class,
        Isin::class,
        Imei::class,
        Vin::class,
        Isbn::class,
        Gtin::class,
        BitcoinAddress::class,
        NationalIdentifier::class,
        Authorized::class,
        ModelsExist::class,
        DeliverableEmail::class,
    ];

    foreach ($mustNotBeClientCheckable as $class) {
        expect(is_a($class, ClientCheckable::class, true))
            ->toBeFalse("{$class} performs a checksum, a query or IO and must not advertise a browser form");
    }
});

it('has at least one implementation, so the contract is not decorative', function (): void {
    expect(clientCheckableRules())->not->toBeEmpty();
});

it('advertises nothing when the country is only known at validation time', function (): void {
    // A reference() instance takes its country from a sibling field, so which
    // pattern applies cannot be decided while exporting. Advertising one
    // would mean picking a country at random.
    $referenced = PostalCode::reference('country');

    expect($referenced->clientRule())->toBeNull()
        ->and(new PostalCode(['US'])->clientRule())->not->toBeNull();
});

it('combines several countries without one pattern’s flags leaking onto another', function (): void {
    // GB and CA are case-insensitive, US is not. Concatenating them naively
    // would either lose a flag or apply it to all three.
    $advertised = new PostalCode(['US', 'CA'])->clientRule();

    expect($advertised)->not->toBeNull();

    foreach (['90210' => true, 'K1A 0B1' => true, 'k1a 0b1' => true, 'nonsense' => false] as $value => $expected) {
        expect(preg_match($advertised['params']['pattern'], (string) $value) === 1)->toBe($expected, (string) $value);
    }
});

it('does not advertise a rule whose check is a numeric range', function (): void {
    // Latitude and Longitude compare magnitudes. A regex CAN be contorted into
    // a bounded numeric range, but the result is unreadable and easy to get
    // subtly wrong, and being wrong here means the browser disagreeing with
    // the server. The contract is for rules whose check IS a pattern.
    expect(is_a(Latitude::class, ClientCheckable::class, true))->toBeFalse()
        ->and(is_a(Longitude::class, ClientCheckable::class, true))->toBeFalse();
});
