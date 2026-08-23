<?php declare(strict_types=1);

use Illuminate\Contracts\Validation\ValidationRule;
use Simtabi\Laranail\Validation\Contracts\ClientCheckable;

/**
 * Numbers and inventories stated in prose drift the moment the code moves —
 * the CHANGELOG claimed 49 rules against a library of 54, its ClientCheckable
 * list was one implementer short, and the rule reference had no section for
 * the whole Telecom family. Nothing failed, so nobody noticed. These tests
 * make every such claim one CI checks against the live source.
 */

/** @return list<class-string<ValidationRule>> */
function liveRuleClasses(): array
{
    $root = dirname(__DIR__) . '/src/Rules';
    $classes = [];

    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root)) as $file) {
        if (! $file instanceof SplFileInfo || $file->getExtension() !== 'php') {
            continue;
        }

        $relative = substr($file->getPathname(), strlen($root) + 1, -4);
        $class = 'Simtabi\\Laranail\\Validation\\Rules\\' . str_replace('/', '\\', $relative);

        if (class_exists($class) && is_subclass_of($class, ValidationRule::class)) {
            $classes[] = $class;
        }
    }

    sort($classes);

    return $classes;
}

it('states the real rule count in the CHANGELOG', function (): void {
    $actual = count(liveRuleClasses());
    $prose = (string) file_get_contents(dirname(__DIR__) . '/CHANGELOG.md');

    preg_match_all('/(\d+) rules across/', $prose, $m);

    expect($m[1])->not->toBeEmpty('CHANGELOG no longer states the rule count this test pins.');

    foreach ($m[1] as $stated) {
        expect((int) $stated)->toBe(
            $actual,
            "CHANGELOG claims {$stated} rules; src/Rules holds {$actual}.",
        );
    }
});

it('documents every rule family with a section in the rule reference', function (): void {
    // Directory name → the reference page's heading, where they differ.
    $headings = ['AntiSpam' => 'Anti-spam', 'Vendor' => 'Vendor identifiers'];
    $reference = (string) file_get_contents(dirname(__DIR__) . '/docs/tools/rule-library.md');

    $entries = scandir(dirname(__DIR__) . '/src/Rules');
    $families = array_values(array_filter(
        $entries === false ? [] : $entries,
        static fn (string $entry): bool => ! str_starts_with($entry, '.')
            && is_dir(dirname(__DIR__) . '/src/Rules/' . $entry),
    ));

    foreach ($families as $family) {
        $heading = '## ' . ($headings[$family] ?? $family);

        expect(str_contains($reference, "\n{$heading}\n"))->toBeTrue(
            "src/Rules/{$family} has no \"{$heading}\" section in docs/tools/rule-library.md — the whole family is undocumented.",
        );
    }
});

it('names every ClientCheckable implementer in the CHANGELOG', function (): void {
    $prose = (string) file_get_contents(dirname(__DIR__) . '/CHANGELOG.md');

    $implementers = array_values(array_filter(
        liveRuleClasses(),
        static fn (string $class): bool => is_subclass_of($class, ClientCheckable::class),
    ));

    expect($implementers)->not->toBeEmpty();

    foreach ($implementers as $class) {
        $short = str_replace('Simtabi\\Laranail\\Validation\\Rules\\', '', $class);

        expect(str_contains($prose, "`{$short}`"))->toBeTrue(
            "ClientCheckable implementer {$short} is missing from the CHANGELOG's list.",
        );
    }
});
