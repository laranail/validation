<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Concerns\ValidatesAttributes;

/**
 * Laravel's `integer:strict` is honored by `validateInteger` only on
 * Laravel 12.23+ (it gained an `array $parameters` argument there). On
 * older Laravel the modifier is silently ignored — `validateInteger`
 * runs `filter_var(..., FILTER_VALIDATE_INT)` and accepts numeric
 * strings. Tests that assert strict-mode rejection through Laravel's
 * outer validator must skip on the older path.
 */
function laravelSupportsIntegerStrict(): bool
{
    $reflection = new ReflectionMethod(
        ValidatesAttributes::class,
        'validateInteger',
    );

    return count($reflection->getParameters()) >= 3;
}

/**
 * Pick out the compiled rules that are instances of a given rule class.
 *
 * Rule objects that can carry closures (Exists, Unique, Dimensions) must never
 * be stringified during compilation — __toString() silently drops them. Use
 * this to assert the object itself survived rather than its lossy string form.
 *
 * @param  list<object|string>  $rules
 * @param  class-string  $type
 * @return list<object>
 */
function rulesOfType(array $rules, string $type): array
{
    return array_values(array_filter(
        $rules,
        static fn (object|string $rule): bool => $rule instanceof $type,
    ));
}

/**
 * The compiled rule set, asserted to be in array form.
 *
 * `compiledRules()` returns `string|array`, and the array branch is itself the
 * property under test wherever a rule OBJECT is expected: a non-Stringable
 * rule cannot survive the pipe-string branch, so a set that stringified has
 * already lost it. Asserting here keeps that check at every call site.
 *
 * @return list<object|string>
 */
function compiledArray(mixed $compiled): array
{
    expect($compiled)->toBeArray();

    $rules = is_array($compiled) ? array_values($compiled) : [];

    $narrowed = array_values(array_filter(
        $rules,
        static fn (mixed $rule): bool => is_object($rule) || is_string($rule),
    ));

    // A compiled set holds rule strings and rule objects and nothing else.
    // Filtering silently would hide anything that slipped through, so the
    // counts have to match.
    expect($narrowed)->toHaveSameSize($rules);

    return $narrowed;
}

/**
 * Every rule class under `src/Rules`, discovered from the filesystem.
 *
 * The separator handling is the point. `SplFileInfo::getPathname()` returns
 * the platform's separator, so a path is joined with `\` on Windows and `/`
 * everywhere else. Building an FQN by replacing only `/` therefore produced a
 * name like `Rules\Banking\Iban` on Linux and `Rules/Banking/Iban` on
 * Windows, where `class_exists()` is false for every entry — so a
 * discovery-based guard found NOTHING and either passed over an empty set or
 * failed outright. Every Windows CI cell failed that way while every Linux one
 * passed.
 *
 * @param  class-string|null  $implementing  Restrict to classes of this type.
 * @return list<class-string>
 */
function ruleClassesUnder(?string $implementing = null): array
{
    $base = dirname(__DIR__).'/src/Rules';
    $classes = [];

    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($base)) as $file) {
        if (! $file instanceof SplFileInfo || $file->getExtension() !== 'php') {
            continue;
        }

        // Normalise to forward slashes FIRST, so the rest is platform-agnostic.
        $path = str_replace('\\', '/', $file->getPathname());
        $relative = substr($path, strlen(str_replace('\\', '/', $base)) + 1, -4);

        $class = 'Simtabi\\Laranail\\Validation\\Rules\\'.str_replace('/', '\\', $relative);

        if (! class_exists($class)) {
            continue;
        }

        if ($implementing !== null && ! is_a($class, $implementing, true)) {
            continue;
        }

        $classes[] = $class;
    }

    sort($classes);

    return $classes;
}

/**
 * Run a single rule object against a value and report whether it passed.
 *
 * Lives here rather than in a test file because several rule-family suites
 * share it and phpunit.xml.dist randomises execution order — a helper defined
 * in one test file is not reliably declared before another runs.
 */
function ruleAccepts(object $rule, mixed $value): bool
{
    return Validator::make(['f' => $value], ['f' => $rule])->passes();
}
