<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;
use RectorPest\Set\PestSetList;
use RectorLaravel\Set\LaravelSetList;
use RectorPest\Rules\UseToMatchRector;
use Rector\Caching\ValueObject\Storage\FileCacheStorage;
use Rector\Carbon\Rector\FuncCall\DateFuncCallToCarbonRector;
use Rector\DeadCode\Rector\ClassMethod\RemoveUselessParamTagRector;
use Rector\DeadCode\Rector\ClassMethod\RemoveUselessReturnTagRector;
use Rector\Php81\Rector\FuncCall\NullToStrictStringFuncCallArgRector;
use Rector\CodeQuality\Rector\BooleanOr\RepeatedOrEqualToInArrayRector;
use Rector\CodeQuality\Rector\ClassMethod\InlineArrayReturnAssignRector;
use Rector\Privatization\Rector\ClassMethod\PrivatizeFinalClassMethodRector;
use RectorLaravel\Rector\Class_\RemoveDefaultAuthorizeTrueFromFormRequestRector;
use Rector\TypeDeclaration\Rector\ArrowFunction\AddArrowFunctionReturnTypeRector;

return RectorConfig::configure()
    ->withCache(
        cacheDirectory: './.cache/rector',
        cacheClass: FileCacheStorage::class,
        containerCacheDirectory: './.cache/rectorContainer',
    )
    ->withPaths([
        __DIR__ . '/src',
        __DIR__ . '/tests',
    ])
    ->withSkipPath(__DIR__ . '/tests/Fixtures/MacroableFootgun')
    ->withPreparedSets(
        deadCode: true,
        codeQuality: true,
        codingStyle: true,
        typeDeclarations: true,
        typeDeclarationDocblocks: true,
        privatization: true,
        instanceOf: true,
        earlyReturn: true,
        carbon: true,
        rectorPreset: true,
        phpunitCodeQuality: true,
    )
    ->withAttributesSets()
    ->withImportNames()
    ->withIndent()
    ->withFluentCallNewLine()
    ->withParallel(300, 15, 15)
    ->withMemoryLimit('3G')
    ->withPhpSets(php84: true)
    ->withSets([
        LaravelSetList::LARAVEL_110,
        LaravelSetList::LARAVEL_CODE_QUALITY,
        LaravelSetList::LARAVEL_ARRAYACCESS_TO_METHOD_CALL,
        LaravelSetList::LARAVEL_CONTAINER_STRING_TO_FULLY_QUALIFIED_NAME,
        LaravelSetList::LARAVEL_FACADE_ALIASES_TO_FULL_NAMES,
        PestSetList::PEST_CODE_QUALITY,
        PestSetList::PEST_CHAIN,
        PestSetList::PEST_LARAVEL,
    ])
    ->withSkip([
        // PostIncDecToPreIncDecRector, EncapsedStringsToSprintfRector and
        // ExplicitBoolCompareRector were skipped here until Rector 2.9 removed
        // them (deprecated in 2.8, in no set). rector/rector is floored at ^2.9
        // so no resolvable Rector still ships them. The first was skipped
        // because it fought Pint over $i++ vs ++$i.
        DateFuncCallToCarbonRector::class,
        NullToStrictStringFuncCallArgRector::class,
        AddArrowFunctionReturnTypeRector::class,
        InlineArrayReturnAssignRector::class,
        PrivatizeFinalClassMethodRector::class,
        RemoveUselessParamTagRector::class,
        RemoveUselessReturnTagRector::class,
        // Rewrites expect(preg_match($p, 'x'))->toBe(1) into expect('x')
        // ->toMatch($p), but on chained expectations it swaps the head
        // subject while leaving ->toBe(1) behind and converts ->and()
        // links without changing their subject — both produce failing
        // (or nonsensical) assertions. mrpunyapal/rector-pest 0.2.17.
        UseToMatchRector::class,
        // rector-laravel 2.7 deletes an authorize() that returns true. Laravel
        // treats a missing authorize() as authorized, so src behaves the same,
        // but the test fixtures define it on purpose (AuthorizedEach...,
        // MergeRulesBaseRequest) to cover the path where the method exists.
        RemoveDefaultAuthorizeTrueFromFormRequestRector::class,
        // Hot-path closure allocates a literal array on every invocation
        // when in_array() is used. Explicit === comparisons avoid that.
        RepeatedOrEqualToInArrayRector::class => [
            __DIR__ . '/src/FastCheckCompiler.php',
        ],
    ]);
