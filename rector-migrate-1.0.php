<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;
use Rector\Renaming\Rector\MethodCall\RenameMethodRector;
use Rector\Renaming\ValueObject\MethodCallRename;
use Simtabi\Laranail\Validation\Builder\Nodes\ArrayRule;

/**
 * The 0.x → 1.0 migration set (UPGRADING.md, v1.0.0). Run it against an
 * application's own code:
 *
 *   vendor/bin/rector process app/ \
 *       --config vendor/laranail/validation/rector-migrate-1.0.php
 *
 * It applies the mechanical break only — `getEachRules()` was an exact
 * alias of `getEachKeyedRules()` and is renamed one-for-one. The
 * behavioural 1.0 changes (the PHP `^8.5` floor, the removed Laravel-11
 * branches) need no code change beyond what UPGRADING.md describes.
 */
return RectorConfig::configure()
    ->withConfiguredRule(RenameMethodRector::class, [
        new MethodCallRename(ArrayRule::class, 'getEachRules', 'getEachKeyedRules'),
    ]);
