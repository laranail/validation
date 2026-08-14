<?php declare(strict_types=1);

namespace Simtabi\Laranail\Validation\Rules\Structure;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Facades\Validator;
use InvalidArgumentException;

/**
 * Validate every item of a delimited string against the same rule.
 *
 * For the field that holds `alice@example.com, bob@example.com` — one input,
 * many values. Laravel's `array` rules cannot help: the value is a string, and
 * by the time you have exploded it into an array you have left the validator.
 *
 *     'recipients' => new Delimited(['email']),
 *     'tags'       => new Delimited(['string', 'max:20'], max: 5),
 *     'codes'      => new Delimited([new Isbn()], separator: ';'),
 *
 * The message names the offending position rather than just failing, because
 * "entry 3 is not a valid email" is actionable and "the recipients field is
 * invalid" is not — and with a dozen addresses in one box, the difference is
 * whether the user can fix it without bisecting by hand.
 *
 * Duplicates are allowed by default. A repeated recipient is something to
 * deduplicate, not something to refuse; pass `distinct: true` where a repeat
 * genuinely is an error.
 *
 * Tier follows the sub-rules. `new Delimited(['email'])` is pure; wrapping a
 * database or network rule makes the whole thing that tier, since it runs
 * once per item — worth remembering before wrapping `exists`.
 */
final readonly class Delimited implements ValidationRule
{
    /**
     * Not promoted, so the emptiness check can narrow the type on the way in.
     * Declaring the parameter `non-empty-string` instead would push the
     * obligation onto every caller — including ones built from config at
     * runtime, which static analysis never sees, and which are exactly the
     * callers the guard exists for.
     *
     * @var non-empty-string
     */
    private string $separator;

    /**
     * @param  list<mixed>  $rules      Applied to each item.
     * @param  string       $separator  Splits the string; not trimmed itself.
     * @param  int|null     $min        Fewest items required.
     * @param  int|null     $max        Most items allowed.
     * @param  bool         $distinct   Reject repeated items.
     * @param  bool         $trim       Trim whitespace around each item.
     *
     * @throws InvalidArgumentException If the separator is empty.
     */
    public function __construct(
        private array $rules,
        string $separator = ',',
        private ?int $min = null,
        private ?int $max = null,
        private bool $distinct = false,
        private bool $trim = true,
    ) {
        // explode() throws on an empty separator, so the failure would
        // otherwise surface deep inside validation of whichever request
        // happened to reach this rule first. Fail where the mistake is.
        if ($separator === '') {
            throw new InvalidArgumentException('The delimiter cannot be an empty string.');
        }

        $this->separator = $separator;
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value)) {
            $fail('laranail-validation::validation.delimited.invalid')->translate();

            return;
        }

        $items = explode($this->separator, $value);

        if ($this->trim) {
            $items = array_map(trim(...), $items);
        }

        if ($this->min !== null && count($items) < $this->min) {
            $fail('laranail-validation::validation.delimited.min')->translate(['min' => $this->min]);

            return;
        }

        if ($this->max !== null && count($items) > $this->max) {
            $fail('laranail-validation::validation.delimited.max')->translate(['max' => $this->max]);

            return;
        }

        if ($this->distinct && count(array_unique($items)) !== count($items)) {
            $fail('laranail-validation::validation.delimited.distinct')->translate();

            return;
        }

        foreach ($items as $index => $item) {
            // An empty item means a stray or doubled separator — `a,,b`, or a
            // trailing comma. Reported separately because "entry 2 is empty"
            // points at the punctuation, while running it through the
            // sub-rules would report whatever `email` says about ''.
            if ($item === '') {
                $fail('laranail-validation::validation.delimited.empty')
                    ->translate(['position' => $index + 1]);

                return;
            }

            if (Validator::make(['item' => $item], ['item' => $this->rules])->fails()) {
                $fail('laranail-validation::validation.delimited.item')
                    ->translate(['position' => $index + 1]);

                return;
            }
        }
    }
}
