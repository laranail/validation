<?php declare(strict_types=1);

namespace Simtabi\Laranail\Validation\Rules\Geo;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * A US state, by USPS two-letter code or by full name.
 *
 * Territories are opt-in. A shipping form usually wants them; a form that
 * says "state" and means the fifty plus DC usually does not, and silently
 * accepting `GU` into a dataset that cannot handle it is worse than a
 * validation failure the user can read.
 *
 * DC is included in the default set. It is not a state, but every form that
 * asks for one expects it, and excluding it by default would fail a
 * significant population for pedantic reasons.
 *
 * Pure tier — no IO.
 */
final readonly class UsState implements ValidationRule
{
    /** @var array<string, string> */
    private const STATES = [
        'AL' => 'Alabama', 'AK' => 'Alaska', 'AZ' => 'Arizona', 'AR' => 'Arkansas',
        'CA' => 'California', 'CO' => 'Colorado', 'CT' => 'Connecticut', 'DE' => 'Delaware',
        'FL' => 'Florida', 'GA' => 'Georgia', 'HI' => 'Hawaii', 'ID' => 'Idaho',
        'IL' => 'Illinois', 'IN' => 'Indiana', 'IA' => 'Iowa', 'KS' => 'Kansas',
        'KY' => 'Kentucky', 'LA' => 'Louisiana', 'ME' => 'Maine', 'MD' => 'Maryland',
        'MA' => 'Massachusetts', 'MI' => 'Michigan', 'MN' => 'Minnesota', 'MS' => 'Mississippi',
        'MO' => 'Missouri', 'MT' => 'Montana', 'NE' => 'Nebraska', 'NV' => 'Nevada',
        'NH' => 'New Hampshire', 'NJ' => 'New Jersey', 'NM' => 'New Mexico', 'NY' => 'New York',
        'NC' => 'North Carolina', 'ND' => 'North Dakota', 'OH' => 'Ohio', 'OK' => 'Oklahoma',
        'OR' => 'Oregon', 'PA' => 'Pennsylvania', 'RI' => 'Rhode Island', 'SC' => 'South Carolina',
        'SD' => 'South Dakota', 'TN' => 'Tennessee', 'TX' => 'Texas', 'UT' => 'Utah',
        'VT' => 'Vermont', 'VA' => 'Virginia', 'WA' => 'Washington', 'WV' => 'West Virginia',
        'WI' => 'Wisconsin', 'WY' => 'Wyoming',
        'DC' => 'District of Columbia',
    ];

    /** @var array<string, string> */
    private const TERRITORIES = [
        'AS' => 'American Samoa',
        'GU' => 'Guam',
        'MP' => 'Northern Mariana Islands',
        'PR' => 'Puerto Rico',
        'VI' => 'U.S. Virgin Islands',
    ];

    public function __construct(private bool $includeTerritories = false) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! self::passes($value, $this->includeTerritories)) {
            $fail('laranail-validation::validation.us_state')->translate();
        }
    }

    public static function passes(mixed $value, bool $includeTerritories = false): bool
    {
        if (! is_string($value)) {
            return false;
        }

        return Subdivisions::contains(
            $value,
            $includeTerritories ? self::STATES + self::TERRITORIES : self::STATES,
        );
    }
}
