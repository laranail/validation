<?php declare(strict_types=1);

namespace Simtabi\Laranail\Validation\Internal;

use Illuminate\Validation\Rules\Exists;
use Illuminate\Validation\Rules\Unique;
use Simtabi\Laranail\Validation\BatchDatabaseChecker;

/**
 * Scans a rule set for every database claim — object or string form,
 * batchable or not — and reports the table:columns no batched lookup may
 * be registered for.
 *
 * The verifier's lookups are keyed by table:column only, and Laravel's
 * presence-verifier interface gives `getCount()` no way to tell which rule
 * is asking. So the moment two rules with different query semantics share a
 * physical column — `exists` vs `unique`, a `unique` with `ignore()` vs one
 * without, a batchable rule beside a non-batchable one (string-form, no
 * explicit column, closure constraints) whose values were never collected —
 * a registered lookup answers at least one of them wrongly. The edit-form
 * idiom (`exists` + `unique->ignore()` on one field) is the everyday
 * instance.
 *
 * Group-level conflict detection inside {@see BatchDatabaseChecker} only
 * sees rules that formed groups; this scan sees every claim, including
 * rules the collector skipped. Poisoned columns fall back to per-item
 * verification, which answers each rule from the real database.
 *
 * @internal
 */
final class DatabaseClaimScanner
{
    /**
     * @param  array<string, mixed>  $rules  Field (or expanded attribute) → rules
     * @return array<string, true>
     */
    public static function findPoisonedTableColumns(array $rules): array
    {
        /** @var array<string, array<string, true>> $signatures */
        $signatures = [];
        $marker = 0;

        foreach ($rules as $field => $fieldRules) {
            $list = is_array($fieldRules)
                ? $fieldRules
                : (is_string($fieldRules) ? explode('|', $fieldRules) : [$fieldRules]);

            foreach ($list as $rule) {
                $claim = self::describeClaim((string) $field, $rule, $marker);

                if ($claim === null) {
                    continue;
                }

                [$tableColumn, $signature] = $claim;
                $signatures[$tableColumn][$signature] = true;
            }
        }

        $poisoned = [];

        foreach ($signatures as $tableColumn => $sigs) {
            if (count($sigs) > 1) {
                $poisoned[$tableColumn] = true;
            }
        }

        return $poisoned;
    }

    /**
     * Identify one rule's database claim as [table:column, signature], or
     * null when the rule touches no database column we can identify.
     *
     * Batchable object rules sign with their full query shape (two claims
     * with identical shapes would query identically and may share a lookup);
     * everything else — non-batchable objects, string-form rules — signs
     * with a unique marker, because its values are never collected into a
     * group and no lookup may answer for it.
     *
     * @return array{0: string, 1: string}|null
     */
    private static function describeClaim(string $field, mixed $rule, int &$marker): ?array
    {
        if ($rule instanceof Exists || $rule instanceof Unique) {
            return self::describeObjectClaim($field, $rule, $marker);
        }

        if (is_string($rule) && (str_starts_with($rule, 'exists:') || str_starts_with($rule, 'unique:'))) {
            return self::describeStringClaim($field, $rule, $marker);
        }

        return null;
    }

    /** @return array{0: string, 1: string}|null */
    private static function describeObjectClaim(string $field, Exists|Unique $rule, int &$marker): ?array
    {
        $table = BatchDatabaseChecker::getVerifierTable($rule);

        if ($table === null) {
            return null;
        }

        // extractMeta() maps a column-less rule ('NULL') to '' — either
        // way Laravel will infer the column from the attribute leaf.
        $column = BatchDatabaseChecker::getVerifierColumn($rule);

        if (in_array($column, [null, '', 'NULL'], true)) {
            $column = self::leafAttribute($field);
        }

        $signature = BatchDatabaseChecker::isBatchable($rule)
            ? BatchDatabaseChecker::batchableSignature($rule, $table, $column)
            : 'unbatchable#' . $marker++;

        return [$table . ':' . $column, $signature];
    }

    /** @return array{0: string, 1: string}|null */
    private static function describeStringClaim(string $field, string $rule, int &$marker): ?array
    {
        // Both recognised prefixes ('exists:', 'unique:') are 7 characters.
        $params = explode(',', substr($rule, 7));
        $tableToken = $params[0];

        if ($tableToken === '') {
            return null;
        }

        // Mirror Laravel's connection.table split.
        $table = str_contains($tableToken, '.')
            ? explode('.', $tableToken, 2)[1]
            : $tableToken;

        $column = $params[1] ?? '';

        if ($column === '' || $column === 'NULL') {
            $column = self::leafAttribute($field);
        }

        return [$table . ':' . $column, 'string#' . $marker++];
    }

    /**
     * The attribute segment Laravel would infer a query column from —
     * `guessColumnForQuery()` uses the last dot segment of an expanded
     * attribute path, and per-item rules are already keyed by the leaf.
     */
    private static function leafAttribute(string $field): string
    {
        $pos = strrpos($field, '.');

        return $pos === false ? $field : substr($field, $pos + 1);
    }
}
