<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Validation\Contracts\I18n;

use Simtabi\Laranail\Validation\Rules\I18n\LanguageCode;

/**
 * The set of ISO 639-1 language codes the {@see LanguageCode}
 * rule accepts.
 *
 * The bundled default is the full assigned alpha-2 registry. An application
 * validating against the locales it actually ships binds a narrower
 * implementation — the common case for a locale picker.
 *
 * Implementations answer for the canonical case only (lowercase — `en`, not
 * `EN`); case folding is the rule's decision.
 */
interface LanguageDataset
{
    /** Whether the code is an assigned ISO 639-1 alpha-2 code (lowercase). */
    public function isAlpha2(string $code): bool;
}
