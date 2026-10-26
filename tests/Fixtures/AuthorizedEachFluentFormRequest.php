<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Validation\Tests\Fixtures;

use Simtabi\Laranail\Validation\FluentFormRequest;
use Simtabi\Laranail\Validation\FluentRule;

/**
 * Authorized FormRequest fixture exercising each() wildcard rules — the
 * shape downstream consumers (mijntp/hihaho/collectiq) most commonly
 * validate bulk imports against.
 *
 * @internal
 */
final class AuthorizedEachFluentFormRequest extends FluentFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'items' => FluentRule::array()->required()->each([
                'name' => FluentRule::string('Name')->required()->max(255),
                'qty' => FluentRule::numeric('Quantity')->required()->integer()->min(1),
            ]),
        ];
    }
}
