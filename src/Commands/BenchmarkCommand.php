<?php declare(strict_types=1);

namespace Simtabi\Laranail\Validation\Commands;

use Simtabi\Laranail\Console\Tools\Commands\Command;
use Simtabi\Laranail\Console\Tools\Commands\Concerns\SupportsNamespacedNames;
use Symfony\Component\Process\Process;

/**
 * The official face of the repo's benchmark harness. `benchmark.php` is a
 * development artifact (export-ignored from the Composer archive), so this
 * command runs it where it exists — a checkout of this repository — and
 * says exactly that where it does not, instead of pretending an installed
 * copy can benchmark itself.
 */
final class BenchmarkCommand extends Command
{
    use SupportsNamespacedNames;

    protected $signature = 'laranail::validation.benchmark {--snapshot : Write benchmark-snapshot.json for later comparison}';

    protected $description = 'Run the optimizer benchmark suite (repository checkouts only).';

    public function handle(): int
    {
        $script = dirname(__DIR__, 2) . '/benchmark.php';

        if (! is_file($script)) {
            $this->error(
                'benchmark.php ships only with a repository checkout, not the Composer archive. '
                . 'Clone laranail/validation to benchmark: https://github.com/laranail/validation',
            );

            return self::FAILURE;
        }

        $process = new Process(
            [PHP_BINARY, $script, ...((bool) $this->option('snapshot') ? ['--snapshot'] : [])],
            timeout: 600,
        );

        return $process->run(function (string $type, string $buffer): void {
            $this->output->write($buffer);
        });
    }
}
