<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Validation\Rules\Chrono;

use Closure;
use Exception;
use LogicException;
use DateTimeImmutable;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * A time whose minute component is one of the allowed values —
 * `new MinuteIn([0, 15, 30, 45])` for a scheduler that books on the
 * quarter hour.
 *
 * The value may be a bare time (`10:15`) or a full datetime; anything
 * PHP's date parser reads works, because the rule asks one question of it
 * and only one: which minute. Slot policy belongs in the constructor,
 * where an impossible minute (60) or an empty set is a configuration
 * error and throws.
 *
 * Pure tier — no IO.
 */
final readonly class MinuteIn implements ValidationRule
{
    /**
     * @param list<int> $minutes Allowed minute values, each 0–59.
     */
    public function __construct(private array $minutes)
    {
        if ($minutes === []) {
            throw new LogicException('MinuteIn needs at least one allowed minute.');
        }

        foreach ($minutes as $minute) {
            if ($minute < 0 || $minute > 59) {
                throw new LogicException("A minute is 0-59; [{$minute}] is not one.");
            }
        }
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || ! $this->passes($value)) {
            $fail('laranail/validation::validation.minute_in')
                ->translate(['minutes' => implode(', ', $this->minutes)]);
        }
    }

    private function passes(string $value): bool
    {
        try {
            $time = new DateTimeImmutable($value);
        } catch (Exception) {
            return false;
        }

        return in_array((int) $time->format('i'), $this->minutes, true);
    }
}
