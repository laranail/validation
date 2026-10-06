<?php

declare(strict_types=1);

use Illuminate\Http\Request;
use Illuminate\Routing\Redirector;
use Illuminate\Validation\Validator;
use Illuminate\Translation\Translator;
use Illuminate\Translation\ArrayLoader;
use Illuminate\Foundation\Http\FormRequest;
use Simtabi\Laranail\Validation\FluentSchema;
use Simtabi\Laranail\Validation\HasFluentRules;
use Simtabi\Laranail\Validation\Tests\TestCase;
use Illuminate\Contracts\Validation\Factory as ValidationFactory;

uses(TestCase::class)->in(__DIR__);

require_once __DIR__ . '/../src/Testing/PestExpectations.php';

/**
 * @param array<string, mixed> $data
 * @param array<string, mixed> $rules
 */
function makeValidator(array $data, array $rules): Validator
{
    return new Validator(
        new Translator(new ArrayLoader, 'en'),
        $data,
        $rules,
    );
}

/**
 * @param array<string, mixed> $rules
 * @param array<array-key, mixed> $data
 */
function createFormRequest(array $rules, array $data): FormRequest
{
    $formRequest = new class extends FormRequest
    {
        use HasFluentRules;

        /** @var array<string, mixed> */
        public static array $testRules = [];

        /** @return array<string, mixed> */
        public function rules(): array
        {
            return self::$testRules;
        }

        public function authorize(): bool
        {
            return true;
        }
    };

    $formRequest::$testRules = $rules;

    return bootFormRequest($formRequest, $data);
}

/**
 * Build a FormRequest that defines its rules through the FluentSchema
 * builder via a schema() method, mirroring createFormRequest().
 *
 * @param Closure(FluentSchema): array<string, mixed> $schema
 * @param array<array-key, mixed> $data
 */
function createSchemaFormRequest(Closure $schema, array $data): FormRequest
{
    $formRequest = new class extends FormRequest
    {
        use HasFluentRules;

        /** @var Closure(FluentSchema): array<string, mixed> */
        public static Closure $testSchema;

        /** @return array<string, mixed> */
        public function schema(FluentSchema $rules): array
        {
            return (self::$testSchema)($rules);
        }

        public function authorize(): bool
        {
            return true;
        }
    };

    $formRequest::$testSchema = $schema;

    return bootFormRequest($formRequest, $data);
}

/**
 * Resolve a configured FormRequest instance against a fake POST request,
 * wiring the container and redirector the way the framework would.
 *
 * @template T of FormRequest
 *
 * @param T $formRequest
 * @param array<array-key, mixed> $data
 *
 * @return T
 */
function bootFormRequest(FormRequest $formRequest, array $data): FormRequest
{
    $request = Request::create('/test', 'POST', $data);
    $instance = $formRequest::createFrom($request);
    $instance->setContainer(app());
    $instance->setRedirector(resolve(Redirector::class));

    return $instance;
}

/**
 * Build the validator a FormRequest creates for itself, by running its
 * protected createDefaultValidator() -- the method HasFluentRules overrides.
 *
 * Bound to the request, the closure takes FormRequest's declared return type,
 * the Validator contract, which has no passes(). HasFluentRules returns the
 * concrete Illuminate validator; this asserts that and returns it typed, so
 * the call sites need no per-line narrowing.
 */
function defaultValidatorFor(FormRequest $formRequest, ValidationFactory $factory): Validator
{
    $validator = (fn () => $this->createDefaultValidator($factory))->call($formRequest);

    if (! $validator instanceof Validator) {
        throw new LogicException(sprintf(
            '%s::createDefaultValidator() returned %s, expected %s.',
            $formRequest::class,
            get_debug_type($validator),
            Validator::class,
        ));
    }

    return $validator;
}
