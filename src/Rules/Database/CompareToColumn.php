<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Validation\Rules\Database;

use Closure;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Contracts\Validation\DataAwareRule;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * The value compared against a column looked up in another row — "quantity
 * may not exceed THIS product's max_quantity":
 *
 *     new CompareToColumn('products', 'max_quantity',
 *         Comparison::LessThanOrEqual, 'id', '@product_id')
 *
 * The key is a literal, or `@field` to read a sibling from the data under
 * validation. One rule parameterised by {@see Comparison} replaces the
 * legacy five-class family.
 *
 * A missing row FAILS the value: the bound could not be checked, and the
 * moment the reference is wrong is exactly the moment enforcement matters.
 * (The legacy rule agreed; this is stated so nobody "fixes" it into a
 * pass.)
 *
 * **Database tier.** One indexed read per validated value. Not
 * PrecognitionSkippable, deliberately: live cross-row feedback is what
 * precognition exists for. `$table`/`$column`/`$keyColumn` are code, not
 * input — never pass user data as identifiers.
 */
final class CompareToColumn implements DataAwareRule, ValidationRule
{
    /** @var array<array-key, mixed> */
    private array $data = [];

    public function __construct(
        private readonly string $table,
        private readonly string $column,
        private readonly Comparison $operator,
        private readonly string $keyColumn,
        private readonly int|float|string $key,
    ) {}

    /** @param  array<array-key, mixed>  $data */
    public function setData(array $data): static
    {
        $this->data = $data;

        return $this;
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_scalar($value) || ! $this->passes($value)) {
            $fail('laranail/validation::validation.compare_to_column')->translate();
        }
    }

    private function passes(int|float|string|bool $value): bool
    {
        $key = $this->resolveKey();

        if ($key === null) {
            return false;
        }

        $found = DB::table($this->table)->where($this->keyColumn, $key)->value($this->column);

        if (! is_scalar($found)) {
            return false;
        }

        return $this->operator->compare((string) $value, (string) $found);
    }

    private function resolveKey(): int|float|string|null
    {
        if (! is_string($this->key) || ! str_starts_with($this->key, '@')) {
            return $this->key;
        }

        $sibling = Arr::get($this->data, substr($this->key, 1));

        return is_int($sibling) || is_float($sibling) || is_string($sibling) ? $sibling : null;
    }
}
