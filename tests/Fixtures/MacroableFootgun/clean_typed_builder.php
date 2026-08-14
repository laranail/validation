<?php declare(strict_types=1);

use Simtabi\Laranail\Validation\FluentRule;

return [
    'age' => FluentRule::numeric()->min(5),
    'name' => FluentRule::string()->between(1, 10),
];
