<?php declare(strict_types=1);

use Simtabi\Laranail\Validation\FluentRule;

return [
    'email' => FluentRule::field()->required()->nullable()->exists('users', 'email'),
    'tags' => FluentRule::field()->present()->children([]),
];
