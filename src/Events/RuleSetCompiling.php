<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Validation\Events;

use Simtabi\Laranail\Validation\RuleSet;

/**
 * Fired before a {@see RuleSet} compiles and validates — the seam for
 * mutating what is ABOUT to run. Listeners may add or replace rules,
 * messages and attributes, or reshape the data; the mutations apply to
 * this run only and never stick to the rule set instance.
 *
 * This generalizes the role the legacy `JsValidationRulesProcessing`
 * event played: one place a consumer (or sibling package) can inject
 * cross-cutting rules — a tenant field, an audit constraint — without
 * owning the call site.
 */
final class RuleSetCompiling
{
    /**
     * @param array<string, mixed> $rules Field → rule input, as the rule set holds it. Mutable.
     * @param array<string, string> $messages Custom messages. Mutable.
     * @param array<string, string> $attributes Human attribute names. Mutable.
     * @param array<string, mixed> $data The data about to be validated. Mutable.
     */
    public function __construct(
        public readonly RuleSet $ruleSet,
        public array $rules,
        public array $messages,
        public array $attributes,
        public array $data,
    ) {}
}
