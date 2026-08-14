<?php declare(strict_types=1);

namespace Simtabi\Laranail\Validation\Support\Email;

use Simtabi\Laranail\Validation\Contracts\Email\RoleAccountList;

/**
 * The bundled role-account list — mailboxes belonging to a function rather
 * than a person.
 *
 * Several entries are mandated by RFC 2142 (`postmaster`, `abuse`,
 * `hostmaster`, `webmaster` and friends); the rest are conventional. Unlike
 * the disposable list this one is short and barely changes, so the snapshot
 * staleness caveat does not really apply.
 */
final class BundledRoleAccountList implements RoleAccountList
{
    /** @var array<string, true>|null */
    private ?array $localParts = null;

    public function contains(string $localPart): bool
    {
        $this->localParts ??= DomainFile::load(__DIR__ . '/../../../resources/data/role-accounts.txt');

        return isset($this->localParts[strtolower(trim($localPart))]);
    }
}
