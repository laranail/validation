<?php declare(strict_types=1);

use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Simtabi\Laranail\Validation\Rules\Text\CaseStyle;
use Simtabi\Laranail\Validation\Rules\Text\HtmlClean;
use Simtabi\Laranail\Validation\Rules\Text\PersonName;
use Simtabi\Laranail\Validation\Rules\Text\Slug;
use Simtabi\Laranail\Validation\Rules\Text\Username;
use Simtabi\Laranail\Validation\Rules\Text\WithoutSpaces;

// =========================================================================
// Slug
// =========================================================================

it('accepts valid slugs', function (string $value): void {
    expect(ruleAccepts(new Slug(), $value))->toBeTrue();
})->with(['hello', 'hello-world', 'a', '2026-in-review', 'a1-b2-c3']);

it('rejects invalid slugs', function (string $value): void {
    expect(ruleAccepts(new Slug(), $value))->toBeFalse();
})->with([
    'Hello-World',     // uppercase
    '-hello',          // leading hyphen
    'hello-',          // trailing hyphen
    'hello--world',    // doubled: two slugs that differ only in punctuation
    'hello world',
    'hello_world',
    'héllo',
]);

it('accepts exactly what Str::slug produces', function (string $input): void {
    // A slug that passes must survive a round trip, otherwise the rule and the
    // generator disagree about what a slug is.
    expect(ruleAccepts(new Slug(), Str::slug($input)))->toBeTrue();
})->with(['Hello World', 'Ünïcødé Títlè', 'lots   of   spaces', '2026 in review']);

// =========================================================================
// WithoutSpaces
// =========================================================================

it('accepts values with no whitespace', function (string $value): void {
    expect(ruleAccepts(new WithoutSpaces(), $value))->toBeTrue();
})->with(['abc', 'a-b_c', '12345', 'héllo']);

it('rejects every kind of whitespace, not just ASCII', function (string $value): void {
    // \s alone misses these, and they are exactly what gets pasted in from a
    // word processor or used to slip past a naive check.
    expect(ruleAccepts(new WithoutSpaces(), $value))->toBeFalse();
})->with([
    'a b',
    "a\tb",
    "a\nb",
    "a\u{00A0}b",   // non-breaking space
    "a\u{200B}b",   // zero-width space
    "a\u{FEFF}b",   // zero-width no-break space
    "a\u{2003}b",   // em space
]);

// =========================================================================
// HtmlClean
// =========================================================================

it('accepts plain text', function (string $value): void {
    expect(ruleAccepts(new HtmlClean(), $value))->toBeTrue();
})->with([
    'Just a name',
    'Ünïcødé',
    'Acme & Sons',
]);

it('rejects values containing markup', function (string $value): void {
    expect(ruleAccepts(new HtmlClean(), $value))->toBeFalse();
})->with([
    '<b>bold</b>',
    'hello <script>alert(1)</script>',
    'a <br> b',
    '<div class="x">',
]);

it('accepts encoded markup and bare comparison operators', function (string $value): void {
    // Both are ordinary text. `&lt;script&gt;` renders as the literal
    // characters and is not a tag; `5 < 10` is prose. Rejecting either would
    // be wrong far more often than useful.
    expect(ruleAccepts(new HtmlClean(), $value))->toBeTrue();
})->with(['&lt;script&gt;', '5 < 10', 'a -> b', 'x > y']);

it('is a data-shape rule and not an XSS defence', function (): void {
    // Documenting the boundary in an executable place. A value that passes is
    // NOT safe to render unescaped — output escaping is the defence.
    $passes = '&lt;img src=x onerror=alert(1)&gt;';

    expect(ruleAccepts(new HtmlClean(), $passes))->toBeTrue()
        ->and(e($passes))->not->toBe($passes);
});

// =========================================================================
// Username
// =========================================================================

it('accepts valid usernames', function (string $value): void {
    expect(ruleAccepts(new Username(), $value))->toBeTrue();
})->with(['alice', 'alice_b', 'alice-b', 'alice.b', 'a1b2', 'Alice', str_repeat('a', 32)]);

