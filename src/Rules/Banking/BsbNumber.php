<?php declare(strict_types=1);

namespace Simtabi\Laranail\Validation\Rules\Banking;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Simtabi\Laranail\Validation\Contracts\ClientCheckable;

/**
 * An Australian Bank State Branch number: six digits, written `062-000` or
 * `062000`.
 *
 * Format-only, and honestly so: BSBs carry no check digit, and the register
 * of live bank prefixes churns as institutions merge — a bundled snapshot
 * of it would reject real branches within a year. What can be checked is
 * checked exactly (six digits, the hyphen in the one place it may appear,
 * `D`-anchored so a trailing newline fails); whether the branch exists is
 * the payment network's question.
 *
 * Pure tier — no IO.
 */
final class BsbNumber implements ClientCheckable, ValidationRule
{
    private const string PATTERN = '/^\d{3}-?\d{3}$/D';

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || preg_match(self::PATTERN, $value) !== 1) {
            $fail('laranail-validation::validation.bsb_number')->translate();
        }
    }

    public function clientRules(): array
    {
        return [['rule' => 'regex', 'params' => ['pattern' => self::PATTERN]]];
    }
}
