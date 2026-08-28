<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Validation\Contracts\I18n;

use Simtabi\Laranail\Validation\Rules\I18n\CurrencyCode;

/**
 * The set of ISO 4217 currency identifiers the {@see CurrencyCode}
 * rule accepts.
 *
 * The bundled default carries the official current "List One" — circulating
 * currencies plus the funds, precious-metal and special codes (XAU, XDR,
 * XXX, …). An application that accepts only the currencies it can settle
 * binds a narrower implementation; the rule does not change.
 *
 * Implementations answer for the canonical case only (uppercase alpha codes,
 * bare digits for numeric codes) — case folding is the rule's decision.
 */
interface CurrencyDataset
{
    /** Whether the code is a current ISO 4217 alpha code (uppercase). */
    public function isCode(string $code): bool;

    /** Whether the string is a current ISO 4217 three-digit numeric code. */
    public function isNumericCode(string $code): bool;

    /**
     * Whether the string is a recognised currency symbol (€, £, KSh, …).
     *
     * Symbols are conventions, not part of ISO 4217 — the bundled set is the
     * CLDR's English-locale renderings, useful for "did the user type a
     * symbol where we wanted one", not as an exhaustive registry.
     */
    public function isSymbol(string $symbol): bool;
}
