<?php declare(strict_types=1);

namespace Simtabi\Laranail\Validation\Rules\Chrono;

use Closure;
use DateTimeImmutable;
use DateTimeInterface;
use Exception;
use Illuminate\Contracts\Validation\DataAwareRule;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Arr;

/**
 * The value lies within `$hours` of a reference instant — either way: a
 * booking end within 48 hours of its start, a correction dated near the
 * event it corrects. The difference is absolute; "no more than N hours
 * apart" has no direction.
 *
 * The reference is a fixed date, a `DateTimeInterface`, or `@field` to
 * read a sibling from the data under validation (the same convention as
 * `PostalCode`'s `@country`). A missing or unparseable reference FAILS the
 * value rather than passing it: a bound that silently stopped binding is
 * the worse outcome.
 *
 * Pure tier — no IO.
 */
final class MaxDateDifference implements DataAwareRule, ValidationRule
{
    /** @var array<array-key, mixed> */
    private array $data = [];

    public function __construct(
        private readonly int $hours,
        private readonly DateTimeInterface|string $from,
    ) {}

    /** @param  array<array-key, mixed>  $data */
    public function setData(array $data): static
    {
        $this->data = $data;

        return $this;
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $reference = $this->reference();
        $subject = is_string($value) ? $this->parse($value) : null;

        if (! $reference instanceof DateTimeImmutable || ! $subject instanceof DateTimeImmutable
            || abs($subject->getTimestamp() - $reference->getTimestamp()) > $this->hours * 3600) {
            $fail('laranail/validation::validation.max_date_difference')
                ->translate(['hours' => $this->hours]);
        }
    }

    private function reference(): ?DateTimeImmutable
    {
        if ($this->from instanceof DateTimeInterface) {
            return DateTimeImmutable::createFromInterface($this->from);
        }

        if (str_starts_with($this->from, '@')) {
            $sibling = Arr::get($this->data, substr($this->from, 1));

            return is_string($sibling) ? $this->parse($sibling) : null;
        }

        return $this->parse($this->from);
    }

    private function parse(string $value): ?DateTimeImmutable
    {
        try {
            return new DateTimeImmutable($value);
        } catch (Exception) {
            return null;
        }
    }
}
