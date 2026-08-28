<?php

declare(strict_types=1);

use Illuminate\Http\Request;
use Simtabi\Laranail\Validation\Concerns\SkipsPrecognition;
use Simtabi\Laranail\Validation\Contracts\PrecognitionSkippable;

// =========================================================================
// Laravel's precognition filter narrows rules by ATTRIBUTE, driven by the
// Precognition-Validate-Only header — FormRequest::createDefaultValidator()
// calls filterPrecognitiveRules(). It has no idea what a rule *does*, so a
// network rule attached to a validated attribute still executes: one DNS or
// HTTP call per debounced keystroke. This contract is what stops that, so it
// needs a test proving the check actually reads the live request.
// =========================================================================

final class SkippableProbe implements PrecognitionSkippable
{
    use SkipsPrecognition;
}

it('reports not-skippable when no request has been resolved', function (): void {
    // A rule is perfectly usable outside HTTP — a queued job, an artisan
    // command, a bare Validator::make() in a test. Answering "not
    // precognitive" is the only safe default there.
    expect(new SkippableProbe()->shouldSkipPrecognition())->toBeFalse();
});

it('reports not-skippable for an ordinary request', function (): void {
    app()->instance('request', Request::create('/register', 'POST'));

    expect(new SkippableProbe()->shouldSkipPrecognition())->toBeFalse();
});

it('reports skippable for a precognitive request', function (): void {
    $request = Request::create('/register', 'POST');
    // isPrecognitive() reads the `precognitive` request ATTRIBUTE, which the
    // HandlePrecognitiveRequests middleware sets — not the header directly.
    $request->attributes->set('precognitive', true);

    app()->instance('request', $request);

    expect(new SkippableProbe()->shouldSkipPrecognition())->toBeTrue();
});

it('does not treat the Precognition header alone as precognitive', function (): void {
    // Header present but the middleware has not run: isAttemptingPrecognition()
    // is true while isPrecognitive() is false. Skipping here would let a
    // caller disable network rules just by sending a header.
    $request = Request::create('/register', 'POST');
    $request->headers->set('Precognition', 'true');

    app()->instance('request', $request);

    expect($request->isAttemptingPrecognition())->toBeTrue()
        ->and(new SkippableProbe()->shouldSkipPrecognition())->toBeFalse();
});
