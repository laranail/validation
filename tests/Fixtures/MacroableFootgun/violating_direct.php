<?php declare(strict_types=1);

use Simtabi\Laranail\Validation\FluentRule;

return [
    'age' => FluentRule::field()->min(5),
];
