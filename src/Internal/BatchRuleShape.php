<?php declare(strict_types=1);

namespace Simtabi\Laranail\Validation\Internal;

/**
 * A short digest of everything that changes the query a batched rule issues.
 *
 * Two `unique('users', 'email')` rules are not interchangeable if one carries
 * a `where('team_id', 4)` or an `ignore()`: their batched queries return
 * different rows, and grouping them together would answer one rule from the
 * other's result set. The digest goes into the group key so they stay apart,
 * and the same-table-and-column conflict check then declines to batch either
 * — PrecomputedPresenceVerifier is keyed on table and column alone and cannot
 * tell them apart at lookup time.
 *
 * @internal
 */
final class BatchRuleShape
{
    /**
     * Takes BatchDatabaseChecker::extractMeta()'s return value verbatim —
     * the whole shape, so the annotation stays honest about its one caller,
     * even though only three of the keys are read.
     *
     * @param  array{connection: string|null, table: string, column: string, wheres: array<int, array{column: string, value: string}>, ignore: mixed, idColumn: string}|null  $meta
     */
    public static function of(?array $meta): string
    {
        if ($meta === null) {
            return 'unknown';
        }

        $encoded = json_encode([
            $meta['wheres'],
            is_scalar($meta['ignore']) ? $meta['ignore'] : null,
            $meta['idColumn'],
        ]);

        // Not a security boundary — a collision only means two rules share a
        // group they would have shared anyway under a weaker key. sha256
        // because the codebase forbids md5 outright rather than case by case.
        return substr(hash('sha256', (string) $encoded), 0, 12);
    }
}
