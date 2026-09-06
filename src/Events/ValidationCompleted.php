<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Validation\Events;

use Simtabi\Laranail\Validation\RuleSet;

/**
 * Fired when a {@see RuleSet} run PASSES, with the validated data.
 * A failing run fires {@see ValidationFailed} instead — the two are
 * mutually exclusive, so a listener never needs to re-derive the verdict.
 */
final readonly class ValidationCompleted
{
    /**
     * @param array<string, mixed> $data
     * @param array<string, mixed> $validated
     */
    public function __construct(
        public RuleSet $ruleSet,
        public array $data,
        public array $validated,
    ) {}
}
