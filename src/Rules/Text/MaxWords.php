<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Validation\Rules\Text;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use LogicException;

/**
 * At most `$max` words.
 *
 * A word is a run of letters, numbers or apostrophes in any script —
 * `háblame` is one word, `don't` is one word, and a hyphenated compound
 * splits (`state-of-the-art` is four), which matches how editors count.
 * Splitting uses `PREG_SPLIT_NO_EMPTY`: the legacy rule counted the empty
 * fragments around surrounding whitespace, so `"  two words  "` was four.
 *
 * Pure tier — no IO.
 */
final readonly class MaxWords implements ValidationRule
{
    public function __construct(private int $max)
    {
        if ($max < 1) {
            throw new LogicException('MaxWords needs a positive bound — a maximum of zero words is `prohibited`.');
        }
    }

    /** How many words the value contains, shared with {@see MinWords}. */
    public static function count(string $value): int
    {
        $words = preg_split("~[^\p{L}\p{N}']+~u", $value, -1, PREG_SPLIT_NO_EMPTY);

        return $words === false ? 0 : count($words);
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || self::count($value) > $this->max) {
            $fail('laranail/validation::validation.max_words')->translate(['max' => $this->max]);
        }
    }
}
