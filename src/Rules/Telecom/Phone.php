<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Validation\Rules\Telecom;

use Closure;
use Illuminate\Contracts\Validation\DataAwareRule;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Arr;
use RuntimeException;
use Simtabi\Laranail\Phone\Enums\PhoneNumberType;
use Simtabi\Laranail\Phone\PhoneFormatter;
use Simtabi\Laranail\Phone\PhoneNumberValue;

/**
 * A phone number, checked against Google's numbering-plan metadata.
 *
 * IO tier — the check reads libphonenumber's metadata from disk (cached in-process after the first
 * lookup for a given prefix). It is not a regex, and it cannot be: no pattern can know that
 * `+254712345678` is an allocated Kenyan mobile range while `+254012345678` is not.
 *
 * ### Possible versus valid
 *
 * The default is `valid` — the number falls inside a range that has actually been allocated. That is
 * the stricter of the two and the right default, but it has a real cost: newly allocated ranges are
 * *possible* before Google's metadata knows about them, so a small number of genuine customers will
 * be turned away until the next libphonenumber release. Where that matters more than precision —
 * signup forms, lead capture — {@see possibleOnly()} relaxes to a shape check.
 *
 * ### The country comes from somewhere
 *
 * A bare national number cannot be checked without knowing which plan to check it against. Supply it
 * as a fixed list, or point at a sibling field with {@see countryField()} so a country picker beside
 * the input feeds the rule. A number already in international form carries its own country and needs
 * neither.
 *
 * Requires `laranail/phone`, which is a `suggest` rather than a `require`: it pulls libphonenumber's
 * metadata, and a project validating only strings and dates should not carry that.
 */
final class Phone implements DataAwareRule, ValidationRule
{
    /** @var array<array-key, mixed> */
    private array $data = [];

    /**
     * @param  list<string>  $countries  ISO 3166-1 alpha-2 codes; empty means any country
     * @param  list<PhoneNumberType>  $types  Acceptable line types; empty means any
     */
    public function __construct(
        private readonly array $countries = [],
        private readonly ?string $countryField = null,
        private readonly array $types = [],
        private readonly bool $possibleOnly = false,
        private readonly bool $allowExtension = true,
        private readonly bool $rejectShortNumbers = false,
        private readonly bool $rejectEmergency = false,
    ) {}

    /**
     * @param  array<array-key, mixed>  $data
     */
    public function setData(array $data): static
    {
        $this->data = $data;

        return $this;
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) && ! is_int($value)) {
            $fail('laranail/validation::validation.phone')->translate();

            return;
        }

        $number = $this->formatter()->parse((string) $value, $this->resolveCountry());

        if ($number->isEmpty()) {
            $fail('laranail/validation::validation.phone')->translate();

            return;
        }

        if (! $this->allowExtension && $number->extension !== null) {
            $fail('laranail/validation::validation.phone_extension')->translate();

            return;
        }

        if ($this->possibleOnly ? ! $number->isPossible : ! $number->isValid) {
            $fail($this->possibleOnly
                ? 'laranail/validation::validation.phone_possible'
                : 'laranail/validation::validation.phone')->translate();

            return;
        }

        if ($this->rejectEmergency && $number->type === PhoneNumberType::Emergency) {
            $fail('laranail/validation::validation.phone_emergency')->translate();

            return;
        }

        if ($this->rejectShortNumbers && $number->type === PhoneNumberType::ShortCode) {
            $fail('laranail/validation::validation.phone_short_code')->translate();

            return;
        }

        if (! $this->countryMatches($number)) {
            $fail('laranail/validation::validation.phone_country')->translate([
                'country' => implode(', ', $this->countries),
            ]);

            return;
        }

        if (! $this->typeMatches($number)) {
            $fail('laranail/validation::validation.phone_type')->translate([
                'type' => implode(', ', array_map(
                    static fn (PhoneNumberType $type): string => strtolower($type->label()),
                    $this->types,
                )),
            ]);
        }
    }

    private function countryMatches(PhoneNumberValue $number): bool
    {
        if ($this->countries === []) {
            return true;
        }

        return $number->country !== null
            && in_array(strtoupper($number->country), array_map(strtoupper(...), $this->countries), true);
    }

    /**
     * Whether the line type is one of the accepted ones.
     *
     * `FixedLineOrMobile` satisfies a request for either. In the NANP mobile and fixed-line share
     * ranges, so libphonenumber cannot tell them apart — requiring `Mobile` strictly would reject
     * every valid North American mobile number, which is the single most common way this check is
     * got wrong.
     */
    private function typeMatches(PhoneNumberValue $number): bool
    {
        if ($this->types === []) {
            return true;
        }

        foreach ($this->types as $type) {
            if ($number->type === $type) {
                return true;
            }

            if ($number->type === PhoneNumberType::FixedLineOrMobile
                && ($type === PhoneNumberType::Mobile || $type === PhoneNumberType::FixedLine)) {
                return true;
            }
        }

        return false;
    }

    /**
     * The country to parse a bare national number against.
     *
     * A sibling field wins when one is named and holds a value — that is the country picker beside
     * the input. Otherwise a single configured country is used as the hint; a list is not, because
     * picking one arbitrarily would make the outcome depend on array order.
     */
    private function resolveCountry(): ?string
    {
        if ($this->countryField !== null) {
            $country = Arr::get($this->data, $this->countryField);

            if (is_string($country) && $country !== '') {
                return $country;
            }
        }

        return count($this->countries) === 1 ? $this->countries[0] : null;
    }

    private function formatter(): PhoneFormatter
    {
        if (! class_exists(PhoneFormatter::class)) {
            throw new RuntimeException(
                'The phone rule requires laranail/phone. Install it with `composer require laranail/phone`. '
                ."It is a suggested rather than a required dependency because it pulls libphonenumber's "
                .'numbering-plan metadata, which a project validating only strings and dates should not carry.',
            );
        }

        return resolve(PhoneFormatter::class);
    }
}
