<?php declare(strict_types=1);

namespace Simtabi\Laranail\Validation\Rules\Email;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Simtabi\Laranail\Validation\Contracts\Email\RoleAccountList;
use Simtabi\Laranail\Validation\Rules\Email\Support\Address;

/**
 * The address belongs to a person rather than a function.
 *
 * `info@`, `sales@` and `postmaster@` are shared mailboxes. An account owned
 * by one belongs to nobody: password resets go to a distribution list, the
 * person who signed up leaves, and support cannot verify who is asking.
 *
 * Worth applying to account signup and worth NOT applying to a contact form —
 * "who should we email about this order" is exactly when `billing@` is the
 * right answer.
 *
 * Pure tier: a hash lookup, no IO.
 */
final class NotRoleEmail implements ValidationRule
{
    /**
     * The list is optional so the builder can offer a named method without the
     * caller wiring the contract by hand. Passed explicitly it is used as-is;
     * left null it resolves from the container at validation time rather than
     * construction time, so a rule set can be built outside a booted
     * application — in a queued job, or a test that never boots Laravel.
     */
    public function __construct(private ?RoleAccountList $localParts = null) {}

    private function localParts(): RoleAccountList
    {
        return $this->localParts ??= resolve(RoleAccountList::class);
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $address = Address::split($value);

        if ($address === null) {
            $fail('laranail/validation::validation.email.malformed')->translate();

            return;
        }

        // Plus-tagging does not change who owns the mailbox: info+signup@ is
        // still the info mailbox, and stripping the tag is the difference
        // between a rule and a rule that is trivially bypassed.
        $localPart = strtolower($address[0]);
        $plus = strpos($localPart, '+');

        if ($plus !== false) {
            $localPart = substr($localPart, 0, $plus);
        }

        if ($this->localParts()->contains($localPart)) {
            $fail('laranail/validation::validation.email.role')->translate();
        }
    }
}
