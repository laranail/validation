<?php declare(strict_types=1);

use Simtabi\Laranail\Validation\Rules\Profanity\NoProfanity;
use Simtabi\Laranail\Validation\Support\InlineTermList;

/**
 * The bindable TermList over application-supplied arrays — the one-liner
 * between config (or a table) and the container, and the test fixture in the
 * same class. Behaviour through the rule is what matters: the class itself
 * is two getters.
 */
it('drives NoProfanity through the contract', function (): void {
    $rule = new NoProfanity(new InlineTermList(terms: ['badword'], allowed: []));

    expect(ruleAccepts($rule, 'a perfectly fine sentence'))->toBeTrue()
        ->and(ruleAccepts($rule, 'contains badword here'))->toBeFalse();
});

it('carries allowed() through the contract', function (): void {
    $bare = new NoProfanity(new InlineTermList(terms: ['badger']));
    $tolerant = new NoProfanity(new InlineTermList(terms: ['badger'], allowed: ['badger badger']));

    expect(ruleAccepts($bare, 'badger badger'))->toBeFalse()
        ->and(ruleAccepts($tolerant, 'badger badger'))->toBeTrue();
});
