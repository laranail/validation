<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Validation\Contracts\Email;

/**
 * A list of disposable / throwaway email domains.
 *
 * Declared here, in the validation package, rather than in laranail/email:
 * the RULES that consume it live here, and laranail/email depends on this
 * package, never the other way round. Putting the contract in the email
 * package would invert that and make the dependency circular.
 *
 * laranail/validation binds a bundled snapshot as the default so the rules
 * work standalone; laranail/email overrides the binding with a refreshable,
 * config-driven implementation.
 */
interface DisposableDomainList
{
    /**
     * Whether the given domain (not the full address) is disposable.
     *
     * Implementations must compare case-insensitively and must not perform
     * network IO — this is a pure-tier lookup. Fetching or refreshing the
     * underlying data belongs in a command or an Actions service.
     */
    public function contains(string $domain): bool;
}
