<?php declare(strict_types=1);

namespace Simtabi\Laranail\Validation\Rules\Text;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Simtabi\Laranail\Validation\Contracts\ClientCheckable;

/**
 * An identifier written in a particular casing convention.
 *
 * One rule rather than five classes, because the alternative is five files
 * that differ by a single pattern and a message key, and because the choice
 * is naturally a parameter: `new CaseStyle(CaseStyle::SNAKE)`.
 *
 * ASCII by design. These validate identifiers — config keys, column names,
 * class names, CSS classes — where the surrounding tooling is ASCII anyway.
 * For human-facing text see {@see PersonName}.
 *
 * Laravel already ships `lowercase` and `uppercase`, so neither appears here.
 *
 * Pure tier — no IO.
 */
final readonly class CaseStyle implements ClientCheckable, ValidationRule
{
    public const string CAMEL = 'camel';

    public const string KEBAB = 'kebab';

    public const string PASCAL = 'pascal';

    public const string SNAKE = 'snake';

    public const string TITLE = 'title';

    /**
     * Each pattern requires at least one character and forbids the shapes that
     * make two identifiers look identical: leading, trailing and doubled
     * separators.
     */
    private const array PATTERNS = [
        self::CAMEL => '/^[a-z][a-zA-Z0-9]*$/',
        self::PASCAL => '/^[A-Z][a-zA-Z0-9]*$/',
        self::SNAKE => '/^[a-z0-9]+(?:_[a-z0-9]+)*$/',
        self::KEBAB => '/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
        // Each space-separated word starts with a capital. Digits may follow.
        self::TITLE => '/^[A-Z][a-z0-9]*(?: [A-Z][a-z0-9]*)*$/',
    ];

    public function __construct(private string $style) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! self::passes($value, $this->style)) {
            $fail('laranail-validation::validation.case_style.' . $this->style)->translate();
        }
    }

    public static function passes(mixed $value, string $style): bool
    {
        if (! is_string($value) || ! isset(self::PATTERNS[$style])) {
            return false;
        }

        return preg_match(self::PATTERNS[$style], $value) === 1;
    }

    /**
     * The configured style's pattern, which is the whole check.
     *
     * Null for an unknown style: the rule rejects everything in that case, and
     * advertising nothing routes it to the server rather than shipping a
     * pattern that does not exist.
     */
    /**
     * @return list<array{rule: string, params: array<array-key, string>}>
     */
    public function clientRules(): array
    {
        $pattern = self::PATTERNS[$this->style] ?? null;

        return $pattern === null ? [] : [['rule' => 'regex', 'params' => ['pattern' => $pattern]]];
    }
}
