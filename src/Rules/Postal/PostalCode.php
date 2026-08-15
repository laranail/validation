<?php declare(strict_types=1);

namespace Simtabi\Laranail\Validation\Rules\Postal;

use Closure;
use Illuminate\Contracts\Validation\DataAwareRule;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Arr;

/**
 * A postal code, validated against the format of a particular country.
 *
 * A postcode means nothing without a country: `1234` is valid in a dozen
 * places and invalid in a dozen more, and a rule that accepts "four to ten
 * alphanumerics" accepts everything and catches nothing. So the country is
 * required, and comes from one of two places.
 *
 * Fixed, when the form is for one country:
 *
 *     'postcode' => new PostalCode('NL'),
 *     'postcode' => new PostalCode(['NL', 'BE']),   // either
 *
 * Or read from a sibling field, when the user picks the country on the same
 * form — the usual case for a shipping address:
 *
 *     'country'  => ['required', 'string'],
 *     'postcode' => PostalCode::reference('country'),
 *
 * An unsupported or missing country FAILS rather than passing. Silently
 * accepting anything for a country not in the table is how a postcode column
 * fills up with junk: the failure is visible, the silent pass is not.
 *
 * Pure tier — no IO. This checks shape, not existence; confirming a postcode
 * is real needs a licensed dataset.
 */
final class PostalCode implements DataAwareRule, ValidationRule
{
    /** @var array<array-key, mixed> */
    private array $data = [];

    /** @var list<string> */
    private readonly array $countries;

    /**
     * @param  string|list<string>  $countries
     */
    public function __construct(string|array $countries = [], private readonly ?string $countryField = null)
    {
        $this->countries = array_values(array_map(
            static fn (string $country): string => strtoupper(trim($country)),
            is_string($countries) ? [$countries] : $countries,
        ));
    }

    /**
     * Resolve the country from another field in the same payload.
     *
     * Dot notation works, and so does a wildcard sibling — inside
     * `addresses.*.postcode`, referencing `addresses.*.country` resolves to
     * the country of the same row rather than the first one.
     */
    public static function reference(string $field): self
    {
        return new self(countryField: $field);
    }

    /**
     * `array-key`, not `string`: `DataAwareRule::setData()` declares a bare `array`, so narrowing the
     * key type in the implementation is a contravariance violation — a caller holding the interface
     * may legitimately hand over an integer-keyed array.
     *
     * @param array<array-key, mixed> $data
     */
    public function setData(array $data): static
    {
        $this->data = $data;

        return $this;
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value)) {
            $fail('laranail-validation::validation.postal_code')->translate();

            return;
        }

        $countries = $this->resolveCountries($attribute);

        if ($countries === []) {
            $fail('laranail-validation::validation.postal_code')->translate();

            return;
        }

        foreach ($countries as $country) {
            $pattern = Patterns::for($country);

            if ($pattern !== null && preg_match($pattern, trim($value)) === 1) {
                return;
            }
        }

        $fail('laranail-validation::validation.postal_code')->translate();
    }

    /** @return list<string> */
    private function resolveCountries(string $attribute): array
    {
        if ($this->countryField === null) {
            return $this->countries;
        }

        $country = Arr::get($this->data, $this->siblingPath($attribute));

        return is_string($country) && $country !== '' ? [strtoupper(trim($country))] : [];
    }

    /**
     * Rewrite a wildcard reference to point at the row currently being
     * validated. Without this, `addresses.*.country` inside
     * `addresses.2.postcode` would read `addresses.0.country` — every row
     * validated against the first row's country, which is wrong in a way that
     * only shows up when two rows differ.
     */
    private function siblingPath(string $attribute): string
    {
        if (! str_contains((string) $this->countryField, '*')) {
            return (string) $this->countryField;
        }

        $keys = [];
        foreach (explode('.', $attribute) as $segment) {
            if (is_numeric($segment)) {
                $keys[] = $segment;
            }
        }

        $path = (string) $this->countryField;

        foreach ($keys as $key) {
            $position = strpos($path, '*');

            if ($position === false) {
                break;
            }

            $path = substr_replace($path, $key, $position, 1);
        }

        return $path;
    }
}
