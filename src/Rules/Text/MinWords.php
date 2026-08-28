<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Validation\Rules\Text;

use Closure;
use LogicException;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * At least `$min` words — the usual guard on a free-text field that must be
 * a sentence, not a keyword ("please describe the problem").
 *
 * Word counting is {@see MaxWords::count()}, so the two rules can never
 * disagree about what a word is.
 *
 * Pure tier — no IO.
 */
final readonly class MinWords implements ValidationRule
{
    public function __construct(private int $min)
    {
        if ($min < 1) {
            throw new LogicException('MinWords needs a positive bound — a minimum of zero words is no rule at all.');
        }
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || MaxWords::count($value) < $this->min) {
            $fail('laranail/validation::validation.min_words')->translate(['min' => $this->min]);
        }
    }
}
