<?php declare(strict_types=1);

use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Date;
use Simtabi\Laranail\Validation\Rules\AntiSpam\Honeypot;
use Simtabi\Laranail\Validation\Rules\AntiSpam\SubmissionTiming;

// =========================================================================
// Honeypot
// =========================================================================

it('treats absent and blank as untouched', function (mixed $value): void {
    expect(Honeypot::passes($value))->toBeTrue();
})->with([null, '', '   ', "\t\n"]);

it('treats "0" as filled, which empty() would not', function (): void {
    // empty('0') is true. That is exactly the value a lazy bot posts, so
    // using empty() here would wave through the case the rule exists for.
    expect(Honeypot::passes('0'))->toBeFalse();
});

it('rejects anything a person would not leave behind', function (mixed $value): void {
    expect(Honeypot::passes($value))->toBeFalse();
})->with(['https://spam.example', 'x', [['a']], 42, true]);

// =========================================================================
// SubmissionTiming
// =========================================================================

it('rejects a submission that arrives faster than a person could type', function (): void {
    $rule = new SubmissionTiming(minimumSeconds: 3);

    expect(ruleAccepts($rule, SubmissionTiming::token()))->toBeFalse();
});

it('accepts one that took long enough', function (): void {
    // The token carries its own issue time, so an older one can be forged
    // honestly here rather than sleeping through the test.
    $token = Crypt::encryptString((string) (Date::now()
        ->getTimestamp() - 30));

    expect(ruleAccepts(new SubmissionTiming(minimumSeconds: 3), $token))->toBeTrue();
});

it('rejects a stale token', function (): void {
    $token = Crypt::encryptString((string) (Date::now()
        ->getTimestamp() - 10_000));

    expect(ruleAccepts(new SubmissionTiming(maximumSeconds: 7200), $token))->toBeFalse();
});

it('rejects a plain, tampered or foreign token', function (mixed $value): void {
    // No empty-string case here on purpose: Validator::presentOrRuleIsImplicit()
    // short-circuits on trim($value) === '', so a non-implicit rule never sees
    // blank input at all. Requiring the field is `required`'s job.
    // The whole point of encrypting: a plain timestamp is attacker-supplied,
    // and a bot would simply post one that passes.
    expect(ruleAccepts(new SubmissionTiming(), $value))->toBeFalse();
})->with([
    'plain timestamp' => '1700000000',
    'garbage' => 'not-a-token',
    'non-string' => 12345,
]);

it('rejects a token from the future rather than treating it as instant', function (): void {
    $token = Crypt::encryptString((string) (Date::now()
        ->getTimestamp() + 600));

    expect(SubmissionTiming::elapsed($token))->toBeNull();
});

it('measures elapsed time from its own token', function (): void {
    expect(SubmissionTiming::elapsed(SubmissionTiming::token()))->toBeLessThan(3);
});
