<?php declare(strict_types=1);

namespace Simtabi\Laranail\Validation\Support;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;
use Simtabi\Laranail\Validation\Rules\Banking\Bic;
use Simtabi\Laranail\Validation\Rules\Banking\Iban;
use Simtabi\Laranail\Validation\Rules\Banking\Isin;
use Simtabi\Laranail\Validation\Rules\Banking\Luhn;
use Simtabi\Laranail\Validation\Rules\Codes\Ean;
use Simtabi\Laranail\Validation\Rules\Codes\Gtin;
use Simtabi\Laranail\Validation\Rules\Codes\Isbn;
use Simtabi\Laranail\Validation\Rules\Codes\Issn;
use Simtabi\Laranail\Validation\Rules\Crypto\BitcoinAddress;
use Simtabi\Laranail\Validation\Rules\Crypto\EthereumAddress;
use Simtabi\Laranail\Validation\Rules\Database\Authorized;
use Simtabi\Laranail\Validation\Rules\Database\ModelsExist;
use Simtabi\Laranail\Validation\Rules\Email\EmailDomainIs;
use Simtabi\Laranail\Validation\Rules\Email\EmailDomainIsNot;
use Simtabi\Laranail\Validation\Rules\Email\NotDisposableEmail;
use Simtabi\Laranail\Validation\Rules\Email\NotRoleEmail;
use Simtabi\Laranail\Validation\Rules\Geo\CaProvince;
use Simtabi\Laranail\Validation\Rules\Geo\Latitude;
use Simtabi\Laranail\Validation\Rules\Geo\LatLng;
use Simtabi\Laranail\Validation\Rules\Geo\Longitude;
use Simtabi\Laranail\Validation\Rules\Geo\UsState;
use Simtabi\Laranail\Validation\Rules\Identifiers\Imei;
use Simtabi\Laranail\Validation\Rules\Identifiers\Jwt;
use Simtabi\Laranail\Validation\Rules\Identifiers\SemVer;
use Simtabi\Laranail\Validation\Rules\Identifiers\Vin;
use Simtabi\Laranail\Validation\Rules\Net\Cidr;
use Simtabi\Laranail\Validation\Rules\Net\DomainName;
use Simtabi\Laranail\Validation\Rules\Net\PrivateIp;
use Simtabi\Laranail\Validation\Rules\Net\PublicIp;
use Simtabi\Laranail\Validation\Rules\Net\Subdomain;
use Simtabi\Laranail\Validation\Rules\Postal\PostalCode;
use Simtabi\Laranail\Validation\Rules\Structure\Delimited;
use Simtabi\Laranail\Validation\Rules\Text\CaseStyle;
use Simtabi\Laranail\Validation\Rules\Text\HtmlClean;
use Simtabi\Laranail\Validation\Rules\Text\PersonName;
use Simtabi\Laranail\Validation\Rules\Text\Slug;
use Simtabi\Laranail\Validation\Rules\Text\Username;
use Simtabi\Laranail\Validation\Rules\Text\WithoutSpaces;
use Stringable;

/**
 * The alias suffix → rule-factory map behind the opt-in string rules.
 *
 * Why a central map rather than a `fromParameters()` static on each rule: the
 * parameter spelling is a property of the *alias*, not of the rule. A rule
 * constructed in PHP takes typed arguments; the alias has to turn a list of
 * strings from a pipe rule into those arguments, and only the alias layer
 * cares. Keeping the translation here leaves 39 rule classes free of a method
 * that exists solely to serve a feature most applications leave switched off,
 * and puts every parameter spelling in one file that can be read end to end.
 *
 * Each factory receives the raw `:`-separated parameters, already split by
 * Laravel's own parser, and returns the constructed rule.
 *
 * @internal
 */
final class RuleAliases
{
    /**
     * Rules deliberately without an alias, and why.
     *
     * `Delimited` takes a nested rule set, which has no faithful string
     * spelling — `delimited:email|min:3` cannot survive the pipe splitting
     * that produced it. Use the rule object.
     *
     * @var list<class-string<ValidationRule>>
     */
    public const array UNALIASED = [
        Delimited::class,
    ];

