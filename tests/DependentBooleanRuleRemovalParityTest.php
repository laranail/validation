<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Validator;
use Simtabi\Laranail\Validation\FluentRule;
use Illuminate\Validation\ValidationException;
use Simtabi\Laranail\Validation\FluentValidator;

/**
 * A dependent field that the fast-check phase removed.
 *
 * Laravel's dependent rules — `required_if`, `exclude_if`, `prohibited_if`
 * and the rest — convert their `true`/`false` parameters to real booleans only
 * when the DEPENDENT field is declared `boolean`, which it learns by reading
 * `$this->rules[$parameter]` at validation time.
 *
 * The fast-check phase removes a satisfied attribute from `$this->rules`
 * BEFORE `parent::passes()` and restores it after, so a dependent that passed
 * its own `boolean` rule was missing from that lookup exactly when Laravel
 * consulted it. The conversion was skipped, `required_if:items.*.notify,true`
 * stopped matching a `notify` of `1`, and a required field went unenforced —
 * the validator reported success where Laravel reported an error.
 *
 * Only wildcard-expanded attributes reach the fast-check map, which is why
 * this needs an array: the same rules on a top-level field never trigger it.
 */
final class WildcardRequiredIfValidator extends FluentValidator
{
    /** @param  array<string, mixed>  $data */
    public function __construct(array $data)
    {
        parent::__construct($data, [
            'items.*.notify' => FluentRule::boolean(),
            'items.*.email'  => FluentRule::string()->requiredIf('items.*.notify', 'true'),
        ]);
    }
}

function vanillaWildcardFails(mixed $notify): bool
{
    return Validator::make(
        ['items' => [['notify' => $notify]]],
        [
            'items.*.notify' => ['boolean'],
            'items.*.email'  => ['required_if:items.*.notify,true', 'string'],
        ],
    )->fails();
}

function optimizedWildcardFails(mixed $notify): bool
{
    try {
        new WildcardRequiredIfValidator(['items' => [['notify' => $notify]]])->validated();

        return false;
    } catch (ValidationException) {
        return true;
    }
}

it('still enforces required_if when the dependent was fast-checked away', function (mixed $notify): void {
    // 1 and '1' are the cases that broke: accepted by `boolean`, so removed by
    // the fast-check phase, and not booleans, so the conversion mattered.
    expect(optimizedWildcardFails($notify))->toBe(vanillaWildcardFails($notify));
})->with([
    'int one'     => 1,
    'string one'  => '1',
    'real true'   => true,
    'int zero'    => 0,
    'string zero' => '0',
    'real false'  => false,
]);

it('does not start requiring the field when the condition is genuinely unmet', function (): void {
    // The control: the fix restores a conversion, it must not force a match.
    expect(optimizedWildcardFails(0))->toBeFalse()
        ->and(vanillaWildcardFails(0))->toBeFalse();
});

it('leaves a dependent with no boolean declaration alone', function (): void {
    // Without a `boolean` rule neither validator converts, so the string
    // comparison stands and the field is not required.
    $data = ['items' => [['notify' => 'yes']]];

    $vanilla = Validator::make($data, [
        'items.*.notify' => ['string'],
        'items.*.email'  => ['required_if:items.*.notify,true', 'string'],
    ]);

    expect($vanilla->fails())->toBeFalse();
});
