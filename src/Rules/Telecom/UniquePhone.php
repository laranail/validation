<?php declare(strict_types=1);

namespace Simtabi\Laranail\Validation\Rules\Telecom;

use Closure;
use Illuminate\Contracts\Validation\DataAwareRule;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Database\DatabaseManager;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Arr;
use RuntimeException;
use Simtabi\Laranail\Phone\PhoneFormatter;

/**
 * Uniqueness for a phone column, compared in E.164 rather than as typed.
 *
 * Laravel's own `unique` compares the attribute exactly as it arrived, which does not work for phone
 * numbers: with a row holding `+254712123456`, a user typing `0712 123456` passes. The strings
 * differ, so the query finds nothing, and you get a duplicate contact that no amount of squinting at
 * the table explains.
 *
 * This normalises first and queries the canonical form, so both spellings collide the way they
 * should. It is deliberately a separate rule rather than a tweak to the generic one — `unique` is
 * used on every other kind of column, and it should keep meaning exactly what it says there.
 *
 * A value that cannot be parsed is **not** reported as a duplicate. It is not a number, so it cannot
 * collide with one; {@see Phone} is the rule that rejects it, and reporting the same input twice for
 * two different reasons only obscures which one the user has to fix.
 */
final class UniquePhone implements DataAwareRule, ValidationRule
{
    /** @var array<array-key, mixed> */
    private array $data = [];

    private mixed $ignoreId = null;

    private string $ignoreColumn = 'id';

    /**
     * @param string      $table        The table to search
     * @param string      $column       The column holding E.164
     * @param string|null $country      ISO 3166-1 alpha-2 hint for parsing bare national input
     * @param string|null $countryField A sibling field to read that hint from instead
     */
    public function __construct(
        private readonly string $table,
        private readonly string $column = 'phone',
        private readonly ?string $country = null,
        private readonly ?string $countryField = null,
        private readonly ?string $connection = null,
    ) {}

    /**
     * Exclude a row, the way `Rule::unique()->ignore()` does.
     *
     * The case this exists for is an edit form, where the record being edited would otherwise always
     * collide with itself.
     */
    public function ignore(mixed $id, string $column = 'id'): self
    {
        $this->ignoreId = $id;
        $this->ignoreColumn = $column;

        return $this;
    }

    /**
     * @param array<array-key, mixed> $data
     */
    public function setData(array $data): static
    {
        $this->data = $data;

        return $this;
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) && ! is_int($value)) {
            return;
        }

        $e164 = $this->formatter()->toE164((string) $value, $this->resolveCountry());

        // Unparseable input is left alone on purpose — see the class docblock.
        if ($e164 === null) {
            return;
        }

        if ($this->query()->where($this->column, $e164)->exists()) {
            $fail('laranail/validation::validation.phone_unique')->translate();
        }
    }

    private function query(): Builder
    {
        $query = $this->resolver()
            ->connection($this->connection)
            ->table($this->table);

        if ($this->ignoreId !== null) {
            $query->where($this->ignoreColumn, '!=', $this->ignoreId);
        }

        return $query;
    }

    /**
     * The country to parse bare national input against.
     *
     * A sibling field wins over a fixed hint, because a form with a country picker beside the number
     * is describing the user's actual choice rather than the developer's assumption.
     */
    private function resolveCountry(): ?string
    {
        if ($this->countryField !== null) {
            $fromData = Arr::get($this->data, $this->countryField);

            if (is_string($fromData) && $fromData !== '') {
                return strtoupper($fromData);
            }
        }

        return $this->country;
    }

    private function formatter(): PhoneFormatter
    {
        if (! class_exists(PhoneFormatter::class)) {
            throw new RuntimeException(
                'The phone rules require laranail/phone. Install it with `composer require laranail/phone`. '
                . "It is a suggested rather than a required dependency because it carries libphonenumber's "
                . 'numbering-plan metadata, which a project validating only strings and dates should not have to install.',
            );
        }

        return resolve(PhoneFormatter::class);
    }

    private function resolver(): DatabaseManager
    {
        return resolve(DatabaseManager::class);
    }
}
