<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Validation\Rules\Payment;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Translation\PotentiallyTranslatedString;
use Simtabi\Laranail\Validation\Contracts\Payment\CardBrandCatalogue;
use Simtabi\Laranail\Validation\Support\Payment\CardBrand;

/**
 * A payment card number: brand identified by IIN range (via the
 * {@see CardBrandCatalogue} contract), length checked against what THAT
 * brand issues, and the Luhn check digit verified where the brand carries
 * one. Spaces and hyphens — how people actually type card numbers — are
 * stripped first.
 *
 * `brands:` restricts acceptance to named brand slugs (`['visa',
 * 'mastercard']`) — the "we only take" case — checked after
 * identification so the message can say which brands are accepted.
 *
 * The legacy engine's typed exceptions survive as distinct message keys:
 * a wrong length, a failed checksum and an unrecognised range send the
 * cardholder to different corrections, and one blended "invalid card"
 * helps with none of them.
 *
 * **Validity is not authorisation.** This proves the number is
 * well-formed for its brand — nothing about the account. And a PAN is
 * cardholder data the moment it exists: validating it does not license
 * storing or logging it, and no failure message here echoes the value.
 *
 * Pure tier — no IO.
 */
final class CardNumber implements ValidationRule
{
    /**
     * @param  list<string>|null  $brands  Accepted brand slugs; null accepts the whole catalogue.
     */
    public function __construct(
        private readonly ?array $brands = null,
        private ?CardBrandCatalogue $catalogue = null,
    ) {}

    public static function luhnPasses(string $digits): bool
    {
        $sum = 0;
        $double = false;

        for ($i = strlen($digits) - 1; $i >= 0; $i--) {
            $digit = (int) $digits[$i];

            if ($double) {
                $digit *= 2;

                if ($digit > 9) {
                    $digit -= 9;
                }
            }

            $sum += $digit;
            $double = ! $double;
        }

        return $sum % 10 === 0;
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value)) {
            $this->fail($fail, 'card_number');

            return;
        }

        $digits = str_replace([' ', '-'], '', $value);

        if ($digits === '' || ! ctype_digit($digits)) {
            $this->fail($fail, 'card_number');

            return;
        }

        $brand = $this->catalogue()->identify($digits);

        if (! $brand instanceof CardBrand) {
            $this->fail($fail, 'card_number');

            return;
        }

        if ($this->brands !== null && ! in_array($brand->name, $this->brands, true)) {
            $this->fail($fail, 'card_number_brand', ['brands' => implode(', ', $this->brands)]);

            return;
        }

        if (! in_array(strlen($digits), $brand->lengths, true)) {
            $this->fail($fail, 'card_number_length', [
                'lengths' => implode(' or ', $brand->lengths),
                'brand' => $brand->displayName,
            ]);

            return;
        }

        if ($brand->luhn && ! self::luhnPasses($digits)) {
            $this->fail($fail, 'card_number_checksum');
        }
    }

    private function catalogue(): CardBrandCatalogue
    {
        return $this->catalogue ??= resolve(CardBrandCatalogue::class);
    }

    /**
     * @param  Closure(string): PotentiallyTranslatedString  $fail
     * @param  array<string, string>  $parameters
     */
    private function fail(Closure $fail, string $key, array $parameters = []): void
    {
        $fail('laranail/validation::validation.'.$key)->translate($parameters);
    }
}
