# Upgrading

Breaking changes, and what to do about them. Versions not listed here need no action.

## Unreleased

### Builder nodes moved out of `Rules\` into `Builder\`

`src/Rules/` now belongs to the extended rule library (`Iban`, `Vin`, `PostalCode`, …).
The twelve typed builder nodes and their shared traits moved so the two never share a
namespace.

| Before | After |
|---|---|
| `Simtabi\Laranail\Validation\Rules\AcceptedRule` | `Simtabi\Laranail\Validation\Builder\Nodes\AcceptedRule` |
| `Simtabi\Laranail\Validation\Rules\ArrayRule` | `Simtabi\Laranail\Validation\Builder\Nodes\ArrayRule` |
| `Simtabi\Laranail\Validation\Rules\BooleanRule` | `Simtabi\Laranail\Validation\Builder\Nodes\BooleanRule` |
| `Simtabi\Laranail\Validation\Rules\DateRule` | `Simtabi\Laranail\Validation\Builder\Nodes\DateRule` |
| `Simtabi\Laranail\Validation\Rules\DeclinedRule` | `Simtabi\Laranail\Validation\Builder\Nodes\DeclinedRule` |
| `Simtabi\Laranail\Validation\Rules\EmailRule` | `Simtabi\Laranail\Validation\Builder\Nodes\EmailRule` |
| `Simtabi\Laranail\Validation\Rules\FieldRule` | `Simtabi\Laranail\Validation\Builder\Nodes\FieldRule` |
| `Simtabi\Laranail\Validation\Rules\FileRule` | `Simtabi\Laranail\Validation\Builder\Nodes\FileRule` |
| `Simtabi\Laranail\Validation\Rules\ImageRule` | `Simtabi\Laranail\Validation\Builder\Nodes\ImageRule` |
| `Simtabi\Laranail\Validation\Rules\NumericRule` | `Simtabi\Laranail\Validation\Builder\Nodes\NumericRule` |
| `Simtabi\Laranail\Validation\Rules\PasswordRule` | `Simtabi\Laranail\Validation\Builder\Nodes\PasswordRule` |
| `Simtabi\Laranail\Validation\Rules\StringRule` | `Simtabi\Laranail\Validation\Builder\Nodes\StringRule` |
| `Simtabi\Laranail\Validation\Rules\Concerns\HasEmbeddedRules` | `Simtabi\Laranail\Validation\Builder\Concerns\HasEmbeddedRules` |
| `Simtabi\Laranail\Validation\Rules\Concerns\HasFieldModifiers` | `Simtabi\Laranail\Validation\Builder\Concerns\HasFieldModifiers` |
| `Simtabi\Laranail\Validation\Rules\Concerns\SelfValidates` | `Simtabi\Laranail\Validation\Builder\Concerns\SelfValidates` |

**Unchanged:** `FluentRule`, `FluentSchema`, `RuleSet`, `PreparedRules`, `Validated`,
`FluentValidator`, `FluentFormRequest`, `HasFluentRules`, `HasFluentValidation`,
`Contracts\FluentRuleContract`, and everything under `Testing\`. The entry points and the
advertised stable type all keep their FQNs, so most upgrades are a no-op.

**Who is affected.** Only code that names a node class directly — a type hint on a concrete
node, a `StringRule::macro(...)` call, or an `instanceof`. Code that calls
`FluentRule::string()` and type-hints `FluentRuleContract` needs no change.

**Migration.** Search and replace, longest match first so `Concerns` is not caught by the
second rule:

```bash
grep -rl 'Simtabi\\Laranail\\Validation\\Rules' app/ tests/ \
  | xargs sed -i '' \
      -e 's/Simtabi\\Laranail\\Validation\\Rules\\Concerns/Simtabi\\Laranail\\Validation\\Builder\\Concerns/g' \
      -e 's/Simtabi\\Laranail\\Validation\\Rules/Simtabi\\Laranail\\Validation\\Builder\\Nodes/g'
```

Prefer binding to `Contracts\FluentRuleContract` afterwards, so a future node reorganisation
does not reach your code at all.

### Validation results changed for six rules

These are Laravel-parity fixes: the optimized path now agrees with
`Illuminate\Validation\Validator` where it previously did not. **Input your application
used to accept may now be rejected** — that is the point, but it can surface as new
validation failures on existing data.

| Rule | Was | Now |
|---|---|---|
| `date` | accepted `'tomorrow'`, `'now'`, `'+1 week'`, `'2024-02-31'` | rejects them, matching `validateDate()`'s `checkdate()` |
| `url` | accepted any scheme, including `file://` and `mailto:` | uses `Str::isUrl()`'s protocol allow-list |
| `in:` / `not_in:` | split on `,`, so `in:"a,b","c"` was three values | splits with `str_getcsv`, so it is two |
| `min:` / `max:` | truncated the parameter to an integer (`min:2.5` behaved as `min:2`) | keeps fractional precision |
| `email` with a rule containing a literal `\|` | threw `BadMethodCallException` at validation time | compiles to array form and validates |
| `email` with `exists()`/`unique()` + a closure | silently dropped the closure constraint | keeps it |

The `url` and `exists()` items are the two worth auditing: a `url` rule that accepted
`file://` may have been load-bearing somewhere, and an `exists()` whose soft-delete or
tenant scope was being dropped will now correctly reject rows it previously accepted.

### Wildcard values that are arrays are now validated

`FluentValidator` / `HasFluentRules` previously dropped a rule when a wildcard attribute's
value was a non-empty array, so `['items.*.name' => 'nullable|string']` silently passed for
`['items' => [['name' => ['oops']]]]`. It now fails, as it always did under vanilla Laravel.

---

[← Docs index](README.md#documentation)
