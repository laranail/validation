<?php declare(strict_types=1);

namespace Simtabi\Laranail\Validation\Rules\Payment;

use Closure;
use Illuminate\Contracts\Validation\DataAwareRule;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Arr;
use Simtabi\Laranail\Validation\Contracts\Payment\CardBrandCatalogue;
use Simtabi\Laranail\Validation\Support\Payment\CardBrand;

/**
 * A card security code. Alone, 3 or 4 digits; given `numberField:`, the
 * sibling card number's brand narrows it to exactly what that brand
 * prints (Visa 3, Amex 3 or 4).
 *
 * When the sibling is missing or its brand unrecognisable, this rule
 * falls back to 3-or-4 rather than failing: the number field's own rule
 * reports the bad number, and a CVC error saying "fix your CVC" for a
 * problem in the OTHER field sends the cardholder to the wrong box.
 *
 * A CVC is cardholder data at its most sensitive — it may never be
 * stored post-authorisation, and validating it here does not change
 * that. No message echoes it.
 *
 * Pure tier — no IO.
 */
final class CardCvc implements DataAwareRule, ValidationRule
{
    /** @var array<array-key, mixed> */
    private array $data = [];

    public function __construct(
        private readonly ?string $numberField = null,
        private ?CardBrandCatalogue $catalogue = null,
    ) {}

    /** @param  array<array-key, mixed>  $data */
    public function setData(array $data): static
    {
        $this->data = $data;

        return $this;
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || ! ctype_digit($value)
            || ! in_array(strlen($value), $this->allowedLengths(), true)) {
            $fail('laranail-validation::validation.card_cvc')->translate();
        }
    }

    /** @return list<int> */
    private function allowedLengths(): array
    {
        if ($this->numberField !== null) {
            $number = Arr::get($this->data, $this->numberField);

            if (is_string($number)) {
                $brand = $this->catalogue()->identify(str_replace([' ', '-'], '', $number));

                if ($brand instanceof CardBrand) {
                    return $brand->cvcLengths;
                }
            }
        }

        return [3, 4];
    }

    private function catalogue(): CardBrandCatalogue
    {
        return $this->catalogue ??= resolve(CardBrandCatalogue::class);
    }
}
