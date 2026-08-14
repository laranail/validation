<?php declare(strict_types=1);

use Illuminate\Validation\Concerns\ValidatesAttributes;

/**
 * Laravel's `integer:strict` is honored by `validateInteger` only on
 * Laravel 12.23+ (it gained an `array $parameters` argument there). On
 * older Laravel the modifier is silently ignored — `validateInteger`
 * runs `filter_var(..., FILTER_VALIDATE_INT)` and accepts numeric
 * strings. Tests that assert strict-mode rejection through Laravel's
 * outer validator must skip on the older path.
 */
function laravelSupportsIntegerStrict(): bool
{
    $reflection = new ReflectionMethod(
        ValidatesAttributes::class,
        'validateInteger'
    );

    return count($reflection->getParameters()) >= 3;
}

/**
 * Pick out the compiled rules that are instances of a given rule class.
 *
 * Rule objects that can carry closures (Exists, Unique, Dimensions) must never
 * be stringified during compilation — __toString() silently drops them. Use
 * this to assert the object itself survived rather than its lossy string form.
 *
 * @param  list<object|string>  $rules
 * @param  class-string  $type
 * @return list<object>
 */
function rulesOfType(array $rules, string $type): array
{
    return array_values(array_filter(
        $rules,
        static fn (object|string $rule): bool => $rule instanceof $type,
    ));
}
