<?php declare(strict_types=1);

namespace Simtabi\Laranail\Validation\Rules\Postal;

/**
 * Postal-code patterns by ISO 3166-1 alpha-2 country code.
 *
 * 100 countries onto 20 distinct patterns — most of the world writes four or
 * five digits, so the table is far smaller than the country count suggests.
 * Grouping by pattern rather than listing each country separately means a
 * correction is made once rather than forty times.
 *
 * Every pattern uses bounded quantifiers only, so there is no nested or
 * adjacent unbounded repetition for a backtracking attack to exploit.
 *
 * Coverage is deliberately shallow. This answers "is this the right SHAPE for
 * that country", not "is this a real postcode" — a shape check catches the
 * common transposition and wrong-country mistakes, while confirming that
 * 75001 is a Paris arrondissement needs a licensed dataset, not a regex.
 *
 * @internal
 */
final class Patterns
{
    /**
     * Comma-separated country codes => pattern.
     *
     * @var array<string, string>
     */
    private const BY_PATTERN = [
        'AD, AS, BA, CU, DE, DZ, EE, ES, FI, FM, FR, GF, GP, GU, HR, IC, ID, IT, KR, LT,' .
        'MA, MC, ME, MH, MP, MQ, MY, NC, PK, PR, PW, RE, RS, SM, TH, TR, UA, US, VI, XK,' .
        'YT' => '/^[0-9]{5}$/',
        'AM, AR, AT, AU, BD, BE, BG, CH, CY, DK, GE, GL, HU, LI, LU, LV, MD, MK, NO, NZ,' .
        'PH, SI, TN, ZA' => '/^[0-9]{4}$/',
        'BY, CN, EC, IN, KG, KZ, RO, RU, SG, TJ' => '/^[0-9]{6}$/',
        'CZ, GR, SE, SK' => '/^[0-9]{3} [0-9]{2}$/',
        'FO, IS, MG, PG' => '/^[0-9]{3}$/',
        'GG, JE' => '/^[a-z]{2}[0-9][0-9]? [0-9][a-z]{2}$/i',
        'MV, MX' => '/^[0-9]{4}[0-9]?$/',
        'AZ' => '/^[0-9]{4}([0-9]{2})?$/',
        'BN' => '/^[a-z]{2}[0-9]{4}$/i',
        'BR' => '/^[0-9]{5}(-?[0-9]{3})?$/',
        // Corrected from the source table, which made the final digit optional
        // and so accepted the five-character 'K1A 0B'. A Canadian postal code is
        // always A1A 1A1. Canada Post also excludes D, F, I, O, Q and U in every
        // position, and W and Z as the first letter.
        'CA' => '/^[ABCEGHJ-NPRSTVXY][0-9][ABCEGHJ-NPRSTV-Z] ?[0-9][ABCEGHJ-NPRSTV-Z][0-9]$/i',
        'GB' => '/^(([a-z][0-9])|([a-z][0-9]{2})|([a-z][0-9][a-z])|([a-z]{2}[0-9])|([a-z]{2}[0-9]{2})|([a-z]{2}[0-9][a-z])) [0-9][a-z]{2}$/i',
        'IL' => '/^[0-9]{5}([0-9]{2})?$/',
        'JP' => '/^[0-9]{3}-[0-9]{4}$/',
        'MN' => '/^[0-9]{5}[0-9]?$/',
        'NL' => '/^[0-9]{4}( [a-z]{2})?$/i',
        'PL' => '/^[0-9]{2}-[0-9]{3}$/',
        'PT' => '/^[0-9]{4}(-[0-9]{3})?$/',
        'SZ' => '/^[a-z]{1}[0-9]{3}$/i',
        'TW' => '/^[0-9]{3}([0-9]{2})?$/',
    ];

    /**
     * Flattened code => pattern lookup, built once per process.
     *
     * @var array<string, string>|null
     */
    private static ?array $flat = null;

    public static function for(string $countryCode): ?string
    {
        self::$flat ??= self::flatten();

        return self::$flat[strtoupper(trim($countryCode))] ?? null;
    }

    public static function supports(string $countryCode): bool
    {
        return self::for($countryCode) !== null;
    }

    /** @return list<string> */
    public static function countries(): array
    {
        self::$flat ??= self::flatten();

        return array_keys(self::$flat);
    }

    /** @return array<string, string> */
    private static function flatten(): array
    {
        $flat = [];

        foreach (self::BY_PATTERN as $codes => $pattern) {
            foreach (explode(',', $codes) as $code) {
                $flat[trim($code)] = $pattern;
            }
        }

        return $flat;
    }
}
