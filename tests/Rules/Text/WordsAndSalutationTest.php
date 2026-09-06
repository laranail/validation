<?php

declare(strict_types=1);

use Simtabi\Laranail\Validation\Rules\Text\MaxWords;
use Simtabi\Laranail\Validation\Rules\Text\MinWords;
use Simtabi\Laranail\Validation\Rules\Text\HtmlClean;
use Simtabi\Laranail\Validation\Rules\Text\Salutation;

// =========================================================================
// MaxWords / MinWords
// =========================================================================

it('counts words for MaxWords, unicode included', function (): void {
    expect(ruleAccepts(new MaxWords(3), 'one two three'))->toBeTrue()
        ->and(ruleAccepts(new MaxWords(3), 'one two three four'))->toBeFalse()
        ->and(ruleAccepts(new MaxWords(3), 'háblame más despacio'))->toBeTrue()
        ->and(ruleAccepts(new MaxWords(1), "don't"))->toBeTrue();
});

it('does not count surrounding whitespace as words', function (): void {
    // The legacy splitter counted the empty fragments before and after the
    // text, so "  two words  " was four and failed a max of two.
    expect(ruleAccepts(new MaxWords(2), '  two words  '))->toBeTrue()
        ->and(ruleAccepts(new MinWords(2), '  two words  '))->toBeTrue();
});

it('enforces MinWords', function (): void {
    expect(ruleAccepts(new MinWords(2), 'one two'))->toBeTrue()
        ->and(ruleAccepts(new MinWords(2), 'one'))->toBeFalse()
        ->and(ruleAccepts(new MinWords(1), 'word'))->toBeTrue();
});

it('rejects non-strings for word counts', function (): void {
    expect(ruleAccepts(new MaxWords(3), 12))->toBeFalse()
        ->and(ruleAccepts(new MinWords(1), ['a b']))->toBeFalse();
});

it('refuses a non-positive word bound', function (): void {
    new MaxWords(0);
})->throws(LogicException::class);

it('refuses a non-positive minimum word bound', function (): void {
    new MinWords(0);
})->throws(LogicException::class);

// =========================================================================
// HtmlClean's inverse — requiring markup
// =========================================================================

it('can require markup instead of forbidding it', function (): void {
    expect(ruleAccepts(new HtmlClean(mustContainHtml: true), '<p>rich</p>'))->toBeTrue()
        ->and(ruleAccepts(new HtmlClean(mustContainHtml: true), 'plain text'))->toBeFalse()
        // The same subtleties as the forbidding direction, mirrored: encoded
        // markup and a bare `<` are prose, so they do not count as markup.
        ->and(ruleAccepts(new HtmlClean(mustContainHtml: true), '&lt;p&gt;'))->toBeFalse()
        ->and(ruleAccepts(new HtmlClean(mustContainHtml: true), '5 < 10'))->toBeFalse();
});

it('keeps the forbidding default unchanged', function (): void {
    expect(ruleAccepts(new HtmlClean, 'plain text'))->toBeTrue()
        ->and(ruleAccepts(new HtmlClean, '<b>bold</b>'))->toBeFalse();
});

// =========================================================================
// Salutation
// =========================================================================

it('accepts common salutations in any case, with or without the dot', function (string $value): void {
    expect(ruleAccepts(new Salutation, $value))->toBeTrue();
})->with(['Mr', 'mr', 'Mr.', 'MRS', 'Prof.', 'Mx', 'Dr.', 'Madame']);

it('rejects values that are not salutations', function (mixed $value): void {
    expect(ruleAccepts(new Salutation, $value))->toBeFalse();
})->with(['xyz', 'Mister John', 'M r', 12, null]);

it('validates against a custom salutation list when given one', function (): void {
    $rule = new Salutation(['eng', 'mwl']);

    expect(ruleAccepts($rule, 'Eng.'))->toBeTrue()
        ->and(ruleAccepts($rule, 'Mwl'))->toBeTrue()
        ->and(ruleAccepts($rule, 'Mr'))->toBeFalse();
});
