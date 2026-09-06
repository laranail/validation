<?php

declare(strict_types=1);
use Simtabi\Laranail\Validation\RuleSet;
use Simtabi\Laranail\Validation\Builder\Concerns\SelfValidates;

arch('it will not use debugging functions')
    ->expect(['dd', 'dump', 'ray'])
    ->not->toBeUsed()
    ->ignoring(RuleSet::class)
    ->ignoring(SelfValidates::class);
