<?php declare(strict_types=1);

namespace Simtabi\Laranail\Validation\Rules\Text;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Simtabi\Laranail\Validation\Support\I18n\CodeFile;

/**
 * A salutation / honorific — `Mr`, `Prof.`, `Madame`.
 *
 * Comparison is deliberately forgiving about surface form: case is folded
 * and a trailing dot is dropped, because `mr`, `Mr` and `Mr.` are the same
 * choice from the same dropdown. What it will not do is guess — `Mister
 * John` is a name with a title in it, not a salutation.
 *
 * The bundled set is a curated everyday list (see
 * `resources/data/salutations.txt`); honorifics are unbounded and culture-
 * specific, so pass your own `$accepted` list — lowercase, no dots — to
 * replace it, which is also the localisation hook.
 *
 * Pure tier — no IO.
 */
final class Salutation implements ValidationRule
{
    /** @var array<string, true>|null */
    private readonly ?array $accepted;

    /** @var array<string, true>|null */
    private static ?array $bundled = null;

    /**
     * @param  list<string>|null  $accepted  Replacement list, lowercase without dots.
     */
    public function __construct(?array $accepted = null)
    {
        $this->accepted = $accepted === null ? null : array_fill_keys($accepted, true);
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value)) {
            $fail('laranail/validation::validation.salutation')->translate();

            return;
        }

        $normalised = rtrim(mb_strtolower(trim($value)), '.');

        if ($this->accepted === null) {
            self::$bundled ??= CodeFile::load(dirname(__DIR__, 3) . '/resources/data/salutations.txt');
        }

        $list = $this->accepted ?? self::$bundled ?? [];

        if (! isset($list[$normalised])) {
            $fail('laranail/validation::validation.salutation')->translate();
        }
    }
}