it('rejects usernames with edge or doubled separators', function (string $value): void {
    // `admin.`, `_admin` and `admin..b` all read as `admin` at a glance, and a
    // doubled separator is invisible in most fonts — impersonation shapes.
    expect(ruleAccepts(new Username(), $value))->toBeFalse();
})->with(['_alice', 'alice_', '.alice', 'alice.', '-alice', 'alice-', 'alice..b', 'alice__b']);

it('rejects usernames outside the length bounds', function (): void {
    expect(ruleAccepts(new Username(), 'ab'))->toBeFalse()
        ->and(ruleAccepts(new Username(), str_repeat('a', 33)))->toBeFalse()
        ->and(ruleAccepts(new Username(min: 2), 'ab'))->toBeTrue()
        ->and(ruleAccepts(new Username(max: 33), str_repeat('a', 33)))->toBeTrue();
});

it('rejects non-ASCII usernames to prevent homograph impersonation', function (): void {
    // `аdmin` with a Cyrillic а is visually identical to `admin`.
    expect(ruleAccepts(new Username(), "\u{0430}dmin"))->toBeFalse()
        ->and(ruleAccepts(new Username(), 'admin'))->toBeTrue();
});

// =========================================================================
// PersonName
// =========================================================================

it('accepts names from many scripts', function (string $value): void {
    // A validator that assumes ASCII, or two parts, or no punctuation, is
    // wrong about a large share of the world's population.
    expect(ruleAccepts(new PersonName(), $value))->toBeTrue();
})->with([
    'Alice',
    "O'Neill",
    'Jean-Luc Picard',
    'Müller',
    'Ait Ben Haddou',
    '李',
    'Ólafur Ragnar Grímsson',
    'J. R. R. Tolkien',
    'Иванов',
]);

it('rejects values that are not names', function (string $value): void {
    expect(ruleAccepts(new PersonName(), $value))->toBeFalse();
})->with([
    'Alice2',          // digits, by default
    'Alice 😀',        // emoji: \p{S}
    '<b>Alice</b>',
    'alice@example.com',
    "'-.",             // punctuation with no letter
]);

it('leaves blank input to required, including whitespace-only', function (): void {
    // Validator::presentOrRuleIsImplicit() treats a string that trims to ''
    // as absent, so a non-implicit rule never sees it. That is `required`'s
    // job, and it applies to every core format rule too.
    expect(ruleAccepts(new PersonName(), '   '))->toBeTrue()
        ->and(Validator::make(['f' => '   '], ['f' => ['required', new PersonName()]])->passes())->toBeFalse();
});

it('can allow digits when the domain genuinely needs them', function (): void {
    expect(ruleAccepts(new PersonName(), 'Henry 8'))->toBeFalse()
        ->and(ruleAccepts(new PersonName(allowDigits: true), 'Henry 8'))->toBeTrue();
});

// =========================================================================
// CaseStyle
// =========================================================================

it('validates each casing convention', function (string $style, string $valid, string $invalid): void {
    expect(ruleAccepts(new CaseStyle($style), $valid))->toBeTrue()
        ->and(ruleAccepts(new CaseStyle($style), $invalid))->toBeFalse();
})->with([
    'camel' => [CaseStyle::CAMEL, 'helloWorld', 'HelloWorld'],
    'pascal' => [CaseStyle::PASCAL, 'HelloWorld', 'helloWorld'],
    'snake' => [CaseStyle::SNAKE, 'hello_world', 'hello__world'],
    'kebab' => [CaseStyle::KEBAB, 'hello-world', '-hello'],
    'title' => [CaseStyle::TITLE, 'Hello World', 'hello world'],
]);

it('rejects leading, trailing and doubled separators', function (string $style, string $value): void {
    expect(ruleAccepts(new CaseStyle($style), $value))->toBeFalse();
})->with([
    ['snake', '_hello'],
    ['snake', 'hello_'],
    ['snake', 'hello__world'],
    ['kebab', '-hello'],
    ['kebab', 'hello-'],
    ['kebab', 'hello--world'],
]);

it('rejects an unknown style rather than passing everything', function (): void {
    // A typo in the style name must fail loudly, not silently accept anything.
    expect(ruleAccepts(new CaseStyle('screaming'), 'anything'))->toBeFalse();
});
