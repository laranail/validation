<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Validation\Rules\Email;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Simtabi\Laranail\Validation\Rules\Email\Support\Address;
use Simtabi\Laranail\Validation\Contracts\Email\DisposableDomainList;

/**
 * The address is not at a known throwaway-mail provider.
 *
 * The list comes from the container, so laranail/email can swap the bundled
 * snapshot for a refreshable one without this rule changing. That indirection
 * is also what makes it testable: bind a fake and the rule has no idea.
 *
 * **This is a heuristic and should be treated as one.** Disposable-domain
 * lists are always behind — new providers appear weekly, and a determined
 * user can register a domain in minutes. Blocking signups on it alone will
 * turn away real users while barely inconveniencing anyone deliberate. It
 * pairs best with something that raises cost rather than something that
 * refuses outright.
 *
 * Pure tier: a hash lookup, no IO. Refreshing the list is a command's job.
 */
final class NotDisposableEmail implements ValidationRule
{
    /**
     * The list is optional so the builder can offer a named method without the
     * caller wiring the contract by hand. Passed explicitly it is used as-is;
     * left null it resolves from the container at validation time rather than
     * construction time, so a rule set can be built outside a booted
     * application — in a queued job, or a test that never boots Laravel.
     */
    public function __construct(private ?DisposableDomainList $domains = null) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $address = Address::split($value);

        if ($address === null) {
            $fail('laranail/validation::validation.email.malformed')->translate();

            return;
        }

        if ($this->domains()->contains($address[1])) {
            $fail('laranail/validation::validation.email.disposable')->translate();
        }
    }

    private function domains(): DisposableDomainList
    {
        return $this->domains ??= resolve(DisposableDomainList::class);
    }
}
