<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Validation\Contracts;

use Simtabi\Laranail\Validation\Rules\Text\Username;
use Simtabi\Laranail\Validation\Support\Text\ExtendedReservedUsernameList;

/**
 * The names {@see Username} refuses when no explicit `reserved:` list is
 * given.
 *
 * The default binding is the rule's own 35-name floor — names that break
 * something concrete (impersonation, route collisions), per the rule's
 * documented philosophy. The package also ships
 * {@see ExtendedReservedUsernameList},
 * a ~350-entry curated set covering the wider infrastructure/product
 * surface; bind it (or your own) to swap policies application-wide without
 * touching every rule:
 *
 *     $this->app->singleton(ReservedUsernameList::class, ExtendedReservedUsernameList::class);
 */
interface ReservedUsernameList
{
    /**
     * The reserved names, lowercase. Matching (case folding, separator
     * stripping) is the rule's job, not the list's.
     *
     * @return list<string>
     */
    public function names(): array;
}
