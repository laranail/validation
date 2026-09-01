<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Validation\Tests\Fixtures;

use Simtabi\Laranail\Validation\FluentRule;
use Simtabi\Laranail\Validation\FluentValidator;

/**
 * FluentValidator subclass mirroring hihaho's JsonImportValidator shape:
 * accepts an extra ctor arg (`$prefix`) used inside buildRules() to shape
 * the effective rule set. Exercises the tester's variadic ctor-arg
 * forwarding contract.
 *
 * @internal
 */
final class ExampleFluentValidator extends FluentValidator
{
    /** @param  array<string, mixed>  $data */
    public function __construct(array $data, private readonly string $prefix = '')
    {
        parent::__construct($data, $this->buildRules());
    }

    /** @return array<string, mixed> */
    private function buildRules(): array
    {
        $rule = FluentRule::string()->required();

        if ($this->prefix !== '') {
            $rule = $rule->startsWith($this->prefix);
        }

        return ['name' => $rule];
    }
}
