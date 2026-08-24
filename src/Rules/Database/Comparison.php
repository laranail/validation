<?php declare(strict_types=1);

namespace Simtabi\Laranail\Validation\Rules\Database;

/**
 * The operator {@see CompareToColumn} applies — an enum rather than five
 * near-identical rule classes (the legacy shape), because the operator is
 * a parameter of one comparison, not five different validations.
 *
 * Backed by the short tokens so the string-alias form can spell them:
 * `laranail_compare_to_column:products,max_quantity,lte,id,@product_id`.
 */
enum Comparison: string
{
    case LessThan = 'lt';
    case LessThanOrEqual = 'lte';
    case GreaterThan = 'gt';
    case GreaterThanOrEqual = 'gte';
    case Equal = 'eq';
    case NotEqual = 'neq';

    public function compare(int|float|string $left, int|float|string $right): bool
    {
        // Numeric when both sides are — '9' must not beat '10' the way it
        // does lexicographically; strings otherwise.
        if (is_numeric((string) $left) && is_numeric((string) $right)) {
            $left = (float) $left;
            $right = (float) $right;
        }

        return match ($this) {
            self::LessThan => $left < $right,
            self::LessThanOrEqual => $left <= $right,
            self::GreaterThan => $left > $right,
            self::GreaterThanOrEqual => $left >= $right,
            self::Equal => ($left <=> $right) === 0,
            self::NotEqual => ($left <=> $right) !== 0,
        };
    }
}
