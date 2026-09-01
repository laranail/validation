<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Validation\Events;

use Illuminate\Support\MessageBag;
use Illuminate\Validation\ValidationException;
use Simtabi\Laranail\Validation\RuleSet;

/**
 * Fired when a {@see RuleSet} run FAILS — the notification seam.
 *
 * The package itself never notifies anyone (failure handling standard,
 * rule 3: reporting goes through the consumer's central handler). A
 * consumer listens here and routes to its own notifier — mail, Slack,
 * log — with whatever throttling its monitoring policy wants.
 */
final readonly class ValidationFailed
{
    /** @param  array<string, mixed>  $data */
    public function __construct(
        public RuleSet $ruleSet,
        public array $data,
        public MessageBag $errors,
        public ValidationException $exception,
    ) {}
}
