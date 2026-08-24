<?php declare(strict_types=1);

use Illuminate\Support\Facades\Validator;
use Simtabi\Laranail\Validation\FluentRule;
use Simtabi\Laranail\Validation\Regex;

/**
 * The fluent regex builder (§6.9) — safe-by-construction patterns for the
 * most error-prone corner of validation, with raw patterns staying a
 * first-class input everywhere. The P6/P7 bug class (a bare `$` matching
 * before a trailing newline) is what "safe by construction" concretely
 * means here: everything the builder emits carries `D`.
 */

// ---------------------------------------------------------------------------
// Raw patterns through Regex::of()
// ---------------------------------------------------------------------------

it('wraps an undelimited raw pattern with delimiters and D', function (): void {
    expect(Regex::of('^\d{3}-[A-Za-z]{2}$')->compile())->toBe('/^\d{3}-[A-Za-z]{2}$/D');
});

it('uses a delimited raw pattern verbatim, flags and all', function (): void {
    expect(Regex::of('/^[a-z]+$/i')->compile())->toBe('/^[a-z]+$/i')
        ->and(Regex::of('#^a/b$#')->compile())->toBe('#^a/b$#');
});

it('picks a delimiter the pattern does not contain', function (): void {
    // Wrapping 'a/b' with '/' would need escaping; picking '#' does not.
    $compiled = Regex::of('^a/b$')->compile();

    expect('a/b')->toMatch($compiled)
        ->and(preg_match($compiled, "a/b\n"))->toBe(0);
});

it('refuses a raw pattern that does not compile', function (): void {
    Regex::of('^([a-z]$');
})->throws(InvalidArgumentException::class);

// ---------------------------------------------------------------------------
// The builder vocabulary
// ---------------------------------------------------------------------------

it('builds the canonical part-number pattern, anchored with D by default', function (): void {
    $compiled = Regex::build()->digits(3)->literal('-')->letters(2)->compile();

    expect('123-Ab')->toMatch($compiled)
        ->and(preg_match($compiled, "123-Ab\n"))->toBe(0)   // D — the P6/P7 class
        ->and(preg_match($compiled, 'x123-Ab'))->toBe(0)    // anchored
        ->and(preg_match($compiled, '123-Abx'))->toBe(0);
});

it('escapes literals, so metacharacters mean themselves', function (): void {
    $compiled = Regex::build()->literal('a.b')->compile();

    expect('a.b')->toMatch($compiled)
        ->and(preg_match($compiled, 'axb'))->toBe(0);
});

it('offers oneOf with escaped alternatives', function (): void {
    $compiled = Regex::build()->oneOf('cat', 'dog', 'a.b')->compile();

    expect('cat')->toBe(1)
        ->and(preg_match($compiled, 'dog'))->toBe(1)
        ->and(preg_match($compiled, 'a.b'))->toMatch($compiled)
        ->and(preg_match($compiled, 'axb'))->toBe(0)
        ->and(preg_match($compiled, 'cow'))->toBe(0);
});

it('supports optional and oneOrMore groups', function (): void {
    $compiled = Regex::build()
        ->literal('v')
        ->digits()
        ->optional(fn (Regex $r): Regex => $r->literal('.')->digits())
        ->compile();

    expect('v1')->toBe(1)
        ->and(preg_match($compiled, 'v1.2'))->toMatch($compiled)
        ->and(preg_match($compiled, 'v1.'))->toBe(0);

    $repeated = Regex::build()
        ->oneOrMore(fn (Regex $r): Regex => $r->letters(1)->digits(1))
        ->compile();

    expect('a1b2')->toMatch($repeated)
        ->and(preg_match($repeated, ''))->toBe(0);
});

it('supports case-insensitive compilation and unanchored opt-out', function (): void {
    $ci = Regex::build()->literal('hello')->caseInsensitive()->compile();

    expect('HELLO')->toMatch($ci);

    $unanchored = Regex::build()->digits(3)->unanchored()->compile();

    expect('abc123def')->toMatch($unanchored);
});

it('refuses unbounded quantifiers nested inside unbounded groups', function (): void {
    // The catastrophic-backtracking shape — (a+)+ — must not be expressible
    // by accident. dangerouslyUnbounded() is the deliberate opt-in.
    Regex::build()->oneOrMore(fn (Regex $r): Regex => $r->oneOrMore(fn (Regex $inner): Regex => $inner->letters(1)));
})->throws(LogicException::class);

it('allows the nested-unbounded shape only behind the explicit opt-in', function (): void {
    $compiled = Regex::build()
        ->dangerouslyUnbounded()
        ->oneOrMore(fn (Regex $r): Regex => $r->oneOrMore(fn (Regex $inner): Regex => $inner->letters(1)))
        ->compile();

    expect('abc')->toMatch($compiled);
});

// ---------------------------------------------------------------------------
// The rule surface: every spelling produces the same rule
// ---------------------------------------------------------------------------

it('accepts all matches() spellings, producing one pattern', function (): void {
    $expected = ['string', 'regex:/^\d{3}-[A-Za-z]{2}$/D'];

    $builderSpelling = FluentRule::string()
        ->matches(fn (Regex $r): Regex => $r->digits(3)->literal('-')->letters(2))
        ->toArray()[1];
    assert(is_string($builderSpelling));
    $builderPattern = substr($builderSpelling, 6);

    expect(FluentRule::string()->matches('^\d{3}-[A-Za-z]{2}$')->toArray())->toBe($expected)
        ->and(FluentRule::string()->matches(Regex::of('^\d{3}-[A-Za-z]{2}$'))->toArray())->toBe($expected)
        // The builder spells the same match differently but must accept and
        // reject the same corpus as the raw spelling.
        ->and(preg_match($builderPattern, '123-Ab'))->toBe(1)
        ->and(preg_match($builderPattern, "123-Ab\n"))->toBe(0);
});

it('keeps the delimited matches() spelling verbatim', function (): void {
    expect(FluentRule::string()->matches('/^\d{3}-[A-Za-z]{2}$/')->toArray())
        ->toBe(['string', 'regex:/^\d{3}-[A-Za-z]{2}$/']);
});

it('widens regex() to accept a Regex or a builder closure', function (): void {
    // The existing string behaviour is untouched — used exactly as given.
    expect(FluentRule::string()->regex('/^[a-z]+$/')->toArray())->toBe(['string', 'regex:/^[a-z]+$/'])
        ->and(FluentRule::string()->regex(Regex::of('^[a-z]+$'))->toArray())->toBe(['string', 'regex:/^[a-z]+$/D'])
        ->and(FluentRule::string()->regex(fn (Regex $r): Regex => $r->letters())->toArray()[1])->toStartWith('regex:');
});

it('validates end to end through the builder-produced rule', function (): void {
    $rule = FluentRule::string()->required()->matches(fn (Regex $r): Regex => $r->digits(3)->literal('-')->letters(2));

    $passes = fn (mixed $value): bool => Validator::make(
        ['f' => $value],
        ['f' => $rule],
    )->passes();

    expect($passes('123-Ab'))->toBeTrue()
        ->and($passes("123-Ab\n"))->toBeFalse()
        ->and($passes('nope'))->toBeFalse();
});
