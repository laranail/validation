<?php declare(strict_types=1);

namespace Simtabi\Laranail\Validation\Rules\Email;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Simtabi\Laranail\Validation\Rules\Email\Support\Address;

/**
 * The address is NOT at any of the given domains.
 *
 * The exact complement of {@see EmailDomainIs} over well-formed addresses,
 * sharing its matcher so a pattern cannot mean one thing to the allow-list
 * and another to the deny-list — in a deny-list that difference is a bypass.
 *
 *     new EmailDomainIsNot(['competitor.test', '*.competitor.test'])
 *
 * A deny-list of domains is weak on its own: registering another domain is
 * cheap. Use it to enforce a policy ("staff must not sign up with the
 * corporate domain"), not as a security control.
 *
 * Pure tier — no IO.
 */
final readonly class EmailDomainIsNot implements ValidationRule
{
    /** @param  list<string>  $domains */
    public function __construct(private array $domains) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $address = Address::split($value);

        if ($address === null) {
            $fail('laranail/validation::validation.email.malformed')->translate();

            return;
        }

        if (EmailDomainIs::matches($address[1], $this->domains)) {
            $fail('laranail/validation::validation.email.domain_is_not')
                ->translate(['domains' => implode(', ', $this->domains)]);
        }
    }
}
