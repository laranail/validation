<?php declare(strict_types=1);

namespace Simtabi\Laranail\Validation\Support\Email;

use Simtabi\Laranail\Validation\Contracts\Email\DisposableDomainList;

/**
 * The bundled snapshot of disposable domains.
 *
 * Exists so NotDisposableEmail works the moment the package is installed,
 * with nothing else to configure. It is bound with `singletonIf`, so
 * laranail/email replaces it with a refreshable, config-driven list simply by
 * binding the contract unconditionally.
 *
 * **A snapshot goes stale**, and disposable-domain lists go stale fast — new
 * throwaway providers appear weekly. Treat a pass as "not on a list from
 * August 2026", not as "not disposable".
 *
 * The file is read once per process and flipped into a hash for O(1) lookups.
 * 8,201 domains is roughly 400KB in memory once loaded, which is why it is
 * lazy: an application that never validates an email never pays for it.
 */
final class BundledDisposableDomainList implements DisposableDomainList
{
    /** @var array<string, true>|null */
    private ?array $domains = null;

    public function contains(string $domain): bool
    {
        $this->domains ??= DomainFile::load(__DIR__ . '/../../../resources/data/disposable-domains.txt');

        return isset($this->domains[strtolower(trim($domain))]);
    }

    public function count(): int
    {
        $this->domains ??= DomainFile::load(__DIR__ . '/../../../resources/data/disposable-domains.txt');

        return count($this->domains);
    }
}
