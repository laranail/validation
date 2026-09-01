<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Validation\Internal;

use Simtabi\Laranail\Validation\BatchDatabaseChecker;

/**
 * Decides whether a batched presence result can be answered by exact PHP
 * comparison, or whether the group must fall back to per-item queries.
 *
 * @internal
 */
final class PresenceMatchFidelity
{
    /**
     * Would an exact PHP comparison give the same answer the database gave?
     *
     * The batched query matches with `whereIn`, so the DATABASE decides what
     * equals what — its collation, its padding, its numeric coercion. It then
     * returns the STORED values, and PrecomputedPresenceVerifier compares
     * those to the submitted values as exact array keys. Those are two
     * different comparisons, and where they disagree the rule reports the
     * wrong answer: on `unique` it reports "not taken" for a value the
     * database considers taken, and a duplicate is written to a column the
     * database treats as unique.
     *
     * Rather than guess the collation — which is per column, driver-specific,
     * and not portably readable — this checks the evidence already in hand:
     *
     * 1. Every value the query returned must be byte-identical to one that
     *    was submitted. If the database returned `alice@example.com` for a
     *    submitted `ALICE@EXAMPLE.COM`, it matched on something PHP will not.
     * 2. No two submitted values may collide under a loose comparison. If
     *    both `alice@` and `ALICE@` are submitted, the single row the query
     *    returns cannot say which of them the database matched, so an exact
     *    lookup would answer one of them wrongly.
     *
     * Failing either, the group is not registered and the fallback verifier
     * answers it per item — slower, and correct. The check is conservative in
     * one direction only: it can decline to batch a group that would have
     * been fine, never the reverse.
     *
     * @param  list<mixed>  $values
     * @param  array<int, mixed>  $fetched
     */
    public static function isFaithful(array $values, array $fetched): bool
    {
        $submitted = array_flip(BatchDatabaseChecker::uniqueStringValues($values));

        foreach (BatchDatabaseChecker::uniqueStringValues($fetched) as $value) {
            if (! isset($submitted[$value])) {
                return false;
            }
        }

        $loose = [];

        foreach (array_keys($submitted) as $value) {
            $key = mb_strtolower(trim((string) $value));

            if (isset($loose[$key])) {
                return false;
            }

            $loose[$key] = true;
        }

        return true;
    }
}
