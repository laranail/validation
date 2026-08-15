<?php declare(strict_types=1);

use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Http;
use Simtabi\Laranail\Validation\Contracts\PrecognitionSkippable;

// =========================================================================
// The rule library is organised in three tiers, and the tiers are a promise
// rather than a filing system:
//
//   Pure      no IO at all. Safe anywhere — a queued job, a console command,
//             a precognitive request firing on every keystroke.
//   Database  read-only queries. Safe under precognition by design, because
//             live `exists`-style feedback is what precognition is for.
//   Network   DNS or HTTP, behind an injected contract, and skipped during a
//             precognitive request.
//
// A rule that quietly acquires IO breaks a promise nothing else checks, so
// these tests read the source rather than trusting the directory name.
// =========================================================================

/** @return list<string> Absolute paths of every rule class in the library. */
function ruleSourceFiles(): array
{
    $directory = new RecursiveDirectoryIterator(dirname(__DIR__, 2) . '/src/Rules');
    $files = [];

    foreach (new RecursiveIteratorIterator($directory) as $file) {
        if ($file instanceof SplFileInfo && $file->getExtension() === 'php') {
            // Normalised to forward slashes: getPathname() returns the
            // platform separator, and tierOf() below looks for '/src/Rules/'.
            // On Windows that never matched, every rule's tier came back
            // empty, and the Database rules read as tier violations — which
            // is exactly how every Windows CI cell failed while Linux passed.
            $files[] = str_replace('\\', '/', $file->getPathname());
        }
    }

    sort($files);

    return $files;
}

/** Tier is the first path segment under src/Rules. */
function tierOf(string $path): string
{
    $marker = '/src/Rules/';
    $position = strpos($path, $marker);

    if ($position === false) {
        return '';
    }

    return explode('/', substr($path, $position + strlen($marker)))[0];
}

arch('pure-tier rules never reach the database, the gate or the network')
    ->expect('Simtabi\Laranail\Validation\Rules')
    ->not->toUse([DB::class, Gate::class, Http::class, HttpFactory::class])
    ->ignoring('Simtabi\Laranail\Validation\Rules\Database');

it('has no rule that writes', function (): void {
    // Read-only is the strongest claim the library makes about itself: a rule
    // runs on unvalidated input, often several times per request, and may run
    // during a precognitive preview the user never submits. Nothing in here
    // may persist anything.
    $writes = ['->save(', '->delete(', '->forceDelete(', '->update(', '->insert(', '->truncate(', 'DB::statement('];
    $offenders = [];

    foreach (ruleSourceFiles() as $file) {
        $source = (string) file_get_contents($file);

        foreach ($writes as $write) {
            if (str_contains($source, $write)) {
                $offenders[] = basename($file) . ' contains ' . $write;
            }
        }
    }

    expect($offenders)->toBeEmpty(implode('; ', $offenders));
});

it('confines database access to the Database tier', function (): void {
    $offenders = [];

    foreach (ruleSourceFiles() as $file) {
        if (tierOf($file) === 'Database') {
            continue;
        }

        $source = (string) file_get_contents($file);

        // newQuery() is the marker: every Eloquent read in this library goes
        // through it, and a rule outside the Database tier having one means
        // the tier label on its directory is now a lie.
        if (str_contains($source, '->newQuery(') || str_contains($source, 'DB::')) {
            $offenders[] = tierOf($file) . '/' . basename($file);
        }
    }

    expect($offenders)->toBeEmpty('database access outside the Database tier: ' . implode(', ', $offenders));
});

it('requires every Network-tier rule to be skippable during precognition', function (): void {
    // Laravel's precognition filter narrows by attribute, not by what a rule
    // does, so a network rule on a validated field fires once per debounced
    // keystroke unless it opts out. There are no Network rules yet; this
    // fails the moment one lands without the contract.
    $network = array_filter(ruleSourceFiles(), static fn (string $f): bool => tierOf($f) === 'Network');

    foreach ($network as $file) {
        $class = 'Simtabi\\Laranail\\Validation\\Rules\\Network\\' . basename($file, '.php');

        expect(class_exists($class) && is_a($class, PrecognitionSkippable::class, true))
            ->toBeTrue("{$class} performs network IO but is not PrecognitionSkippable");
    }

    expect(true)->toBeTrue();
});

it('keeps Database-tier rules out of the precognition opt-out', function (): void {
    // The inverse guard. Precognition exists partly so unique/exists give live
    // feedback; a Database rule that opted out would silently stop validating
    // during the preview and only fail on submit.
    foreach (ruleSourceFiles() as $file) {
        if (tierOf($file) !== 'Database') {
            continue;
        }

        $class = 'Simtabi\\Laranail\\Validation\\Rules\\Database\\' . basename($file, '.php');

        expect(is_a($class, PrecognitionSkippable::class, true))
            ->toBeFalse("{$class} is database-tier and must not opt out of precognition");
    }
});

it('resolves a tier for every rule file, on any platform', function (): void {
    // The guard for the bug this file had: tierOf() searched for the literal
    // '/src/Rules/', which never appears in a Windows path, so every rule's
    // tier came back EMPTY. The Database rules then looked like they were
    // reading the database from the wrong tier, and every Windows CI cell
    // failed while every Linux one passed.
    //
    // An empty tier must be a failure, not a shrug: with one, the tier
    // guarantees above are being checked against nothing.
    $files = ruleSourceFiles();

    expect($files)->not->toBeEmpty('no rule sources discovered at all');

    $untiered = array_values(array_filter($files, static fn (string $f): bool => tierOf($f) === ''));

    expect($untiered)->toBeEmpty('no tier resolved for: ' . implode(', ', $untiered));
});
