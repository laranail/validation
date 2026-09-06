<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Validation\Rules\Network;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Simtabi\Laranail\Validation\Concerns\SkipsPrecognition;
use Simtabi\Laranail\Validation\Contracts\Email\DnsResolver;
use Simtabi\Laranail\Validation\Rules\Email\Support\Address;
use Simtabi\Laranail\Validation\Contracts\PrecognitionSkippable;

/**
 * The address's domain can actually receive mail.
 *
 * **Network tier.** One DNS lookup, behind {@see DnsResolver} so it is
 * injectable, cached and fakeable — none of which is true of Laravel's own
 * `email:dns`, which calls egulias' DNSCheckValidation directly.
 *
 *     'email' => ['required', 'email', new DeliverableEmail()],
 *
 * What this rule is NOT: a check that the mailbox exists. Only an SMTP
 * conversation can establish that, most providers now answer it dishonestly to
 * defeat harvesting, and running one from a signup form is a good way to get
 * the sending host blocked. This rule answers a narrower question — does the
 * domain accept mail at all — which is enough to catch `gmial.com`.
 *
 * It is a quality filter, not a security boundary. A lookup that fails for any
 * reason PASSES: see {@see DnsResolver::hasMailExchanger()} for why treating a
 * DNS outage as an invalid address is the worse error.
 *
 * Being network tier, it implements {@see PrecognitionSkippable} and does
 * nothing during a precognitive request. Laravel's precognition filter narrows
 * by attribute rather than by what a rule does, so without this a debounced
 * email field would issue a DNS lookup per keystroke.
 */
final class DeliverableEmail implements PrecognitionSkippable, ValidationRule
{
    use SkipsPrecognition;

    /**
     * The resolver is optional so the rule can be written `new DeliverableEmail()`
     * in a rule array. Passed explicitly it is used as-is; left null it
     * resolves from the container at validation time rather than construction
     * time, so a rule set can be built outside a booted application.
     */
    public function __construct(private ?DnsResolver $resolver = null) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $address = Address::split($value);

        if ($address === null) {
            $fail('laranail/validation::validation.email.malformed')->translate();

            return;
        }

        // Before the IO, not after: the whole point of the contract is that a
        // preview request costs nothing.
        if ($this->shouldSkipPrecognition()) {
            return;
        }

        if (! $this->resolver()->hasMailExchanger($address[1])) {
            $fail('laranail/validation::validation.email.undeliverable')->translate();
        }
    }

    private function resolver(): DnsResolver
    {
        return $this->resolver ??= resolve(DnsResolver::class);
    }
}
