<?php declare(strict_types=1);

namespace Simtabi\Laranail\Validation\Tests\Fixtures;

use Simtabi\Laranail\Validation\FluentFormRequest;
use Simtabi\Laranail\Validation\FluentRule;

/**
 * FormRequest fixture that reads a route parameter inside both `authorize()`
 * and `rules()`. Exercises the tester's `withRoute()` integration — without
 * it, both methods would dereference null and fatal.
 *
 * @internal
 */
final class RouteAwareFluentFormRequest extends FluentFormRequest
{
    public function authorize(): bool
    {
        $owner = $this->route('owner_id');

        return (int) $owner === 42;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $minLength = $this->route('min_length', 3);

        return [
            'title' => FluentRule::string()->required()->min((int) $minLength),
        ];
    }
}
