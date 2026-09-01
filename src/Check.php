<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Validation;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\InvokableValidationRule;
use Simtabi\Laranail\Validation\Rules\Banking\Bic;
use Simtabi\Laranail\Validation\Rules\Banking\Iban;
use Simtabi\Laranail\Validation\Rules\Banking\Isin;
use Simtabi\Laranail\Validation\Rules\Banking\Luhn;
use Simtabi\Laranail\Validation\Rules\Codes\Ean;
use Simtabi\Laranail\Validation\Rules\Codes\Isbn;
use Simtabi\Laranail\Validation\Rules\Codes\Issn;
use Simtabi\Laranail\Validation\Rules\Colour\CssColor;
use Simtabi\Laranail\Validation\Rules\Geo\Latitude;
use Simtabi\Laranail\Validation\Rules\Geo\Longitude;
use Simtabi\Laranail\Validation\Rules\Identifiers\Imei;
use Simtabi\Laranail\Validation\Rules\Identifiers\Jwt;
use Simtabi\Laranail\Validation\Rules\Identifiers\SemVer;
use Simtabi\Laranail\Validation\Rules\Identifiers\Vin;
use Simtabi\Laranail\Validation\Rules\Net\Cidr;
use Simtabi\Laranail\Validation\Rules\Net\DomainName;
use Simtabi\Laranail\Validation\Rules\Net\MacAddress;
use Simtabi\Laranail\Validation\Rules\Net\Subdomain;
use Simtabi\Laranail\Validation\Rules\Numbers\MonetaryAmount;
use Simtabi\Laranail\Validation\Rules\Postal\PostalCode;
use Simtabi\Laranail\Validation\Rules\Text\PersonName;
use Simtabi\Laranail\Validation\Rules\Text\Slug;
use Simtabi\Laranail\Validation\Rules\Text\Username;

/**
 * One-off boolean guards over the rule library — `Check::iban($v)` where
 * building a validator would be ceremony: a guard clause, a collection
 * filter, an import row triage.
 *
 * Every method is EXPLICIT — no `__callStatic` — so an IDE completes them,
 * static analysis sees them, and a typo is a compile-time unknown method
 * instead of a runtime guess. That is the difference from the legacy
 * `is*()` magic this recovers.
 *
 * The named guards cover the pure tier only: rules that read nothing but
 * the value. Database, network and IO rules deliberately have no entry —
 * a boolean that quietly performed a query or a DNS lookup would be the
 * footgun this class exists to avoid. {@see rule()} runs any rule object
 * when you knowingly want one.
 */
final class Check
{
    private function __construct() {}

    public static function iban(mixed $value): bool
    {
        return self::rule(new Iban, $value);
    }

    public static function bic(mixed $value): bool
    {
        return self::rule(new Bic, $value);
    }

    public static function isin(mixed $value): bool
    {
        return self::rule(new Isin, $value);
    }

    public static function luhn(mixed $value): bool
    {
        return self::rule(new Luhn, $value);
    }

    public static function ean(mixed $value): bool
    {
        return self::rule(new Ean, $value);
    }

    public static function isbn(mixed $value): bool
    {
        return self::rule(new Isbn, $value);
    }

    public static function issn(mixed $value): bool
    {
        return self::rule(new Issn, $value);
    }

    public static function imei(mixed $value): bool
    {
        return self::rule(new Imei, $value);
    }

    public static function vin(mixed $value): bool
    {
        return self::rule(new Vin, $value);
    }

    public static function jwt(mixed $value): bool
    {
        return self::rule(new Jwt, $value);
    }

    public static function semVer(mixed $value): bool
    {
        return self::rule(new SemVer, $value);
    }

    public static function slug(mixed $value): bool
    {
        return self::rule(new Slug, $value);
    }

    public static function username(mixed $value, int $min = 3, int $max = 32): bool
    {
        return self::rule(new Username($min, $max), $value);
    }

    public static function personName(mixed $value): bool
    {
        return self::rule(new PersonName, $value);
    }

    public static function cssColor(mixed $value): bool
    {
        return self::rule(new CssColor, $value);
    }

    public static function latitude(mixed $value): bool
    {
        return self::rule(new Latitude, $value);
    }

    public static function longitude(mixed $value): bool
    {
        return self::rule(new Longitude, $value);
    }

    public static function postalCode(mixed $value, string $country): bool
    {
        return self::rule(new PostalCode($country), $value);
    }

    public static function monetaryAmount(mixed $value, int $decimals = 2, bool $allowNegative = false): bool
    {
        return self::rule(new MonetaryAmount($decimals, $allowNegative), $value);
    }

    public static function macAddress(mixed $value): bool
    {
        return self::rule(new MacAddress, $value);
    }

    public static function domainName(mixed $value, bool $requireTld = true): bool
    {
        return self::rule(new DomainName($requireTld), $value);
    }

    public static function subdomain(mixed $value): bool
    {
        return self::rule(new Subdomain, $value);
    }

    public static function cidr(mixed $value): bool
    {
        return self::rule(new Cidr, $value);
    }

    /**
     * Whether the value matches a pattern, under the same contract as
     * {@see Builder\Nodes\StringRule::matches()}: an undelimited pattern
     * gains delimiters and `D`; a delimited one is used verbatim.
     */
    public static function regex(mixed $value, string|Regex|Closure $pattern): bool
    {
        $compiled = is_string($pattern) ? Regex::of($pattern)->compile() : self::compilePattern($pattern);

        return (is_string($value) || is_numeric($value))
            && preg_match($compiled, (string) $value) === 1;
    }

    /**
     * Run ANY rule object as a boolean — the escape hatch for rules without
     * a named guard, including your own. The caller owns the consequences
     * of handing this a rule that queries or does IO.
     */
    public static function rule(ValidationRule $rule, mixed $value): bool
    {
        $invokable = InvokableValidationRule::make($rule);
        $invokable->setValidator(Validator::make([], []));

        return $invokable->passes('value', $value);
    }

    /** @param  Regex|Closure(Regex): Regex  $pattern */
    private static function compilePattern(Regex|Closure $pattern): string
    {
        if ($pattern instanceof Closure) {
            return $pattern(Regex::build())->compile();
        }

        return $pattern->compile();
    }
}
