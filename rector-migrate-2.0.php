<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;
use Rector\Renaming\Rector\Name\RenameClassRector;

/**
 * The 1.x → 2.0 migration set (UPGRADING.md, v2.0.0). Run it against an
 * application's own code:
 *
 *   vendor/bin/rector process app/ \
 *       --config vendor/laranail/validation/rector-migrate-2.0.php
 *
 * It applies the mechanical break only — the service provider moved into a
 * `Providers/` sub-namespace, and anything naming the class explicitly needs
 * the new name. Auto-discovered registrations need nothing.
 *
 * The other 2.0 break, the `laranail-validation::` → `laranail/validation::`
 * translation namespace, is a change to string literals. Rector has no clean
 * rule for that, and a rule that rewrote arbitrary strings would be worse than
 * the find-and-replace UPGRADING.md gives you.
 *
 * Paths worth passing beyond `app/`: `tests/` (a Testbench
 * `getPackageProviders()` is the most common site), `config/` and
 * `bootstrap/`. `testbench.yaml` is not PHP and Rector will not see it — grep
 * for it by hand.
 */
return RectorConfig::configure()
    // Without this the rename lands as a fully-qualified name inline and leaves the old `use`
    // sitting above it -- correct, but not what anyone wants to read in their own diff.
    ->withImportNames(importShortClasses: false, removeUnusedImports: true)
    ->withConfiguredRule(RenameClassRector::class, [
        'Simtabi\Laranail\Validation\ValidationServiceProvider' => 'Simtabi\Laranail\Validation\Providers\ValidationServiceProvider',
    ]);
