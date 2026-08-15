<?php declare(strict_types=1);

use Simtabi\Laranail\Validation\Rules\Colour\CssColor;
use Simtabi\Laranail\Validation\Rules\Colour\Support\Names;

it('accepts every notation by default', function (string $value): void {
    expect(CssColor::passes($value))->toBeTrue();
})->with([
    '#fff', '#ffff', '#ffffff', '#ffffffff', '#FFF',
    'rgb(255, 0, 0)', 'rgba(255, 0, 0, 0.5)', 'rgb(255 0 0)', 'rgb(255 0 0 / 40%)',
    'hsl(120, 50%, 50%)', 'hsl(120deg 50% 50%)', 'hsla(120, 50%, 50%, .5)',
    'red', 'rebeccapurple', 'transparent', 'currentColor', '  RED  ',
]);

it('rejects hex lengths that are not colours', function (string $value): void {
    // A {3,8} pattern would accept all of these. Only 3, 4, 6 and 8 are real.
    expect(CssColor::passes($value))->toBeFalse();
})->with(['#ff', '#fffff', '#fffffff', '#fffffffff', '#gggggg', 'ffffff', '#']);

it('rejects malformed function syntax', function (string $value): void {
    expect(CssColor::passes($value))->toBeFalse();
})->with([
    'rgb(255, 0)',
    'rgb()',
    'rgb 255 0 0',
    'rgb(255, 0, 0',
    'rgb(255, 0, 0))',
    'notafunction(1, 2, 3)',
]);

it('does not enforce component ranges, because CSS clamps rather than rejects', function (): void {
    // rgb(300, 0, 0) renders as red in every browser. A rule that rejected it
    // would be stricter than the thing it validates for.
    expect(CssColor::passes('rgb(300, 0, 0)'))->toBeTrue();
});

it('narrows to the notations asked for', function (): void {
    expect(CssColor::passes('#fff', [CssColor::HEX]))->toBeTrue()
        ->and(CssColor::passes('red', [CssColor::HEX]))->toBeFalse()
        ->and(CssColor::passes('rgb(1,2,3)', [CssColor::HEX]))->toBeFalse()
        ->and(CssColor::passes('red', [CssColor::NAME]))->toBeTrue();
});

it('leaves hsv off unless named, since no browser parses it', function (): void {
    expect(CssColor::passes('hsv(120, 50%, 50%)'))->toBeFalse()
        ->and(CssColor::passes('hsv(120, 50%, 50%)', [CssColor::HSV]))->toBeTrue();
});

it('carries the CSS Color 4 named list', function (): void {
    expect(Names::all())->toHaveCount(150)   // 148 named colours + transparent + currentcolor
        ->and(Names::has('rebeccapurple'))->toBeTrue()   // added in Color 4
        ->and(Names::has('darkgrey'))->toBeTrue()        // both spellings are real
        ->and(Names::has('darkgray'))->toBeTrue()
        ->and(Names::has('burntsienna'))->toBeFalse();   // plausible, not real
});

it('rejects a non-string', function (mixed $value): void {
    expect(CssColor::passes($value))->toBeFalse();
})->with([null, 123, [['#fff']], true]);
