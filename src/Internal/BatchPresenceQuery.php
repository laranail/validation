<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Validation\Internal;

use Illuminate\Support\Facades\DB;
use Illuminate\Database\Query\Builder;

/**
 * Runs the batched whereIn query for exists/unique presence verification,
 * replaying the rule's where conditions (null, not null, not equal, equal)
 * and the unique-rule ignore clause.
 *
 * @internal
 */
final class BatchPresenceQuery
{
    private const int CHUNK_SIZE = 1000;

    /**
     * @param array<int, mixed> $values
     * @param array<int, array{column: string, operator: '='|'!='|'NULL'|'NOT_NULL', value: string|null}> $wheres
     *
     * @return array<int, mixed>
     */
    public static function run(
        ?string $connection,
        string $table,
        string $column,
        array $values,
        array $wheres,
        mixed $ignore = null,
        string $idColumn = 'id',
    ): array {
        /** @var list<array<int, mixed>> $chunks */
        $chunks = [];

        foreach (array_chunk($values, self::CHUNK_SIZE) as $chunk) {
            $query = (
                $connection !== null
                ? DB::connection($connection)->table($table)
                : DB::table($table)
            )->useWritePdo();

            $query->whereIn($column, $chunk);

            // Replay the rule's where conditions, as DatabasePresenceVerifier would.
            foreach ($wheres as $where) {
                self::applyWhere($query, $where);
            }

            if ($ignore !== null && $ignore !== 'NULL') {
                $query->where($idColumn, '<>', $ignore);
            }

            $chunks[] = array_values($query->pluck($column)->all());
        }

        return array_merge(...$chunks);
    }

    /**
     * Apply one normalised where condition to the query, with the meaning
     * DatabasePresenceVerifier gives it.
     *
     * @param array{column: string, operator: '='|'!='|'NULL'|'NOT_NULL', value: string|null} $where
     */
    private static function applyWhere(Builder $query, array $where): void
    {
        match ($where['operator']) {
            'NULL'     => $query->whereNull($where['column']),
            'NOT_NULL' => $query->whereNotNull($where['column']),
            '!='       => $query->where($where['column'], '!=', $where['value']),
            '='        => $query->where($where['column'], '=', $where['value']),
        };
    }
}
