<?php declare(strict_types=1);

namespace Simtabi\Laranail\Validation\Rules\Geo;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * A Canadian province or territory, by two-letter code or by full name.
 *
 * Unlike {@see UsState}, the territories are included by default. Canada has
 * three, they are ordinary places people live and post to, and no Canadian
 * form treats them as an exotic add-on — the US distinction exists because
 * its territories are outside the contiguous postal and legal defaults, and
 * that reasoning does not carry over.
 *
 * Both the English and French forms of Quebec are accepted, since a bilingual
 * form will produce either.
 *
 * Pure tier — no IO.
 */
final class CaProvince implements ValidationRule
{
    /** @var array<string, string> */
    private const array PROVINCES = [
        'AB' => 'Alberta',
        'BC' => 'British Columbia',
        'MB' => 'Manitoba',
        'NB' => 'New Brunswick',
        'NL' => 'Newfoundland and Labrador',
        'NS' => 'Nova Scotia',
        'NT' => 'Northwest Territories',
        'NU' => 'Nunavut',
        'ON' => 'Ontario',
        'PE' => 'Prince Edward Island',
        'QC' => 'Quebec',
        'SK' => 'Saskatchewan',
        'YT' => 'Yukon',
    ];

    /** Alternate spellings a bilingual or older form may submit. */
    private const array ALIASES = [
        'QC' => 'Québec',
        'NL' => 'Newfoundland & Labrador',
    ];

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! self::passes($value)) {
            $fail('laranail-validation::validation.ca_province')->translate();
        }
    }

    public static function passes(mixed $value): bool
    {
        if (! is_string($value)) {
            return false;
        }

        return Subdivisions::contains($value, self::PROVINCES)
            || Subdivisions::contains($value, self::ALIASES);
    }
}
