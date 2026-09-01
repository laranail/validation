<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Validation\Internal;

use Closure;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Simtabi\Laranail\Validation\RuleSet;

/**
 * The one-validator route `RuleSet::after()` forces.
 *
 * Error-adding after-callbacks are a whole-run concern the per-item fast
 * paths cannot honour, so when any are registered the run compiles into a
 * single vanilla Laravel validator — native wildcard handling included —
 * attaches the callbacks with Laravel's own semantics, and lets it decide.
 * No fast paths: correctness of the error-adding contract over speed.
 *
 * @internal
 */
final class VanillaAfterRoute
{
    /**
     * @param  array<string, mixed>  $compiledRules  Output of {@see RuleSet::compile()} over the flattened fields.
     * @param  array<string, mixed>  $data
     * @param  array<string, string>  $messages
     * @param  array<string, string>  $attributes
     * @param  list<Closure>  $afterCallbacks
     * @return array<string, mixed>
     *
     * @throws ValidationException
     */
    public static function validate(
        array $compiledRules,
        array $data,
        array $messages,
        array $attributes,
        bool $stopOnFirstFailure,
        bool $dropUnknownFields,
        array $afterCallbacks,
    ): array {
        $validator = Validator::make($data, $compiledRules, $messages, $attributes)
            ->stopOnFirstFailure($stopOnFirstFailure);
        $validator->excludeUnvalidatedArrayKeys = $dropUnknownFields || $validator->excludeUnvalidatedArrayKeys;

        foreach ($afterCallbacks as $callback) {
            $validator->after($callback);
        }

        /** @var array<string, mixed> */
        return $validator->validate();
    }
}