    /**
     * @return array<string, Closure(list<string>): ValidationRule>
     */
    public static function map(): array
    {
        return [
            // Banking
            'iban' => static fn (): ValidationRule => new Iban(),
            'bic' => static fn (): ValidationRule => new Bic(),
            'isin' => static fn (): ValidationRule => new Isin(),
            'luhn' => static fn (): ValidationRule => new Luhn(),

            // Codes — the parameters narrow which lengths/editions are accepted.
            'ean' => static fn (): ValidationRule => new Ean(),
            'issn' => static fn (): ValidationRule => new Issn(),
            'isbn' => static fn (array $p): ValidationRule => new Isbn(self::ints($p)),
            'gtin' => static fn (array $p): ValidationRule => new Gtin(self::ints($p)),

            // Identifiers
            'imei' => static fn (): ValidationRule => new Imei(),
            'jwt' => static fn (): ValidationRule => new Jwt(),
            'semver' => static fn (): ValidationRule => new SemVer(),
            'vin' => static fn (array $p): ValidationRule => new Vin(self::bool($p, 0, true)),

            // Geo
            'latitude' => static fn (): ValidationRule => new Latitude(),
            'longitude' => static fn (): ValidationRule => new Longitude(),
            'lat_lng' => static fn (): ValidationRule => new LatLng(),
            'ca_province' => static fn (): ValidationRule => new CaProvince(),
            'us_state' => static fn (array $p): ValidationRule => new UsState(self::bool($p, 0, false)),

            // Postal — `laranail_postal_code:US`, `:US,CA`, or `:@country`
            // to read the country from a sibling field.
            'postal_code' => self::postalCode(...),

            // Net
            'cidr' => static fn (): ValidationRule => new Cidr(),
            'subdomain' => static fn (): ValidationRule => new Subdomain(),
            'public_ip' => static fn (): ValidationRule => new PublicIp(),
            'private_ip' => static fn (): ValidationRule => new PrivateIp(),
            'domain_name' => static fn (array $p): ValidationRule => new DomainName(self::bool($p, 0, true)),

            // Text
            'slug' => static fn (): ValidationRule => new Slug(),
            'html_clean' => static fn (): ValidationRule => new HtmlClean(),
            'without_spaces' => static fn (): ValidationRule => new WithoutSpaces(),
            'case_style' => static fn (array $p): ValidationRule => new CaseStyle(self::str($p, 0)),
            'person_name' => static fn (array $p): ValidationRule => new PersonName(self::bool($p, 0, false)),
            'username' => static fn (array $p): ValidationRule => new Username(self::int($p, 0, 3), self::int($p, 1, 30)),

            // Crypto
            'ethereum_address' => static fn (): ValidationRule => new EthereumAddress(),
            'bitcoin_address' => static fn (array $p): ValidationRule => new BitcoinAddress(self::bool($p, 0, false)),

            // Email — the two list-backed rules resolve their list from the
            // container, so they need no parameter at all.
            'not_disposable_email' => static fn (): ValidationRule => new NotDisposableEmail(),
            'not_role_email' => static fn (): ValidationRule => new NotRoleEmail(),
            'email_domain_is' => static fn (array $p): ValidationRule => new EmailDomainIs(self::strings($p)),
            'email_domain_is_not' => static fn (array $p): ValidationRule => new EmailDomainIsNot(self::strings($p)),

            // Database
            'models_exist' => static fn (array $p): ValidationRule => new ModelsExist(self::model($p, 0), self::nullableStr($p, 1)),
            'authorized' => static fn (array $p): ValidationRule => new Authorized(self::str($p, 0), self::model($p, 1), self::nullableStr($p, 2)),
        ];
    }

    /**
     * `@field` marks a sibling-field reference; anything else is a country.
     *
     * The rule takes the country either as a literal list or as the name of
     * another field to read it from, and a bare parameter cannot express both
     * — `laranail_postal_code:country` is ambiguous between the ISO code of a
     * country and a field called `country`. The sigil resolves it explicitly
     * rather than guessing from the shape of the string.
     *
     * @param  array<array-key, mixed>  $parameters
     */
    private static function postalCode(array $parameters): PostalCode
    {
        $field = null;
        $countries = [];

        foreach (self::strings($parameters) as $parameter) {
            if (str_starts_with($parameter, '@')) {
                $field = substr($parameter, 1);

                continue;
            }

            $countries[] = $parameter;
        }

        return new PostalCode($countries, $field);
    }

    /**
     * Narrow an alias parameter to a model class, or say why it cannot be.
     *
     * The database aliases name a model in a rule string, where nothing checks
     * it. This runs at validation time — the factory is called from inside the
     * extension closure, not at registration — so it does not make the failure
     * earlier. What it changes is legibility: without it the rule instantiates
     * the name itself and the user gets a bare "class not found" that never
     * mentions the alias or the parameter that produced it.
     *
     * @param  array<array-key, mixed>  $parameters
     * @return class-string<Model>
     */
    private static function model(array $parameters, int $index): string
    {
        $class = self::str($parameters, $index);

        if (! is_a($class, Model::class, true)) {
            throw new InvalidArgumentException(
                "[{$class}] is not an Eloquent model, so it cannot be used as a rule-alias parameter.",
            );
        }

        return $class;
    }

    /**
     * The parameters as a string list.
     *
     * `Validator::extend` types them as a bare array, so nothing upstream can
     * promise their shape. Non-scalars are dropped rather than coerced —
     * `(string) []` is a fatal, and a rule string cannot carry an array.
     *
     * @param  array<array-key, mixed>  $parameters
     * @return list<string>
     */
    private static function strings(array $parameters): array
    {
        $strings = [];

        foreach ($parameters as $parameter) {
            if (is_scalar($parameter) || $parameter instanceof Stringable) {
                $strings[] = (string) $parameter;
            }
        }

        return $strings;
    }

    /** @param  array<array-key, mixed>  $parameters */
    private static function str(array $parameters, int $index, string $default = ''): string
    {
        return self::strings($parameters)[$index] ?? $default;
    }

    /** @param  array<array-key, mixed>  $parameters */
    private static function nullableStr(array $parameters, int $index): ?string
    {
        return self::strings($parameters)[$index] ?? null;
    }

    /** @param  array<array-key, mixed>  $parameters */
    private static function int(array $parameters, int $index, int $default): int
    {
        $value = self::strings($parameters)[$index] ?? null;

        return is_numeric($value) ? (int) $value : $default;
    }

    /**
     * A trailing flag parameter, spelled the way Laravel spells booleans in
     * rule strings. Absent means the rule's own default, so an alias with no
     * parameter behaves exactly like `new Rule()`.
     *
     * @param  array<array-key, mixed>  $parameters
     */
    private static function bool(array $parameters, int $index, bool $default): bool
    {
        $value = self::strings($parameters)[$index] ?? null;

        if ($value === null) {
            return $default;
        }

        return filter_var($value, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE) ?? $default;
    }

    /**
     * @param  array<array-key, mixed>  $parameters
     * @return list<int>
     */
    private static function ints(array $parameters): array
    {
        return array_values(array_map(
            intval(...),
            array_filter(self::strings($parameters), is_numeric(...)),
        ));
    }
}
