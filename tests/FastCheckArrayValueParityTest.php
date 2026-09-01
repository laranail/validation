<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Validator;
use Simtabi\Laranail\Validation\FluentValidator;

// =========================================================================
// The fast-check phase reads values out of an Arr::dot() map of the payload.
// Arr::dot() recurses into a non-empty array and emits only its leaves — it
// never emits a key for the array node itself. So for an attribute whose
// value IS a non-empty array the key is absent, and reading it with `?? null`
// handed the closure null. Under `nullable` the closure then reported
// "satisfied", the rule was dropped before Laravel ever saw it, and an array
// silently passed a `string` rule.
//
// Two things are required to reach the defect, and a test that drops either
// one is a no-op guard:
//
//   1. FluentValidator / HasFluentRules, NOT RuleSet::check() — RuleSet's
//      per-item path reads the real value, and runFastCheckPhase() returns
//      early unless withFastChecks() primed it.
//   2. Pipe-STRING rules ('nullable|string'), not array form
//      (['nullable', 'string']) — only the string form is compiled into a
//      fast-check closure. Verified: with the fix reverted, string form
//      diverges and array form does not.
//
// The rule sets therefore live in a variable rather than inline in the
// Validator::make() call, so Rector's ValidationRuleArrayStringValueToArrayRector
// does not helpfully convert them to array form and silently defuse the test.
// =========================================================================

final class ArrayValueParityValidator extends FluentValidator
{
    /**
     * @param  array<string, mixed>  $data
     * @param  array<string, mixed>  $rules
     */
    public function __construct(array $data, array $rules)
    {
        parent::__construct($data, $rules);
    }
}

it('matches native when a wildcard value is a non-empty array', function (): void {
    $cases = [
        'string rule receives an array' => [
            ['items' => [['name' => ['oops']]]],
            ['items.*.name' => 'nullable|string'],
        ],
        'array|min receives a too-short array' => [
            ['items' => [['tags' => ['a']]]],
            ['items.*.tags' => 'nullable|array|min:3'],
        ],
        'string|max receives an array' => [
            ['items' => [['n' => ['aaaaaaaa']]]],
            ['items.*.n' => 'nullable|string|max:5'],
        ],
        'nested arrays several levels down' => [
            ['items' => [['meta' => ['a' => ['b' => 'c']]]]],
            ['items.*.meta' => 'nullable|string'],
        ],
    ];

    foreach ($cases as $label => [$data, $rules]) {
        $native = Validator::make($data, $rules)->passes();
        $optimized = new ArrayValueParityValidator($data, $rules)->passes();

        expect($native)->toBeFalse("native should reject: {$label}")
            ->and($optimized)
            ->toBe($native, "fast-check drifted from native: {$label}");
    }
});

it('still matches native for the cases that already worked', function (): void {
    $cases = [
        // Scalar — the hot path, unchanged.
        'scalar value' => [
            ['items' => [['name' => 'ok']]],
            ['items.*.name' => 'nullable|string'],
        ],
        'explicit null under nullable' => [
            ['items' => [['name' => null]]],
            ['items.*.name' => 'nullable|string'],
        ],
        // Empty arrays ARE emitted by Arr::dot(), so they never hit the fallback.
        'empty array value' => [
            ['items' => [['tags' => []]]],
            ['items.*.tags' => 'nullable|array'],
        ],
        // Genuinely absent path still resolves to null, as before.
        'absent key' => [
            ['items' => [[]]],
            ['items.*.name' => 'nullable|string'],
        ],
        'array rule genuinely satisfied' => [
            ['items' => [['tags' => ['a', 'b', 'c']]]],
            ['items.*.tags' => 'nullable|array|min:3'],
        ],
    ];

    foreach ($cases as $label => [$data, $rules]) {
        $native = Validator::make($data, $rules)->passes();
        $optimized = new ArrayValueParityValidator($data, $rules)->passes();

        expect($native)->toBeTrue("native should accept: {$label}")
            ->and($optimized)
            ->toBe($native, "fast-check drifted from native: {$label}");
    }
});
