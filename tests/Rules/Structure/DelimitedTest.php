<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Validator;
use Simtabi\Laranail\Validation\Rules\Codes\Isbn;
use Simtabi\Laranail\Validation\Rules\Structure\Delimited;

/** The message a rule produced, so assertions can check which failure fired. */
function delimitedError(Delimited $rule, mixed $value): string
{
    return Validator::make(['f' => $value], ['f' => $rule])->errors()->first('f');
}

it('accepts a list whose every item passes the sub-rules', function (string $value): void {
    expect(ruleAccepts(new Delimited(['email']), $value))->toBeTrue();
})->with([
    'single' => 'alice@example.com',
    'two' => 'alice@example.com,bob@example.com',
    'spaced' => 'alice@example.com, bob@example.com',
    'lots of space' => '  alice@example.com  ,  bob@example.com  ',
]);

it('rejects the list when any single item fails', function (string $value): void {
    expect(ruleAccepts(new Delimited(['email']), $value))->toBeFalse();
})->with([
    'first bad' => 'nope,bob@example.com',
    'last bad' => 'alice@example.com,nope',
    'middle bad' => 'alice@example.com,nope,bob@example.com',
]);

it('names the offending position rather than just failing', function (): void {
    // With a dozen addresses in one box, "entry 3 is invalid" is the
    // difference between fixing it and bisecting by hand.
    $error = delimitedError(new Delimited(['email']), 'alice@example.com,bob@example.com,nope');

    expect($error)->toContain('3');
});

it('reports an empty item separately from a failing one', function (): void {
    // A doubled or trailing separator is a punctuation mistake, and saying so
    // points at the comma rather than at whatever `email` says about ''.
    $empty = delimitedError(new Delimited(['email']), 'alice@example.com,,bob@example.com');
    $invalid = delimitedError(new Delimited(['email']), 'alice@example.com,nope');

    expect($empty)->not->toBe($invalid)
        ->and(ruleAccepts(new Delimited(['email']), 'alice@example.com,'))->toBeFalse();
});

it('enforces item count bounds', function (): void {
    $rule = new Delimited(['string'], min: 2, max: 3);

    expect(ruleAccepts($rule, 'a'))->toBeFalse()
        ->and(ruleAccepts($rule, 'a,b'))->toBeTrue()
        ->and(ruleAccepts($rule, 'a,b,c'))->toBeTrue()
        ->and(ruleAccepts($rule, 'a,b,c,d'))->toBeFalse();
});

it('allows duplicates by default and rejects them on request', function (): void {
    // A repeated recipient is something to deduplicate, not refuse.
    expect(ruleAccepts(new Delimited(['email']), 'a@x.test,a@x.test'))->toBeTrue()
        ->and(ruleAccepts(new Delimited(['email'], distinct: true), 'a@x.test,a@x.test'))->toBeFalse()
        ->and(ruleAccepts(new Delimited(['email'], distinct: true), 'a@x.test,b@x.test'))->toBeTrue();
});

it('honours a custom separator', function (): void {
    $rule = new Delimited(['email'], separator: ';');

    expect(ruleAccepts($rule, 'a@x.test;b@x.test'))->toBeTrue()
        // With ';' as the separator, a comma is just part of the item — and
        // an item containing a comma is not an email.
        ->and(ruleAccepts($rule, 'a@x.test,b@x.test'))->toBeFalse();
});

it('can be told not to trim', function (): void {
    expect(ruleAccepts(new Delimited(['email']), 'a@x.test, b@x.test'))->toBeTrue()
        ->and(ruleAccepts(new Delimited(['email'], trim: false), 'a@x.test, b@x.test'))->toBeFalse();
});

it('accepts rule objects as well as strings', function (): void {
    // The sub-rules are whatever Laravel accepts, so the rest of this library
    // composes into it without special-casing.
    $rule = new Delimited([new Isbn]);

    expect(ruleAccepts($rule, '0306406152,9780306406157'))->toBeTrue()
        ->and(ruleAccepts($rule, '0306406152,not-an-isbn'))->toBeFalse();
});

it('rejects a non-string value', function (mixed $value): void {
    expect(ruleAccepts(new Delimited(['string']), $value))->toBeFalse();
})->with([
    'array' => [['a', 'b']],
    'int' => [42],
]);

it('refuses an empty separator at construction', function (): void {
    // explode() throws on an empty separator, so without this the failure
    // would surface deep inside validation of whichever request happened to
    // reach the rule first, rather than where the mistake was made.
    expect(fn (): Delimited => new Delimited(['string'], separator: ''))
        ->toThrow(InvalidArgumentException::class);
});
