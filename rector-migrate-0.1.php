<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;
use Rector\Renaming\Rector\Name\RenameClassRector;

/**
 * The v0.1.0 migration set (UPGRADING.md). Run it against an
 * application's own code:
 *
 *   vendor/bin/rector process app/ \
 *       --config vendor/laranail/validation/rector-migrate-0.1.php
 *
 * It applies the mechanical break only — the service provider moved into a
 * `Providers/` sub-namespace, and anything naming the class explicitly needs
 * the new name. Auto-discovered registrations need nothing.
 *
 * The other break, the `laranail-validation::` → `laranail/validation::`
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
    //
    // `importShortClasses: false` deliberately diverges from this repo's own rector.php, which takes
    // the defaults. It gates exactly one thing: ShortClassImportSkipVoter skips importing a class
    // only when `substr_count($className, '\\') === 0` -- a global-namespace class such as
    // \DateTime. The provider being renamed here carries four separators, so it is untouched by the
    // flag and is still imported as a `use`.
    //
    // The difference is whose code Rector is pointed at. rector.php runs over this repository, where
    // importing global classes everywhere is wanted. This config runs over a *consumer's*
    // application, where a one-class migration that also rewrites every \DateTime and \Exception in
    // their tree buries the rename in an unrelated diff.
    ->withImportNames(importShortClasses: false, removeUnusedImports: true)
    ->withConfiguredRule(RenameClassRector::class, [
        'Simtabi\Laranail\Validation\ValidationServiceProvider' => 'Simtabi\Laranail\Validation\Providers\ValidationServiceProvider',
    ]);
