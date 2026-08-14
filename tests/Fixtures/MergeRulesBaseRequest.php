<?php declare(strict_types=1);

namespace Simtabi\Laranail\Validation\Tests\Fixtures;

use Illuminate\Foundation\Http\FormRequest;
use Simtabi\Laranail\Validation\FluentRule;
use Simtabi\Laranail\Validation\HasFluentRules;

/**
 * Abstract base that supplies shared fields through rules(). A concrete
 * subclass adds/overrides via schema(); the subclass (more derived) wins any
 * shared key, and both layers merge.
 */
abstract class MergeRulesBaseRequest extends FormRequest
{
    use HasFluentRules;

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'shared' => FluentRule::string()->required()->in(['base']),
            'base_only' => FluentRule::string()->required(),
        ];
    }

    public function authorize(): bool
    {
        return true;
    }
}
