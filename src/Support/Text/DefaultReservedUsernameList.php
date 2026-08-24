<?php declare(strict_types=1);

namespace Simtabi\Laranail\Validation\Support\Text;

use Simtabi\Laranail\Validation\Contracts\ReservedUsernameList;
use Simtabi\Laranail\Validation\Rules\Text\Username;

/**
 * The default binding: {@see Username}'s own 35-name floor, unchanged, so
 * introducing the contract changed no verdict. One source of truth — this
 * class reads the rule's constant rather than restating it.
 */
final class DefaultReservedUsernameList implements ReservedUsernameList
{
    public function names(): array
    {
        return Username::DEFAULT_RESERVED;
    }
}
