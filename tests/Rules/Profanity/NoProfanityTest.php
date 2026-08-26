<?php declare(strict_types=1);

use Illuminate\Contracts\Translation\Translator;
use Illuminate\Translation\PotentiallyTranslatedString;
use Simtabi\Laranail\Validation\Contracts\TermList;
use Simtabi\Laranail\Validation\Rules\Profanity\NoProfanity;

/**
 * No real word list is used here, and none ships with the package — see
 * Contracts\TermList for why. "badger" stands in for a term: it is an
 * ordinary word, which keeps the tests readable, and every property under
 * test is about the MATCHING rather than about any particular vocabulary.
 */
/**
 * @param  list<string>  $terms
 * @param  list<string>  $allowed
 */
function termList(array $terms, array $allowed = []): NoProfanity
{
    return new NoProfanity($terms, $allowed);
}

it('matches a plain term', function (): void {
    expect(termList(['badger'])->containsTerm('what a badger'))->toBeTrue()
        ->and(termList(['badger'])->containsTerm('nothing here'))->toBeFalse();
});

it('sees through common character substitutions', function (string $value): void {
    // A list that only matches the literal spelling is decorative.
    expect(termList(['badger'])->containsTerm($value))->toBeTrue();
})->with(['b4dger', 'b@dger', 'badg3r', 'b4dg3r', 'BADGER', 'Ba Dger' => 'ba dger']);

it('sees through separators and accents', function (string $value): void {
    expect(termList(['badger'])->containsTerm($value))->toBeTrue();
})->with(['b.a.d.g.e.r', 'b-a-d-g-e-r', 'b a d g e r', 'bádgér', 'ｂａｄｇｅｒ']);

it('collapses runs of a repeated character', function (): void {
    expect(termList(['badger'])->containsTerm('baaaadger'))->toBeTrue()
        ->and(termList(['badger'])->containsTerm('badgerrrrr'))->toBeTrue();
});

it('leaves separators and runs in the value, handling them in the pattern', function (): void {
    // Rewriting them here would destroy the word boundary that stops a short
    // term matching inside a longer word. Normalisation only undoes character
    // substitution.
    expect(NoProfanity::normalise('bookkeeper'))->toBe('bookkeeper')
        ->and(NoProfanity::normalise('b.a.d.g.e.r'))->toBe('b.a.d.g.e.r')
        ->and(NoProfanity::normalise('b4dg3r'))->toBe('badger');
});

it('does not match a term inside a longer word', function (): void {
    // The Scunthorpe problem. Without word boundaries any list with a short
    // term rejects real place names and ordinary vocabulary, and the people
    // it rejects are the least able to work around it.
    expect(termList(['ass'])->containsTerm('assess the class'))->toBeFalse()
        ->and(termList(['ass'])->containsTerm('what an ass'))->toBeTrue();
});

it('honours an allow-list for words that genuinely contain a term', function (): void {
    $rule = termList(['cunt'], allowed: ['scunthorpe']);

    expect($rule->containsTerm('I live in Scunthorpe'))->toBeFalse();
});

it('checks the allow-list before the terms, not after', function (): void {
    // Order matters: checking terms first rejects the value before the
    // allow-list is ever consulted.
    $rule = termList(['badger'], allowed: ['badger badger']);

    expect($rule->containsTerm('badger badger'))->toBeFalse()
        ->and($rule->containsTerm('one badger'))->toBeTrue();
});

it('removes longer allow-list entries first', function (): void {
    // A short term inside a longer allowed phrase must not match once the
    // phrase is removed.
    $rule = termList(['ass'], allowed: ['ass kicking', 'ass']);

    expect($rule->containsTerm('ass kicking'))->toBeFalse();
});

it('passes everything when no terms are configured', function (): void {
    // Not a silent failure: with no list there is nothing to match, and the
    // application chose not to supply one.
    expect(termList([])->containsTerm('badger'))->toBeFalse();
});

it('accepts a TermList implementation', function (): void {
    $list = new class implements TermList {
        public function terms(): array
        {
            return ['badger'];
        }

        public function allowed(): array
        {
            return ['badgerline'];
        }
    };

    $rule = new NoProfanity($list);

    expect($rule->containsTerm('a badger'))->toBeTrue()
        ->and($rule->containsTerm('the badgerline service'))->toBeFalse();
});

it('never echoes the matched term in its message', function (): void {
    // Repeating it prints the word on the user's screen, and naming it tells
    // someone probing the filter what to obfuscate next.
    $key = null;
    new NoProfanity(['badger'])->validate('bio', 'a badger', function (string $k) use (&$key): PotentiallyTranslatedString {
        $key = $k;

        return new PotentiallyTranslatedString('', resolve(Translator::class));
    });

    expect($key)->toBe('laranail/validation::validation.no_profanity')
        ->and($key)->not->toContain('badger');
});

it('rejects a non-string', function (mixed $value): void {
    $failed = false;
    new NoProfanity(['badger'])->validate('bio', $value, function () use (&$failed): PotentiallyTranslatedString {
        $failed = true;

        return new PotentiallyTranslatedString('', resolve(Translator::class));
    });

    expect($failed)->toBeTrue();
})->with([null, 123, [['badger']], true]);

it('does not hang on input designed to make it backtrack', function (): void {
    // This rule runs over user-submitted free text, which is the worst place
    // for a regex that can be made to hang. The separator quantifier is
    // possessive and matches a disjoint class from the character repeats, so
    // the two never compete for the same input.
    $rule = termList(['badger'], allowed: ['badgerline']);
    $hostile = str_repeat('b', 20000) . str_repeat('.', 20000) . str_repeat('a', 20000);

    $start = microtime(true);
    $rule->containsTerm($hostile);
    $elapsed = (microtime(true) - $start) * 1000;

    expect($elapsed)->toBeLessThan(1000.0);
});
