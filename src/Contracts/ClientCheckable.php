<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Validation\Contracts;

/**
 * A rule that can be checked in a browser without reimplementing it there.
 *
 * `laranail/validation-js` exports rules as a schema and runs them client-side.
 * A rule OBJECT normally cannot go: its logic is PHP that was never sent, so
 * the exporter routes it to the server. That is correct, and slower than it
 * needs to be for the rules a browser could decide.
 *
 * The contract returns NATIVE LARAVEL RULES rather than a "check this in
 * JavaScript" hook, because the alternative is worse: a hand-written
 * JavaScript twin of every rule, drifting from the PHP one, and disagreeing
 * with the server in the cases nobody tested. Returning
 * `[['rule' => 'regex', 'params' => ['pattern' => self::PATTERN]]]` sends the
 * rule's OWN pattern, so there is one source of truth and no second
 * implementation.
 *
 * **A list, not a single rule**, and that is load-bearing rather than
 * generality for its own sake. `Geo\Latitude` is `is_numeric` plus a range:
 * its browser form is `numeric` AND `between:-90,90`, two native rules that
 * the runner already implements. Expressed as one rule it would have to be a
 * regex contorted into a bounded numeric range — unreadable, easy to get
 * subtly wrong, and wrong here means the browser disagreeing with the server.
 * The list is what makes such rules expressible honestly.
 *
 * **Only return rules whose combination is EXACTLY equivalent.** A rule whose
 * check includes a checksum — IBAN, Luhn, IMEI — must not advertise a
 * shape-only pattern: that would pass a mistyped account number in the browser
 * and fail it on the server, which is the precise failure client-side
 * validation exists to avoid. Returning an empty list, or not implementing
 * this at all, keeps the safe default.
 */
interface ClientCheckable
{
    /**
     * The browser-equivalent rules, ALL of which must pass. Empty when there
     * is no faithful browser form.
     *
     * Every rule name must be one the runner already implements — `regex`,
     * `not_regex`, `numeric`, `between`, `in`, and so on. This is not a place
     * to invent new ones: an unrecognised name is ignored by the exporter and
     * the rule routes to the server, which is safe but silent.
     *
     * @return list<array{rule: string, params: array<array-key, string>}>
     */
    public function clientRules(): array;
}
