<?php

declare(strict_types=1);
use Simtabi\Laranail\Validation\Builder\Concerns\SelfValidates;
use Simtabi\Laranail\Validation\RuleSet;

arch('it will not use debugging functions')
    ->expect(['dd', 'dump', 'ray'])
    ->not->toBeUsed()
    ->ignoring(RuleSet::class)
    ->ignoring(SelfValidates::class);
