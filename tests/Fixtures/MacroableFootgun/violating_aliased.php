<?php declare(strict_types=1);

use Simtabi\Laranail\Validation\FluentRule as Rule;

return [
    'age' => Rule::field()->min(5),
];
