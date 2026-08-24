<?php declare(strict_types=1);

namespace Simtabi\Laranail\Validation\Support\Payment;

use Simtabi\Laranail\Validation\Contracts\Payment\CardBrandCatalogue;

/**
 * The bundled brand data — the fourteen brands the legacy engine shipped as
 * one class each, as data, with its range bugs fixed:
 *
 * - Discover's 622126–622925 block is an INTERVAL; the legacy patterns
 *   excluded (and matched) only its two endpoints, so UnionPay claimed
 *   most of Discover's range and vice versa.
 * - Troy is 9792; the legacy `/^9(?!79200|79289)/` matched every 9-card
 *   EXCEPT Troy's own published test prefixes.
 * - Amex issues 15 digits, not 15-or-16.
 * - UnionPay numbers are not universally Luhn — enforcing it declines
 *   real cards, so the brand carries `luhn: false`.
 *
 * Ordering matters only for equal-specificity overlaps, of which the
 * curated set has none; `identify()` picks the most specific range.
 */
final class BundledCardBrandCatalogue implements CardBrandCatalogue
{
    /** @var list<CardBrand>|null */
    private ?array $brands = null;

    public function brands(): array
    {
        return $this->brands ??= [
            new CardBrand('visa', 'Visa', [['4', '4']], [13, 16, 19], [3]),
            new CardBrand('visaelectron', 'Visa Electron', [
                ['4026', '4026'], ['417500', '417500'], ['4405', '4405'],
                ['4508', '4508'], ['4844', '4844'], ['4913', '4913'], ['4917', '4917'],
            ], [16], [3]),
            new CardBrand('mastercard', 'Mastercard', [['51', '55'], ['2221', '2720']], [16], [3]),
            new CardBrand('amex', 'American Express', [['34', '34'], ['37', '37']], [15], [3, 4]),
            new CardBrand('discover', 'Discover', [
                ['6011', '6011'], ['622126', '622925'], ['644', '649'], ['65', '65'],
            ], [16, 19], [3]),
            new CardBrand('unionpay', 'UnionPay', [['62', '62']], [16, 17, 18, 19], [3], luhn: false),
            new CardBrand('dinersclub', 'Diners Club', [
                ['300', '305'], ['36', '36'], ['38', '39'],
            ], [14, 16, 19], [3]),
            new CardBrand('jcb', 'JCB', [['3528', '3589']], [16, 17, 18, 19], [3]),
            new CardBrand('maestro', 'Maestro', [
                ['5018', '5018'], ['502', '503'], ['505', '505'],
                ['56', '58'], ['61', '61'], ['639', '639'], ['67', '69'],
            ], [12, 13, 14, 15, 16, 17, 18, 19], [3]),
            new CardBrand('mir', 'Mir', [['2200', '2204']], [16, 17, 18, 19], [3]),
            new CardBrand('troy', 'Troy', [['9792', '9792']], [16], [3]),
            new CardBrand('dankort', 'Dankort', [['5019', '5019']], [16], [3]),
            new CardBrand('hipercard', 'Hipercard', [
                ['606282', '606282'], ['3841', '3841'],
            ], [13, 16, 19], [3]),
            new CardBrand('forbrugsforeningen', 'Forbrugsforeningen', [['600722', '600722']], [16], [3]),
        ];
    }

    public function identify(string $number): ?CardBrand
    {
        $winner = null;
        $bestWidth = 0;

        foreach ($this->brands() as $brand) {
            $width = $brand->matchWidth($number);

            if ($width !== null && $width > $bestWidth) {
                $winner = $brand;
                $bestWidth = $width;
            }
        }

        return $winner;
    }
}
