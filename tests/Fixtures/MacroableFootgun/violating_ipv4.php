<?php declare(strict_types=1);

use Simtabi\Laranail\Validation\FluentRule;

return [
    'ip' => FluentRule::field()->ipv4(),
];
