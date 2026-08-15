<?php declare(strict_types=1);

namespace Simtabi\Laranail\Validation\Rules\Colour\Support;

/**
 * The CSS named colours.
 *
 * The 148 names from CSS Color Module Level 4, plus the two keywords that
 * are valid wherever a colour is: `transparent`, and `currentcolor` for the
 * inherited value. Factual reference data reproduced from the specification.
 *
 * Flipped to a hash on first use rather than stored as one, so the source
 * stays a readable list while lookup stays O(1) — the same trade the bundled
 * email lists make.
 *
 * @internal
 */
final class Names
{
    /** @var array<string, true>|null */
    private static ?array $lookup = null;

    /** @var list<string> */
    private const array NAMES = [
        'aliceblue', 'antiquewhite', 'aqua', 'aquamarine', 'azure', 'beige', 'bisque', 'black',
        'blanchedalmond', 'blue', 'blueviolet', 'brown', 'burlywood', 'cadetblue',
        'chartreuse', 'chocolate', 'coral', 'cornflowerblue', 'cornsilk', 'crimson', 'cyan',
        'darkblue', 'darkcyan', 'darkgoldenrod', 'darkgray', 'darkgreen', 'darkgrey',
        'darkkhaki', 'darkmagenta', 'darkolivegreen', 'darkorange', 'darkorchid', 'darkred',
        'darksalmon', 'darkseagreen', 'darkslateblue', 'darkslategray', 'darkslategrey',
        'darkturquoise', 'darkviolet', 'deeppink', 'deepskyblue', 'dimgray', 'dimgrey',
        'dodgerblue', 'firebrick', 'floralwhite', 'forestgreen', 'fuchsia', 'gainsboro',
        'ghostwhite', 'gold', 'goldenrod', 'gray', 'green', 'greenyellow', 'grey', 'honeydew',
        'hotpink', 'indianred', 'indigo', 'ivory', 'khaki', 'lavender', 'lavenderblush',
        'lawngreen', 'lemonchiffon', 'lightblue', 'lightcoral', 'lightcyan',
        'lightgoldenrodyellow', 'lightgray', 'lightgreen', 'lightgrey', 'lightpink',
        'lightsalmon', 'lightseagreen', 'lightskyblue', 'lightslategray', 'lightslategrey',
        'lightsteelblue', 'lightyellow', 'lime', 'limegreen', 'linen', 'magenta', 'maroon',
        'mediumaquamarine', 'mediumblue', 'mediumorchid', 'mediumpurple', 'mediumseagreen',
        'mediumslateblue', 'mediumspringgreen', 'mediumturquoise', 'mediumvioletred',
        'midnightblue', 'mintcream', 'mistyrose', 'moccasin', 'navajowhite', 'navy', 'oldlace',
        'olive', 'olivedrab', 'orange', 'orangered', 'orchid', 'palegoldenrod', 'palegreen',
        'paleturquoise', 'palevioletred', 'papayawhip', 'peachpuff', 'peru', 'pink', 'plum',
        'powderblue', 'purple', 'rebeccapurple', 'red', 'rosybrown', 'royalblue',
        'saddlebrown', 'salmon', 'sandybrown', 'seagreen', 'seashell', 'sienna', 'silver',
        'skyblue', 'slateblue', 'slategray', 'slategrey', 'snow', 'springgreen', 'steelblue',
        'tan', 'teal', 'thistle', 'tomato', 'turquoise', 'violet', 'wheat', 'white',
        'whitesmoke', 'yellow', 'yellowgreen',
    ];

    public static function has(string $value): bool
    {
        self::$lookup ??= array_fill_keys([...self::NAMES, 'transparent', 'currentcolor'], true);

        return isset(self::$lookup[mb_strtolower(trim($value))]);
    }

    /** @return list<string> */
    public static function all(): array
    {
        return [...self::NAMES, 'transparent', 'currentcolor'];
    }
}
