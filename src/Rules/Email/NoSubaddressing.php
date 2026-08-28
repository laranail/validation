<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Validation\Rules\Email;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Simtabi\Laranail\Validation\Rules\Email\Support\Address;

/**
 * The address carries no `+tag` subaddress.
 *
 * `user+anything@example.com` is delivered to `user@example.com`, so one
 * mailbox can mint an unlimited number of addresses that all look distinct to
 * a `unique` index. That is the whole reason this rule exists: it is how one
 * person takes a free trial repeatedly, and the duplicate is invisible in the
 * table because the strings genuinely differ.
 *
 * **Off by default, and it should stay off nearly everywhere.** Subaddressing
 * is a legitimate feature that people use to filter their own mail and to see
 * which service leaked their address. Rejecting it on a contact form or a
 * newsletter signup is a small hostility with no upside. It earns its place on
 * one kind of field: the one that grants something per account.
 *
 * `+` is the separator every major provider uses. A provider that uses
 * something else — a dash, a period — is not covered here, and folding those
 * in would reject ordinary addresses at every provider that treats them as
 * part of the local part.
 *
 * Pure tier — no IO. It says nothing about whether the base address exists.
 */
final class NoSubaddressing implements ValidationRule
{
    public static function passes(mixed $value): bool
    {
        $address = Address::split($value);

        return $address !== null && ! str_contains($address[0], '+');
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $address = Address::split($value);

        if ($address === null) {
            $fail('laranail/validation::validation.email.malformed')->translate();

            return;
        }

        if (str_contains($address[0], '+')) {
            $fail('laranail/validation::validation.email.subaddress')->translate();
        }
    }
}
