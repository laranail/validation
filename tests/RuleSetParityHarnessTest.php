<?php declare(strict_types=1);

use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Simtabi\Laranail\Validation\RuleSet;

/**
 * End-to-end fast-check parity harness: every cell runs the same rules and
 * data through BOTH the optimized pipeline (`RuleSet::validate()`) and a
 * vanilla Laravel `Validator`, asserting the FINAL verdicts are identical.
 *
 * This is the regression guard for the optimizer bug class where the fast
 * path disagrees with Laravel (a green suite hid every one of these until
 * the harness existed). It differs from FastCheckParityTest deliberately:
 * that suite pins the compiled closure's verdict; this one pins the verdict
 * a consumer actually receives — fast-check removal, conditional phases,
 * wildcard expansion, and slow-path fallback included. A closure returning
 * false only falls back to Laravel, so only this level can prove the
 * pipeline never *accepts* what Laravel rejects (or the reverse).
 *
 * Each grid cell is exercised in both shapes the pipeline optimizes:
 * a top-level field and a wildcard item field.
 */

/** @param array<string, mixed> $rules
 * @param array<string, mixed> $data */
function harnessRuleSetVerdict(array $rules, array $data): bool
{
    try {
        RuleSet::from($rules)->validate($data);

        return true;
    } catch (ValidationException) {
        return false;
    }
}

/** @param array<string, mixed> $rules
 * @param array<string, mixed> $data */
function harnessVanillaVerdict(array $rules, array $data): bool
{
    return Validator::make($data, $rules)->passes();
}

/**
 * Assert both pipelines agree for a rule applied to one value, in both the
 * top-level and the wildcard shape.
 */
function assertVerdictParity(string $rule, mixed $value): void
{
    $shapes = [
        'top-level' => [['f' => $rule], ['f' => $value]],
        'wildcard' => [['items.*.f' => $rule], ['items' => [['f' => $value], ['f' => $value]]]],
    ];

    foreach ($shapes as $shape => [$rules, $data]) {
        $optimized = harnessRuleSetVerdict($rules, $data);
        $vanilla = harnessVanillaVerdict($rules, $data);

        expect($optimized)->toBe(
            $vanilla,
            sprintf(
                'Verdict drift (%s) for rule "%s" with value %s: RuleSet=%s, Laravel=%s',
                $shape,
                $rule,
                var_export($value, true),
                $optimized ? 'pass' : 'fail',
                $vanilla ? 'pass' : 'fail',
            ),
        );
    }
}

/** @return list<string> */
function harnessRules(): array
{
    return [
        // Presence + type — the `required` emptiness contract (whitespace!).
        'required',
        'required|string',
        'required|string|min:2|max:5',
        'required|array',
        'nullable|string',

        // in / not_in — Laravel compares loosely ('10.0' == '10').
        'in:10,20',
        'not_in:10,20',
        'string|in:a,b',
        'string|not_in:a,b',
        'integer|in:10',
        'integer|not_in:10',

        // Date comparisons — full-timestamp semantics.
        'date|date_equals:2030-01-01',
        'date|after:2030-01-01',
        'date|before:2030-01-01',
        'date|after_or_equal:2030-01-01',
        'date|before_or_equal:2030-01-01',
        'date_format:Y-m-d|after:2029-06-15',
        'date_format:Y-m-d|date_equals:2030-01-01',

        // Regex under normal PCRE conditions (the error path has its own test).
        'regex:/^[a-z]+$/',
        'not_regex:/^[0-9]+$/',

        // Breadth: formats and sizes the compiler covers.
        'numeric|min:1|max:10',
        'boolean',
        'email',
        'uuid',
        'alpha_dash',
        'accepted',
        'declined',
        'digits:3',
        'digits_between:2,4',
    ];
}

/** @return list<mixed> */
function harnessValues(): array
{
    return [
        // Emptiness spectrum — '   ' is the P2 divergence (trim()-empty).
        null,
        '',
        '   ',
        "\t\n",
        '0',
        0,

        // Loose-comparison shapes for in/not_in (P4).
        '10',
        '10.0',
        '1e1',
        10,
        '20.00',

        // Date/time shapes for date_equals and comparisons (P3).
        '2030-01-01',
        '2030-01-01 08:00:00',
        '2030-01-01 00:00:00',
        '2029-12-31',
        '2031-06-15',
        '2028-01-01',

        // Ordinary strings, numbers, arrays.
        'abc',
        'a',
        '12345',
        '123',
        'user@example.com',
        2.5,
        true,
        [],
        ['a'],
    ];
}

/** @return iterable<string, array{string, mixed}> */
function ruleSetParityGrid(): iterable
{
    foreach (harnessRules() as $rule) {
        foreach (harnessValues() as $i => $value) {
            yield "{$rule} :: value #{$i} " . var_export($value, true) => [$rule, $value];
        }
    }
}

it('RuleSet::validate() verdict matches a vanilla Laravel Validator', function (string $rule, mixed $value): void {
    assertVerdictParity($rule, $value);
})->with(ruleSetParityGrid());

/**
 * P1 (HIGH, security): a PCRE error — backtrack-limit exhaustion on a
 * catastrophic pattern, the classic ReDoS shape — must never make the fast
 * path ACCEPT a value the real validator rejects. Laravel's validateRegex
 * returns `preg_match(...) > 0`, so a PCRE error (false) REJECTS; the fast
 * path must agree (fail closed), not treat "not 0" as a match.
 *
 * Deterministic: the backtrack limit is lowered for the assertion window so
 * the PCRE error fires reliably and instantly — no timing, no flakiness.
 */
it('P1: regex fast path fails closed when PCRE aborts, matching Laravel', function (): void {
    $pattern = '/^(a+)+$/';
    $value = str_repeat('a', 200) . '!';
    $original = (string) ini_get('pcre.backtrack_limit');

    ini_set('pcre.backtrack_limit', '100');

    try {
        // Precondition: this cell really is the PCRE-error path, not a match.
        expect(preg_match($pattern, $value))->toBe(false)
            ->and(preg_last_error())->not->toBe(PREG_NO_ERROR);

        // Laravel rejects on PCRE error…
        expect(harnessVanillaVerdict(['f' => 'required|string|regex:' . $pattern], ['f' => $value]))->toBeFalse();

        // …and the optimized pipeline must agree, in both shapes.
        assertVerdictParity('required|string|regex:' . $pattern, $value);
    } finally {
        ini_set('pcre.backtrack_limit', $original);
    }
});

/**
 * P1 companion: `not_regex` on a PCRE error must never be decided by the
 * fast path either. Laravel's validateNotRegex returns `preg_match(...) < 1`,
 * which PASSES on a PCRE error — the fast path must produce the identical
 * verdict by deferring, never by fabricating its own.
 */
it('P1: not_regex fast path matches Laravel when PCRE aborts', function (): void {
    $pattern = '/^(a+)+$/';
    $value = str_repeat('a', 200) . '!';
    $original = (string) ini_get('pcre.backtrack_limit');

    ini_set('pcre.backtrack_limit', '100');

    try {
        expect(preg_match($pattern, $value))->toBe(false);

        assertVerdictParity('required|string|not_regex:' . $pattern, $value);
    } finally {
        ini_set('pcre.backtrack_limit', $original);
    }
});
