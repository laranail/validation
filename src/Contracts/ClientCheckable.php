<?php declare(strict_types=1);

namespace Simtabi\Laranail\Validation\Contracts;

/**
 * A rule that can be checked in a browser without reimplementing it there.
 *
 * `laranail/validation-js` exports rules as a schema and runs them client-side.
 * A rule OBJECT normally cannot go: its logic is PHP that was never sent, so
 * the exporter routes it to the server. That is correct, and slower than it
 * needs to be for the rules whose entire check is a pattern.
 *
 * The contract deliberately returns a rule NAME and PARAMETERS rather than a
 * "check this in JavaScript" hook, because the alternative is worse: a
 * hand-written JavaScript twin of every rule, drifting from the PHP one, and
 * disagreeing with the server in the cases nobody tested. Returning
 * `['rule' => 'regex', 'params' => ['pattern' => self::PATTERN]]` sends the
 * rule's OWN pattern, so there is one source of truth and no second
 * implementation.
 *
 * **Only implement this when the browser form is EXACTLY equivalent.** A rule
 * whose check includes a checksum — IBAN, Luhn, IMEI — must not advertise a
 * shape-only pattern: that would pass a mistyped account number in the browser
 * and fail it on the server, which is the precise failure client-side
 * validation exists to avoid. Returning null, or not implementing this at all,
 * keeps the safe default.
 */
interface ClientCheckable
{
    /**
     * The browser-equivalent rule, or null if there is none.
     *
     * The rule name must be one the runner already implements — `regex`,
     * `not_regex`, `in`, and so on. This is not a place to invent new ones.
     *
     * @return array{rule: string, params: array<string, string>}|null
     */
    public function clientRule(): ?array;
}
