<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Validation\Rules\Network;

use Closure;
use Illuminate\Support\Facades\Http;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Contracts\Validation\ValidationRule;
use Simtabi\Laranail\Validation\Concerns\SkipsPrecognition;
use Simtabi\Laranail\Validation\Rules\Email\Support\Address;
use Simtabi\Laranail\Validation\Contracts\PrecognitionSkippable;

/**
 * The email address has a Gravatar — a HEAD probe of the `d=404` avatar
 * endpoint, over https with the sha256 hash (the current API; the legacy
 * rule used plain http and md5).
 *
 * Unreachable PASSES — the DeliverableEmail posture: a third party's
 * outage must not turn away real users, so read a pass as "not shown to
 * be missing", not "confirmed present". (An owner decision revived this
 * rule from the plan's recommended drop; the redesign is https, sha256,
 * the timeout, and this failure posture.)
 *
 * **Privacy is the cost, and it is the rule's function**: a hash of the
 * user's email is sent to gravatar.com at validation time. Hashes of
 * known addresses are trivially confirmable, so treat this as disclosure
 * to a third party — name it in your privacy policy, and prefer probing
 * AFTER signup (a job, not a rule) when "has an avatar" is a nicety
 * rather than a requirement.
 *
 * Network tier — skipped during precognition.
 */
final readonly class HasGravatar implements PrecognitionSkippable, ValidationRule
{
    use SkipsPrecognition;

    public function __construct(private int $timeoutSeconds = 3) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        // Before the IO: a precognitive preview must cost nothing.
        if ($this->shouldSkipPrecognition()) {
            return;
        }

        if (! is_string($value) || ! $this->passes($value)) {
            $fail('laranail/validation::validation.has_gravatar')->translate();
        }
    }

    private function passes(string $value): bool
    {
        if (Address::split($value) === null) {
            return false;
        }

        $hash = hash('sha256', strtolower(trim($value)));

        try {
            $response = Http::timeout($this->timeoutSeconds)
                ->connectTimeout($this->timeoutSeconds)
                ->head('https://gravatar.com/avatar/' . $hash . '?d=404');
        } catch (ConnectionException) {
            return true;
        }

        return $response->status() === 200;
    }
}
