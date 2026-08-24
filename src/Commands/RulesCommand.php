<?php declare(strict_types=1);

namespace Simtabi\Laranail\Validation\Commands;

use Illuminate\Foundation\Auth\User;
use Simtabi\Laranail\Console\Tools\Commands\Command;
use Simtabi\Laranail\Console\Tools\Commands\Concerns\SupportsNamespacedNames;
use Simtabi\Laranail\Validation\Contracts\ClientCheckable;
use Simtabi\Laranail\Validation\Support\RuleAliases;
use Simtabi\Laranail\Validation\Support\RuleRegistrar;

/**
 * Lists every rule the registry knows — the package's discovered library
 * plus anything the application registered — with its family, string alias
 * and browser-checkability. Reads the LIVE registry, so the output is what
 * the application actually has, not what a doc claims.
 */
final class RulesCommand extends Command
{
    use SupportsNamespacedNames;

    protected $signature = 'laranail::validation.rules';

    protected $description = 'List every registered validation rule, its alias and browser-checkability.';

    public function handle(RuleRegistrar $registrar): int
    {
        $aliasByClass = $this->aliasIndex();
        $rows = [];

        foreach ($registrar->classes() as $class) {
            $short = str_replace('Simtabi\\Laranail\\Validation\\Rules\\', '', $class);
            $family = str_contains($short, '\\') ? explode('\\', $short)[0] : '(application)';

            $rows[] = [
                $family,
                class_basename($class),
                $aliasByClass[$class] ?? '—',
                is_subclass_of($class, ClientCheckable::class) ? '✓' : '—',
            ];
        }

        sort($rows);

        $this->table(['Family', 'Rule', 'Alias suffix', 'Client-checkable'], $rows);
        $this->line(sprintf(
            '%d rules · %d aliased · %d client-checkable',
            count($rows),
            count(array_filter($rows, static fn (array $row): bool => $row[2] !== '—')),
            count($registrar->clientCheckable()),
        ));

        return self::SUCCESS;
    }

    /**
     * Alias suffix per rule class, derived by instantiating each factory the
     * way the alias-completeness test does.
     *
     * @return array<class-string, string>
     */
    private function aliasIndex(): array
    {
        $samples = [
            'models_exist' => [User::class],
            'authorized' => ['view', User::class],
            'parity' => ['even'],
            'vendor_identifier' => ['aws_region'],
            'national_identifier' => ['nl'],
            'max_words' => ['200'],
            'min_words' => ['2'],
        ];
        $index = [];

        foreach (RuleAliases::map() as $suffix => $factory) {
            $index[$factory($samples[$suffix] ?? [])::class] = $suffix;
        }

        return $index;
    }
}
