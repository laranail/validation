<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Validation\Events;

use Simtabi\Laranail\Validation\RuleSet;

/**
 * Fired when a {@see RuleSet} run begins, after {@see RuleSetCompiling}
 * mutations and `before()` hooks have been applied. Observation only —
 * the payload is the final shape the run will use.
 */
final readonly class ValidationStarting
{
    /** @param  array<string, mixed>  $data */
    public function __construct(
        public RuleSet $ruleSet,
        public array $data,
    ) {}
}
