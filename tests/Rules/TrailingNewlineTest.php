<?php declare(strict_types=1);

use Simtabi\Laranail\Validation\Rules\Banking\Bic;
use Simtabi\Laranail\Validation\Rules\Banking\Iban;
use Simtabi\Laranail\Validation\Rules\Banking\Isin;
use Simtabi\Laranail\Validation\Rules\Codes\Isbn;
use Simtabi\Laranail\Validation\Rules\Codes\Issn;
use Simtabi\Laranail\Validation\Rules\Colour\CssColor;
use Simtabi\Laranail\Validation\Rules\Crypto\EthereumAddress;
use Simtabi\Laranail\Validation\Rules\Fiscal\NationalIdentifier;
use Simtabi\Laranail\Validation\Rules\Identifiers\Jwt;
use Simtabi\Laranail\Validation\Rules\Identifiers\SemVer;
use Simtabi\Laranail\Validation\Rules\Identifiers\Vin;
use Simtabi\Laranail\Validation\Rules\Net\DomainName;
use Simtabi\Laranail\Validation\Rules\Net\MacAddress;
use Simtabi\Laranail\Validation\Rules\Net\Subdomain;
use Simtabi\Laranail\Validation\Rules\Numbers\MonetaryAmount;
use Simtabi\Laranail\Validation\Rules\Numbers\Parity;
use Simtabi\Laranail\Validation\Rules\Postal\PostalCode;
use Simtabi\Laranail\Validation\Rules\Text\CaseStyle;
use Simtabi\Laranail\Validation\Rules\Text\PersonName;
use Simtabi\Laranail\Validation\Rules\Text\Slug;

/**
 * The trailing-newline sweep: every anchored single-line pattern in the rule
 * library must carry the `D` modifier (or `\z`), because a bare `$` in PCRE
 * also matches just before a final "\n". Without it, "my-slug\n" passes the
 * Slug rule that promises a Str::slug() round-trip, "DEUTDEFF\n" is a valid
 * BIC, and every one of these values feeds log/header-injection downstream
 * and can dodge a unique index holding the clean form.
 *
 * Each row asserts the clean value passes (so the case is honest) and the
 * same value with a trailing "\n" fails.
 *
 * @see Username's dedicated tests for the security-graded instance (P6).
 */
it('rejects a trailing newline on an otherwise valid value', function (object $rule, string $valid): void {
    expect(ruleAccepts($rule, $valid))->toBeTrue()
        ->and(ruleAccepts($rule, $valid . "\n"))->toBeFalse();
})->with([
    'Slug' => [fn (): object => new Slug(), 'my-slug'],
    'Jwt' => [fn (): object => new Jwt(), 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJzdWIiOiIxIn0.c2ln'],
    'DomainName' => [fn (): object => new DomainName(), 'example.com'],
    'PersonName' => [fn (): object => new PersonName(), 'Ada Lovelace'],
    'EthereumAddress' => [fn (): object => new EthereumAddress(), '0xabcdefabcdefabcdefabcdefabcdefabcdefabcd'],
    'SemVer' => [fn (): object => new SemVer(), '1.2.3'],
    'Subdomain' => [fn (): object => new Subdomain(), 'blog'],
    'MacAddress' => [fn (): object => new MacAddress(), '00:1a:2b:3c:4d:5e'],
    'Isbn' => [fn (): object => new Isbn(), '0306406152'],
    'Issn' => [fn (): object => new Issn(), '2049-3630'],
    'Vin' => [fn (): object => new Vin(), '11111111111111111'],
    'Iban' => [fn (): object => new Iban(), 'DE89370400440532013000'],
    'Bic' => [fn (): object => new Bic(), 'DEUTDEFF'],
    'Isin' => [fn (): object => new Isin(), 'US0378331005'],
    'CaseStyle (kebab)' => [fn (): object => new CaseStyle('kebab'), 'my-var'],
]);

/**
 * NOT defects: these rules normalize with trim() (or numeric coercion)
 * BEFORE matching, so surrounding whitespace — a copy-paste artifact — is
 * tolerated by explicit design, not by a `$`-before-"\n" pattern accident.
 * Their patterns still carry `D` behind the normalization. Pinned here so
 * the tolerance stays a visible, deliberate contract: if one of these rows
 * starts failing, someone removed the normalization — decide, don't patch.
 */
it('tolerates surrounding whitespace where the rule normalizes by design', function (object $rule, string $valid): void {
    expect(ruleAccepts($rule, $valid))->toBeTrue()
        ->and(ruleAccepts($rule, $valid . "\n"))->toBeTrue()
        ->and(ruleAccepts($rule, ' ' . $valid . ' '))->toBeTrue();
})->with([
    'PostalCode (US)' => [fn (): object => new PostalCode('US'), '12345'],
    'CssColor' => [fn (): object => new CssColor(), '#fff'],
    'MonetaryAmount' => [fn (): object => new MonetaryAmount(), '10.99'],
    'Parity (even)' => [fn (): object => new Parity('even'), '12'],
    'NationalIdentifier (US SSN)' => [fn (): object => new NationalIdentifier('US'), '212-09-4553'],
]);
