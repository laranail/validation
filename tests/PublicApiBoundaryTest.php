<?php declare(strict_types=1);

/**
 * The §12.1 boundary, enforced rather than described: everything under
 * `Internal\` and every optimizer class is `@internal` (free to change in a
 * minor), and nothing on the stable list is. A class added to either side
 * without the right marker fails here, so the 1.0 SemVer promise stays a
 * property of the code instead of a paragraph in the README.
 */
const INTERNAL_TOP_LEVEL = [
    'src/OptimizedValidator.php',
    'src/MemoizingValidator.php',
    'src/BatchDatabaseChecker.php',
    'src/PrecomputedPresenceVerifier.php',
    'src/FluentValidator.php',
    'src/FastCheckCompiler.php',
    'src/WildcardExpander.php',
    'src/PreparedRules.php',
    'src/PresenceConditionalReducer.php',
    'src/ValueConditionalReducer.php',
];

const STABLE_SURFACE = [
    'src/FluentRule.php',
    'src/FluentSchema.php',
    'src/RuleSet.php',
    'src/Check.php',
    'src/Regex.php',
    'src/Validation.php',
    'src/Validated.php',
    'src/HasFluentRules.php',
    'src/HasFluentValidation.php',
    'src/FluentFormRequest.php',
    'src/Support/RuleRegistrar.php',
    'src/Events/RuleSetCompiling.php',
    'src/Events/ValidationStarting.php',
    'src/Events/ValidationCompleted.php',
    'src/Events/ValidationFailed.php',
    'src/Contracts/ClientCheckable.php',
    'src/Contracts/PrecognitionSkippable.php',
    'src/Contracts/TermList.php',
];

it('marks every Internal\\ and FastCheck\\ class @internal', function (): void {
    $files = [
        ...glob(dirname(__DIR__) . '/src/Internal/*.php') ?: [],
        ...glob(dirname(__DIR__) . '/src/FastCheck/*.php') ?: [],
        ...glob(dirname(__DIR__) . '/src/FastCheck/Shared/*.php') ?: [],
    ];

    expect($files)->not->toBeEmpty();

    foreach ($files as $file) {
        expect(str_contains((string) file_get_contents($file), '@internal'))->toBeTrue(
            basename($file) . ' lives in an internal namespace but is not marked @internal.',
        );
    }
});

it('marks every top-level optimizer class @internal', function (): void {
    foreach (INTERNAL_TOP_LEVEL as $path) {
        expect(str_contains((string) file_get_contents(dirname(__DIR__) . '/' . $path), '@internal'))->toBeTrue(
            $path . ' is optimizer machinery and must carry @internal.',
        );
    }
});

it('never marks the stable surface @internal at class level', function (): void {
    foreach (STABLE_SURFACE as $path) {
        $source = (string) file_get_contents(dirname(__DIR__) . '/' . $path);

        // Only the CLASS-level docblock counts: a stable class may still
        // mark an individual method @internal. Inspect the source up to the
        // first class/trait/interface declaration.
        preg_match('/^(?:final |abstract |readonly )*(?:class|trait|interface|enum) /m', $source, $m, PREG_OFFSET_CAPTURE);
        $head = $m === [] ? $source : substr($source, 0, (int) $m[0][1]);

        expect(str_contains($head, '@internal'))->toBeFalse(
            $path . ' is on the §12.1 stable list — a class-level @internal there breaks the SemVer promise.',
        );
    }
});
