<?php declare(strict_types=1);

namespace Simtabi\Laranail\Validation\Rules\Colour;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Simtabi\Laranail\Validation\Contracts\ClientCheckable;
use Simtabi\Laranail\Validation\Rules\Colour\Support\Names;

/**
 * A CSS colour, in any of the notations CSS actually accepts.
 *
 *     new CssColor()                                    // any notation
 *     new CssColor([CssColor::HEX, CssColor::RGB])      // only these
 *     'laranail_css_color:hex,rgb'
 *
 * One parameterised rule rather than the five near-identical classes the
 * catalogue listed (`HexColor`, `RgbColor`, `RgbaColor`, `HslColor`,
 * `HsvColor`). A field almost always means "a colour", and a caller who needs
 * to narrow it says so. It also keeps the notations in one place: `rgb()` and
 * `rgba()` are the same function in CSS Color 4, and splitting them into two
 * rules encodes a distinction the spec removed.
 *
 * `hex` overlaps Laravel's native `hex_color`, deliberately: this rule exists
 * to accept SEVERAL notations, and a caller restricting to hex alone should
 * use the native rule.
 *
 * `hsv` is NOT a CSS notation — no browser parses `hsv()`. It is included
 * because colour pickers emit it and applications store it, but it is off
 * unless asked for by name.
 *
 * Pure tier — no IO.
 */
final readonly class CssColor implements ClientCheckable, ValidationRule
{
    public const string HEX = 'hex';

    public const string RGB = 'rgb';

    public const string HSL = 'hsl';

    public const string HSV = 'hsv';

    public const string NAME = 'name';

    /** Everything a browser will parse. `hsv` is excluded — see the class docblock. */
    private const array CSS_NOTATIONS = [self::HEX, self::RGB, self::HSL, self::NAME];

    /** @var list<string> */
    private array $notations;

    /** @param  list<string>|string  $notations */
    public function __construct(array|string $notations = [])
    {
        $notations = is_string($notations) ? [$notations] : $notations;

        $normalised = array_values(array_filter(
            array_map(static fn (string $n): string => mb_strtolower(trim($n)), $notations),
            static fn (string $n): bool => $n !== '',
        ));

        $this->notations = $normalised === [] ? self::CSS_NOTATIONS : $normalised;
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! self::passes($value, $this->notations)) {
            $fail('laranail-validation::validation.css_color')
                ->translate(['notations' => implode(', ', $this->notations)]);
        }
    }

    /** @param  list<string>  $notations */
    public static function passes(mixed $value, array $notations = self::CSS_NOTATIONS): bool
    {
        if (! is_string($value)) {
            return false;
        }

        $value = trim($value);

        return array_any($notations, fn (string $notation) => self::matches($value, $notation));
    }

    private static function matches(string $value, string $notation): bool
    {
        return match ($notation) {
            // 3, 4, 6 and 8 digits: the 4- and 8-digit forms carry alpha.
            // Anything else — 5 digits, 7 digits — is not a colour, and a
            // pattern of {3,8} would accept them.
            self::HEX => preg_match('/^#(?:[0-9a-f]{3,4}|[0-9a-f]{6}|[0-9a-f]{8})$/i', $value) === 1,
            self::RGB => self::functional($value, 'rgba?', 3),
            self::HSL => self::functional($value, 'hsla?', 3),
            self::HSV => self::functional($value, 'hsva?', 3),
            self::NAME => Names::has($value),
            default => false,
        };
    }

    /**
     * A CSS colour function: `rgb(1, 2, 3)`, `rgb(1 2 3 / 40%)`, `hsl(120deg 50% 50%)`.
     *
     * Deliberately shape-only. Component ranges are not enforced because CSS
     * clamps rather than rejects — `rgb(300, 0, 0)` renders as red — so a rule
     * that rejected it would be stricter than every browser.
     */
    private static function functional(string $value, string $function, int $components): bool
    {
        // A component: number, percentage, or angle. The separator is a comma
        // (legacy syntax) or whitespace (CSS Color 4), and alpha follows a
        // slash in the modern form or a fourth comma in the legacy one.
        $component = '[+-]?(?:\d+\.?\d*|\.\d+)(?:%|deg|grad|rad|turn)?';
        $separator = '(?:\s*,\s*|\s+)';

        $body = $component . str_repeat($separator . $component, $components - 1);
        $alpha = '(?:\s*(?:,|\/)\s*' . $component . ')?';

        return preg_match('/^' . $function . '\(\s*' . $body . $alpha . '\s*\)$/i', $value) === 1;
    }

    /**
     * One pattern covering the configured notations.
     *
     * The named colours are inlined as an alternation. That was previously
     * called out as "large" and left undone, which was the wrong reason not
     * to: 150 literal names is about 1.5 KB of pattern, against a package
     * that already ships an 8,201-entry domain list. It is a plain
     * alternation of literals inside an anchored group, so there is no
     * backtracking hazard either.
     *
     * The alternative — omitting names — would mean a browser rejecting
     * `red`, which is worse than a slightly longer pattern.
     */
    public function clientRules(): array
    {
        $branches = [];

        foreach ($this->notations as $notation) {
            $branch = self::branch($notation);

            // An unrecognised notation makes the rule reject everything for
            // it, which a pattern cannot express — so advertise nothing and
            // let the server answer.
            if ($branch === null) {
                return [];
            }

            $branches[] = $branch;
        }

        if ($branches === []) {
            return [];
        }

        return [['rule' => 'regex', 'params' => ['pattern' => '/^(?:' . implode('|', $branches) . ')$/i']]];
    }

    /** The unanchored body for one notation, or null if there is no such notation. */
    private static function branch(string $notation): ?string
    {
        $component = '[+-]?(?:\d+\.?\d*|\.\d+)(?:%|deg|grad|rad|turn)?';
        $separator = '(?:\s*,\s*|\s+)';
        $body = static fn (string $function): string => $function . '\(\s*' . $component
            . str_repeat($separator . $component, 2)
            . '(?:\s*(?:,|\/)\s*' . $component . ')?\s*\)';

        return match ($notation) {
            self::HEX => '#(?:[0-9a-f]{3,4}|[0-9a-f]{6}|[0-9a-f]{8})',
            self::RGB => $body('rgba?'),
            self::HSL => $body('hsla?'),
            self::HSV => $body('hsva?'),
            self::NAME => implode('|', array_map(
                static fn (string $name): string => preg_quote($name, '/'),
                Names::all(),
            )),
            default => null,
        };
    }
}
