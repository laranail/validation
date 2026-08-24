<?php declare(strict_types=1);

use Simtabi\Laranail\Validation\Contracts\I18n\CountryDataset;
use Simtabi\Laranail\Validation\Contracts\I18n\CurrencyDataset;
use Simtabi\Laranail\Validation\Contracts\I18n\LanguageDataset;
use Simtabi\Laranail\Validation\Rules\I18n\CountryCode;
use Simtabi\Laranail\Validation\Rules\I18n\CurrencyCode;
use Simtabi\Laranail\Validation\Rules\I18n\LanguageCode;
use Symfony\Component\Process\PhpExecutableFinder;
use Symfony\Component\Process\Process;

// =========================================================================
// CountryCode — ISO 3166-1, alpha-2 by default, alpha-3 by flag
// =========================================================================

it('accepts assigned alpha-2 country codes', function (string $value): void {
    expect(ruleAccepts(new CountryCode(), $value))->toBeTrue();
})->with(['US', 'KE', 'DE', 'XK']);

it('rejects unassigned, lowercase and alpha-3 values in alpha-2 mode', function (mixed $value): void {
    // Empty strings are absent from this list on purpose: Laravel skips
    // non-implicit rules on '' — presence is `required`'s job, not this rule's.
    expect(ruleAccepts(new CountryCode(), $value))->toBeFalse();
})->with(['AA', 'us', 'USA', 'U', 12, null, [['US']]]);

it('accepts assigned alpha-3 codes only in alpha-3 mode', function (): void {
    expect(ruleAccepts(new CountryCode(alpha3: true), 'KEN'))->toBeTrue()
        ->and(ruleAccepts(new CountryCode(alpha3: true), 'KE'))->toBeFalse()
        ->and(ruleAccepts(new CountryCode(alpha3: true), 'XXX'))->toBeFalse();
});

it('folds case only when asked to', function (): void {
    expect(ruleAccepts(new CountryCode(caseInsensitive: true), 'us'))->toBeTrue()
        ->and(ruleAccepts(new CountryCode(alpha3: true, caseInsensitive: true), 'ken'))->toBeTrue();
});

it('has no alpha-3 for the user-assigned XK', function (): void {
    // XK (Kosovo) is user-assigned, kept in alpha-2 because real address data
    // needs it; ISO assigns it no alpha-3, so none is accepted.
    expect(ruleAccepts(new CountryCode(alpha3: true), 'UNK'))->toBeFalse()
        ->and(ruleAccepts(new CountryCode(alpha3: true), 'XKX'))->toBeFalse();
});

it('honours a bound CountryDataset', function (): void {
    $this->app->instance(CountryDataset::class, new class implements CountryDataset {
        public function isAlpha2(string $code): bool
        {
            return $code === 'ZZ';
        }

        public function isAlpha3(string $code): bool
        {
            return false;
        }
    });

    expect(ruleAccepts(new CountryCode(), 'ZZ'))->toBeTrue()
        ->and(ruleAccepts(new CountryCode(), 'US'))->toBeFalse();
});

// =========================================================================
// LanguageCode — ISO 639-1, lowercase canonical
// =========================================================================

it('accepts assigned ISO 639-1 codes', function (string $value): void {
    expect(ruleAccepts(new LanguageCode(), $value))->toBeTrue();
})->with(['en', 'sw', 'de', 'zh']);

it('rejects unassigned, uppercase and three-letter values', function (mixed $value): void {
    expect(ruleAccepts(new LanguageCode(), $value))->toBeFalse();
})->with(['EN', 'eng', 'xx', 3, null]);

it('folds language case only when asked to', function (): void {
    expect(ruleAccepts(new LanguageCode(caseInsensitive: true), 'EN'))->toBeTrue();
});

it('honours a bound LanguageDataset', function (): void {
    $this->app->instance(LanguageDataset::class, new class implements LanguageDataset {
        public function isAlpha2(string $code): bool
        {
            return $code === 'zz';
        }
    });

    expect(ruleAccepts(new LanguageCode(), 'zz'))->toBeTrue()
        ->and(ruleAccepts(new LanguageCode(), 'en'))->toBeFalse();
});

// =========================================================================
// CurrencyCode — ISO 4217 alpha / numeric / symbol
// =========================================================================

it('accepts current alpha currency codes, including the 2016-2024 replacements', function (string $value): void {
    expect(ruleAccepts(new CurrencyCode(), $value))->toBeTrue();
})->with(['USD', 'EUR', 'KES', 'BYN', 'MRU', 'STN', 'VES', 'SLE', 'ZWG', 'XAU', 'XXX']);

it('rejects retired and unassigned alpha codes', function (mixed $value): void {
    // HRK (Croatia -> euro, 2023), SLL (redenominated, withdrawn 2023) and
    // ZWL (replaced by ZWG, 2024) are exactly what a stale list would accept.
    expect(ruleAccepts(new CurrencyCode(), $value))->toBeFalse();
})->with(['HRK', 'SLL', 'ZWL', 'usd', 'ABC', '840', 840, null]);

it('validates numeric codes in numeric mode', function (): void {
    expect(ruleAccepts(new CurrencyCode(numeric: true), '840'))->toBeTrue()
        ->and(ruleAccepts(new CurrencyCode(numeric: true), '997'))->toBeTrue()   // USN, a funds code
        ->and(ruleAccepts(new CurrencyCode(numeric: true), 'USD'))->toBeFalse()
        ->and(ruleAccepts(new CurrencyCode(numeric: true), '000'))->toBeFalse();
});

it('validates symbols in symbol mode', function (): void {
    expect(ruleAccepts(new CurrencyCode(symbol: true), '€'))->toBeTrue()
        ->and(ruleAccepts(new CurrencyCode(symbol: true), '£'))->toBeTrue()
        ->and(ruleAccepts(new CurrencyCode(symbol: true), 'EUR'))->toBeFalse();
});

it('refuses numeric and symbol together', function (): void {
    new CurrencyCode(numeric: true, symbol: true);
})->throws(LogicException::class);

it('folds currency case only when asked to', function (): void {
    expect(ruleAccepts(new CurrencyCode(caseInsensitive: true), 'usd'))->toBeTrue();
});

it('honours a bound CurrencyDataset', function (): void {
    $this->app->instance(CurrencyDataset::class, new class implements CurrencyDataset {
        public function isCode(string $code): bool
        {
            return $code === 'ZZZ';
        }

        public function isNumericCode(string $code): bool
        {
            return false;
        }

        public function isSymbol(string $symbol): bool
        {
            return false;
        }
    });

    expect(ruleAccepts(new CurrencyCode(), 'ZZZ'))->toBeTrue()
        ->and(ruleAccepts(new CurrencyCode(), 'USD'))->toBeFalse();
});

// =========================================================================
// The bundled datasets — counts pinned to the generated files
// =========================================================================

it('keeps the generated datasets in step with their committed sources', function (): void {
    $php = (string) (new PhpExecutableFinder())->find();
    expect($php)->not->toBe('');

    $process = new Process(
        [$php, dirname(__DIR__, 3) . '/tools/build-datasets.php', '--check'],
    );
    $process->run();

    expect($process->getExitCode())->toBe(0, $process->getErrorOutput());
})->skip(! class_exists(Process::class), 'symfony/process not installed');
