<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Validation\Contracts\Email;

/**
 * A list of role-account local parts — `info`, `support`, `admin` and friends.
 *
 * See {@see DisposableDomainList} for why this contract lives in the
 * validation package rather than laranail/email.
 */
interface RoleAccountList
{
    /**
     * Whether the given local part (the portion before the `@`) is a role
     * account. Compared case-insensitively; no network IO.
     */
    public function contains(string $localPart): bool;
}
