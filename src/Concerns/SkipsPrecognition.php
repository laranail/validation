<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Validation\Concerns;

use Illuminate\Http\Request;
use Illuminate\Container\Container;
use Simtabi\Laranail\Validation\Contracts\PrecognitionSkippable;

/**
 * Default implementation of {@see PrecognitionSkippable}.
 *
 * Network-tier rules `use` this and then guard their IO with it.
 */
trait SkipsPrecognition
{
    public function shouldSkipPrecognition(): bool
    {
        return self::requestIsPrecognitive();
    }

    /**
     * Resolved from the container rather than the `request()` helper, and
     * guarded on `resolved()`, because a rule is perfectly usable outside an
     * HTTP request — a queued job, an artisan command, a bare
     * `Validator::make()` in a test. Asking the container to build a Request
     * there would either fabricate one from empty globals or throw, and both
     * are worse than answering "not precognitive".
     */
    private static function requestIsPrecognitive(): bool
    {
        $container = Container::getInstance();

        if (! $container->resolved('request')) {
            return false;
        }

        $request = $container->make('request');

        return $request instanceof Request && $request->isPrecognitive();
    }
}
