<?php declare(strict_types=1);

namespace Simtabi\Laranail\Validation\Contracts;

use Simtabi\Laranail\Validation\Concerns\SkipsPrecognition;

/**
 * Marks a rule that performs network IO and must not run during a
 * precognitive request.
 *
 * Laravel's own precognition filter narrows rules by ATTRIBUTE, driven by the
 * `Precognition-Validate-Only` header — see
 * `FormRequest::createDefaultValidator()`, which calls
 * `filterPrecognitiveRules()`. It does not know or care what a rule *does*, so
 * a rule attached to a validated attribute still executes. For a DNS or HTTP
 * rule that means one lookup per debounced keystroke.
 *
 * Database-tier rules deliberately do NOT implement this: precognition exists
 * partly so `unique` and `exists` give live feedback, and a single indexed
 * read per keystroke is the intended cost.
 *
 * @see SkipsPrecognition for the check.
 */
interface PrecognitionSkippable
{
    /**
     * Whether this rule should be skipped for the request currently in flight.
     *
     * Returning true must mean "pass without performing the IO", never "fail".
     * A precognitive request is a preview: reporting a failure the real
     * submission would not produce is worse than reporting nothing.
     */
    public function shouldSkipPrecognition(): bool;
}
