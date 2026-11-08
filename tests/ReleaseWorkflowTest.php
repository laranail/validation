<?php

declare(strict_types=1);

/*
 * The workflow that updates a release must not leave it as a draft.
 *
 * DELETING a tag reverts its GitHub release to a DRAFT, and `gh release edit` leaves that flag exactly as it
 * finds it. So an edit after a tag is deleted and re-pushed republishes nothing on a run that still goes
 * GREEN: correct tag, invisible release. This repository already holds two such orphans -- the v1.0.0 and
 * v1.0.1 releases, still drafts since those tags were withdrawn on 2026-08-24. Measured as a live failure in
 * laranail/validation-js on 2026-11-08, whose release had to be published by hand (laranail/validation-js#35).
 *
 * Nothing else reports that state: the tag resolves, composer installs, and verify-tag-currency.sh passes,
 * because none of them reads the release.
 *
 * This reads EVERY workflow file rather than the two call sites, so a workflow added later inherits the rule.
 * A release deliberately left as a draft would fail here, which is the right place to have to think about it.
 */

/** @return list<string> every `gh release edit` command in the repository's workflows, labelled by file */
function releaseEditCommands(): array
{
    $dir = __DIR__ . '/../.github/workflows';
    $files = [];

    // glob() answers false on failure, so each result is checked rather than coalesced: validation's
    // PHPStan config forbids the short ternary, and the copies in the family keep one shape.
    foreach (['*.yml', '*.yaml'] as $pattern) {
        $matched = glob($dir . '/' . $pattern);

        if (is_array($matched)) {
            $files = [...$files, ...$matched];
        }
    }

    // A glob that stops matching must fail here rather than report a clean sweep.
    expect(count($files))->toBeGreaterThanOrEqual(13);

    $commands = [];

    foreach ($files as $file) {
        $raw = file_get_contents($file);
        $lines = is_string($raw) ? explode("\n", $raw) : [];
        // Drop comment lines first -- the prose beside the command names it too -- then join shell line
        // continuations, so a flag written on the next line still counts as part of the same command.
        $code = implode("\n", array_filter($lines, static fn (string $line): bool => preg_match('/^\s*#/', $line) !== 1));
        preg_match_all('/gh release edit[^\n]*/', (string) preg_replace('/\\\\\n\s*/', ' ', $code), $found);

        foreach ($found[0] as $command) {
            $commands[] = basename($file) . ': ' . $command;
        }
    }

    return $commands;
}

it('clears the draft flag on every release edit, because deleting a tag sets it', function (): void {
    $commands = releaseEditCommands();

    // Non-vacuity: no match at all means the matcher broke, not that the workflows are clean.
    expect($commands)->not->toBeEmpty('no gh release edit was found -- check the matcher, not the workflows');

    // Both call sites must carry it: release.yml's update path and release-benchmark.yml's table injection.
    expect(count($commands))->toBeGreaterThanOrEqual(2);

    expect($commands)->each->toContain('--draft=false');
});
