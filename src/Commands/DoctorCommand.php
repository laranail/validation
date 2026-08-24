<?php declare(strict_types=1);

namespace Simtabi\Laranail\Validation\Commands;

use Simtabi\Laranail\Console\Tools\Commands\Command;
use Simtabi\Laranail\Console\Tools\Commands\Concerns\SupportsNamespacedNames;
use Simtabi\Laranail\Phone\PhoneFormatter;
use Simtabi\Laranail\Validation\BatchDatabaseChecker;
use Simtabi\Laranail\Validation\Contracts\Email\DisposableDomainList;
use Simtabi\Laranail\Validation\Contracts\Email\DnsResolver;
use Simtabi\Laranail\Validation\Contracts\Email\RoleAccountList;
use Simtabi\Laranail\Validation\Support\RuleRegistrar;
use Throwable;

/**
 * The package's boot-health surface (failure-handling standard: degraded
 * state must be QUERYABLE, not just reported once). Each check states what
 * it expected and what it found; a FAIL exits non-zero so smoke tests and
 * deploy gates can consume it, while a WARN reports reduced capability the
 * package deliberately tolerates.
 */
final class DoctorCommand extends Command
{
    use SupportsNamespacedNames;

    protected $signature = 'laranail::validation.doctor';

    protected $description = 'Check the package\'s wiring, contracts and datasets in this application.';

    public function handle(RuleRegistrar $registrar): int
    {
        $rows = [];
        $failed = false;

        $checks = [
            ['config merged', function (): array {
                $config = config('laranail.validation');

                return is_array($config) && $config !== []
                    ? ['OK', 'flat laranail.validation key present']
                    : ['FAIL', 'config(\'laranail.validation\') is empty — provider not registered?'];
            }],
            ['rule registry', function () use ($registrar): array {
                $count = count($registrar->classes());

                return $count > 0
                    ? ['OK', $count . ' rules (' . count($registrar->clientCheckable()) . ' client-checkable)']
                    : ['FAIL', 'no rules discovered'];
            }],
            ['disposable-domain list', function (): array {
                $list = resolve(DisposableDomainList::class);

                return $list->contains('mailinator.com')
                    ? ['OK', $list::class]
                    : ['WARN', $list::class . ' does not flag a canonical disposable domain'];
            }],
            ['role-account list', fn (): array => ['OK', resolve(RoleAccountList::class)::class]],
            ['dns resolver', fn (): array => ['OK', resolve(DnsResolver::class)::class]],
            ['batch query cap', function (): array {
                $limit = BatchDatabaseChecker::$maxValuesPerGroup;

                return $limit > 0
                    ? ['OK', (string) $limit]
                    : ['FAIL', 'cap is ' . $limit . ' — every batch would be refused'];
            }],
            ['string aliases', function (): array {
                if (config('laranail.validation.aliases.enabled') !== true) {
                    return ['OK', 'disabled (the default — rule objects only)'];
                }

                $prefix = config('laranail.validation.aliases.prefix');

                return is_string($prefix) && $prefix !== ''
                    ? ['OK', 'enabled, prefix "' . $prefix . '"']
                    : ['FAIL', 'enabled with an empty prefix — bare generic names collide silently'];
            }],
            ['ext-intl', fn (): array => extension_loaded('intl')
                ? ['OK', 'loaded']
                : ['WARN', 'missing — PostalCode cannot validate; install ext-intl']],
            ['laranail/phone', fn (): array => class_exists(PhoneFormatter::class)
                ? ['OK', 'installed — Telecom rules available']
                : ['WARN', 'not installed — FluentRule::phone() and Rules\Telecom throw on use']],
        ];

        foreach ($checks as [$name, $check]) {
            try {
                [$status, $detail] = $check();
            } catch (Throwable $e) {
                [$status, $detail] = ['FAIL', $e::class . ': ' . $e->getMessage()];
            }

            $failed = $failed || $status === 'FAIL';
            $rows[] = [$name, $status, $detail];
        }

        $this->table(['Check', 'Status', 'Detail'], $rows);

        return $failed ? self::FAILURE : self::SUCCESS;
    }
}
