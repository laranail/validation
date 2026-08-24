<?php declare(strict_types=1);

namespace Simtabi\Laranail\Validation\Support\I18n;

use Simtabi\Laranail\Validation\Contracts\I18n\LanguageDataset;

/**
 * The bundled ISO 639-1 registry — the 183 assigned alpha-2 codes, generated
 * from the Library of Congress registry snapshot by `tools/build-datasets.php`
 * and pinned to it by the suite's `--check` test.
 */
final class BundledLanguageDataset implements LanguageDataset
{
    /** @var array<string, true>|null */
    private ?array $alpha2 = null;

    public function isAlpha2(string $code): bool
    {
        $this->alpha2 ??= CodeFile::load(__DIR__ . '/../../../resources/data/iso-639-1.txt');

        return isset($this->alpha2[$code]);
    }
}
