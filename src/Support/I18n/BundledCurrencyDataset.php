<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Validation\Support\I18n;

use Simtabi\Laranail\Validation\Contracts\I18n\CurrencyDataset;

/**
 * The bundled ISO 4217 "List One" — the current alpha and numeric codes,
 * including the funds, precious-metal and special entries, generated from the
 * official registry snapshot by `tools/build-datasets.php`.
 *
 * The registry moves: BYR, MRO, STD, VEF, SLL, HRK and ZWL have all been
 * retired since 2016, and a hand-typed list would still accept them. The
 * suite's `--check` test pins this data to its committed source.
 *
 * Symbols come from the CLDR's English-locale renderings — a convention set,
 * not an ISO registry; see {@see CurrencyDataset::isSymbol()}.
 */
final class BundledCurrencyDataset implements CurrencyDataset
{
    /** @var array<string, string>|null alpha code => numeric code */
    private ?array $codes = null;

    /** @var array<string, true>|null */
    private ?array $numeric = null;

    /** @var array<string, true>|null */
    private ?array $symbols = null;

    public function isCode(string $code): bool
    {
        return isset($this->codes()[$code]);
    }

    public function isNumericCode(string $code): bool
    {
        if ($this->numeric === null) {
            $this->numeric = [];

            foreach ($this->codes() as $numeric) {
                $this->numeric[$numeric] = true;
            }
        }

        return isset($this->numeric[$code]);
    }

    public function isSymbol(string $symbol): bool
    {
        $this->symbols ??= CodeFile::load(__DIR__.'/../../../resources/data/currency-symbols.txt');

        return isset($this->symbols[$symbol]);
    }

    /** @return array<string, string> */
    private function codes(): array
    {
        return $this->codes ??= CodeFile::loadMap(__DIR__.'/../../../resources/data/iso-4217.txt');
    }
}
