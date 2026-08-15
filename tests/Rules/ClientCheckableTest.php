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
use Simtabi\Laranail\Validation\Rules\Identifiers\Imei;
use Simtabi\Laranail\Validation\Rules\Identifiers\Vin;
use Simtabi\Laranail\Validation\Rules\Network\DeliverableEmail;

/**
 * A rule that advertises a browser-equivalent form is making a promise: run
 * this instead and get the same answer. These tests check the promise holds,
 * because the failure it prevents is the one client-side validation exists to
 * avoid — a green tick in the browser for input the server rejects.
 */

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
        $advertised = new $class()->clientRule();

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
    ];

    $mismatches = [];

    foreach (clientCheckableRules() as $class) {
        $rule = new $class();
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
