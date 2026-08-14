<?php declare(strict_types=1);

namespace Simtabi\Laranail\Validation\Tests\Fixtures;

use Simtabi\Laranail\Validation\FluentFormRequest;
use Simtabi\Laranail\Validation\FluentRule;

/**
 * Unauthorized FormRequest fixture — `authorize()` returns false, so
 * validateResolved() raises AuthorizationException. Exercises the
 * tester's recorded-exception path.
 *
 * @internal
 */
final class UnauthorizedFluentFormRequest extends FluentFormRequest
{
    public function authorize(): bool
    {
        return false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'name' => FluentRule::string()->required(),
        ];
    }
}
