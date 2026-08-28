<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Validation\Contracts\I18n;

use Simtabi\Laranail\Validation\Rules\I18n\CountryCode;

/**
 * The set of ISO 3166-1 country codes the {@see CountryCode}
 * rule accepts.
 *
 * A contract so the data can move without the rule changing: the bundled
 * default carries the full assigned registry, and an application with its own
 * geography — a subset it ships to, a sanctions list, `laranail/atlas`'s
 * richer dataset — binds its own implementation and the rule has no idea.
 *
 * Implementations answer for the CANONICAL case only (uppercase). Folding a
 * differently-cased submission is the rule's decision (`caseInsensitive:`),
 * not the dataset's — a dataset that folded on its own would make the rule's
 * strict default impossible to express.
 */
interface CountryDataset
{
    /** Whether the code is an assigned ISO 3166-1 alpha-2 code (uppercase). */
    public function isAlpha2(string $code): bool;

    /** Whether the code is an assigned ISO 3166-1 alpha-3 code (uppercase). */
    public function isAlpha3(string $code): bool;
}
