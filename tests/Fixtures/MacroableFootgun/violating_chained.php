<?php

declare(strict_types=1);

use Simtabi\Laranail\Validation\FluentRule;

return [
    'size' => FluentRule::field()->required()->nullable()->between(1, 10),
];
