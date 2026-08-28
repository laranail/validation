<?php

declare(strict_types=1);

use Simtabi\Laranail\Validation\Rules\Text\Username;
use Simtabi\Laranail\Validation\Contracts\ReservedUsernameList;
use Simtabi\Laranail\Validation\Support\Text\ExtendedReservedUsernameList;

it('keeps the floor as the default policy', function (): void {
    // Introducing the contract changed no verdict: 'chat' is merely
    // undesirable and stays allowed; 'admin' breaks something and stays
    // reserved.
    expect(ruleAccepts(new Username, 'chat'))->toBeTrue()
        ->and(ruleAccepts(new Username, 'admin'))->toBeFalse();
});

it('honours a bound list, including separator-stripped claims', function (): void {
    $this->app->singleton(ReservedUsernameList::class, ExtendedReservedUsernameList::class);

    expect(ruleAccepts(new Username, 'gitlab'))->toBeFalse()
        ->and(ruleAccepts(new Username, 'git.lab'))->toBeFalse()
        ->and(ruleAccepts(new Username, 'imani'))->toBeTrue();
});

it('lets an explicit reserved: list win over the binding', function (): void {
    $this->app->singleton(ReservedUsernameList::class, ExtendedReservedUsernameList::class);

    expect(ruleAccepts(new Username(reserved: ['imani']), 'gitlab'))->toBeTrue()
        ->and(ruleAccepts(new Username(reserved: ['imani']), 'imani'))->toBeFalse();
});

it('ships the extended set without the profanity entries', function (): void {
    $names = new ExtendedReservedUsernameList()->names();

    // The archive's list mixed reserved names with profanity; what counts
    // as offensive is TermList policy, so none of it ships here.
    expect(count($names))->toBeGreaterThan(300)
        ->and($names)->toContain('admin', 'api', 'www', 'gitlab', 'microsoft')
        ->not->toContain('shit', 'porn', 'rnail', 'cnarne');
});
