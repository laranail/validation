<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Validation\Rules\Codes;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * An International Standard Book Number (ISO 2108), in either edition.
 *
 * The two are different algorithms wearing the same name:
 *
 *   ISBN-10   ten characters, mod-11, check written `X` when it is 10
 *   ISBN-13   thirteen digits, a GTIN-13 with a 978 or 979 prefix, so the
 *             GS1 mod-10 check digit applies
 *
 * Hyphens and spaces group the registration segments and carry no meaning for
 * validation, so they are stripped. Pass an explicit edition to accept only
 * one — a system that stores ISBN-13 should not silently take a 10.
 *
 * Pure tier — no IO.
 */
final readonly class Isbn implements ValidationRule
{
    public const int EDITION_10 = 10;

    public const int EDITION_13 = 13;

    /** @var list<int> */
    private array $editions;

    /**
     * @param list<int> $editions 10, 13, or both (the default).
     */
    public function __construct(array $editions = [self::EDITION_10, self::EDITION_13])
    {
        $this->editions = $editions === [] ? [self::EDITION_10, self::EDITION_13] : $editions;
    }

    /**
     * @param list<int> $editions
     */
    public static function passes(mixed $value, array $editions = [self::EDITION_10, self::EDITION_13]): bool
    {
        if (! is_string($value) && ! is_int($value)) {
            return false;
        }

        $isbn = strtoupper(str_replace([' ', '-'], '', (string) $value));

        return match (strlen($isbn)) {
            10      => in_array(self::EDITION_10, $editions, true) && self::isbn10IsValid($isbn),
            13      => in_array(self::EDITION_13, $editions, true) && self::isbn13IsValid($isbn),
            default => false,
        };
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! self::passes($value, $this->editions)) {
            $fail('laranail/validation::validation.isbn')->translate();
        }
    }

    /**
     * Mod-11 with weights 10 down to 1. Only the final character may be `X`,
     * standing for a check value of 10.
     */
    private static function isbn10IsValid(string $isbn): bool
    {
        if (preg_match('/^[0-9]{9}[0-9X]$/D', $isbn) !== 1) {
            return false;
        }

        $sum = 0;
        for ($i = 0; $i < 9; $i++) {
            $sum += ((int) $isbn[$i]) * (10 - $i);
        }

        $sum += $isbn[9] === 'X' ? 10 : (int) $isbn[9];

        return $sum % 11 === 0;
    }

    /**
     * A GTIN-13 whose GS1 prefix is reserved for books.
     */
    private static function isbn13IsValid(string $isbn): bool
    {
        if (! str_starts_with($isbn, '978') && ! str_starts_with($isbn, '979')) {
            return false;
        }

        return Gtin::passes($isbn, [13]);
    }
}
