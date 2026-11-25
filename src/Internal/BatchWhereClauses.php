<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Validation\Internal;

/**
 * The where clauses of an exists/unique rule, in the one shape
 * BatchPresenceQuery replays.
 *
 * @internal
 */
final class BatchWhereClauses
{
    /**
     * Read a database rule's where clauses into one shape, whichever Laravel
     * stored them.
     *
     * Up to Laravel 13.42 each clause was `['column', 'value']`, with the
     * operator encoded in the value as DatabasePresenceVerifier::addWhere()
     * reads it: 'NULL', 'NOT_NULL', a leading '!' for not-equal, otherwise
     * equality. From 13.43 each clause carries an explicit `operator` ('=',
     * '!=', 'NULL', 'NOT_NULL') and the value is literal, so `where('x',
     * 'NULL')` compares against the string and `whereNull()` has a null value.
     *
     * Returns null when the property is not a list of clauses or any clause
     * is in neither shape, so the caller can decline to batch rather than
     * silently drop a constraint.
     *
     * @return array<int, array{column: string, operator: '='|'!='|'NULL'|'NOT_NULL', value: string|null}>|null
     */
    public static function of(mixed $wheres): ?array
    {
        if (! is_array($wheres)) {
            return null;
        }

        $normalized = [];

        foreach ($wheres as $where) {
            if (! is_array($where) || ! isset($where['column']) || ! is_string($where['column'])) {
                return null;
            }

            $column = $where['column'];
            $value = $where['value'] ?? null;

            if (array_key_exists('operator', $where)) {
                $operator = $where['operator'];

                if ($operator === 'NULL' || $operator === 'NOT_NULL') {
                    $normalized[] = ['column' => $column, 'operator' => $operator, 'value' => null];

                    continue;
                }

                if (($operator === '=' || $operator === '!=') && is_scalar($value)) {
                    $normalized[] = ['column' => $column, 'operator' => $operator, 'value' => (string) $value];

                    continue;
                }

                return null;
            }

            if (! is_scalar($value)) {
                return null;
            }

            $value = (string) $value;

            $normalized[] = match (true) {
                $value === 'NULL'            => ['column' => $column, 'operator' => 'NULL', 'value' => null],
                $value === 'NOT_NULL'        => ['column' => $column, 'operator' => 'NOT_NULL', 'value' => null],
                str_starts_with($value, '!') => ['column' => $column, 'operator' => '!=', 'value' => mb_substr($value, 1)],
                default                      => ['column' => $column, 'operator' => '=', 'value' => $value],
            };
        }

        return $normalized;
    }
}
