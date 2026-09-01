<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Validation\Tests\Fixtures\Registry;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/** A consumer-authored rule, as small as one can be. */
final class EvenNumber implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_numeric($value) || ((int) $value) % 2 !== 0) {
            $fail('The :attribute must be an even number.');
        }
    }
}
