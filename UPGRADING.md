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

### A service provider is now registered

The package previously had none, and `docs/configuration.md` said so. It now ships
`ValidationServiceProvider`, auto-discovered via `extra.laravel.providers`.

It registers one config file under the flat `laranail.validation` key and publishes it as
`config/laranail-validation.php`:

```bash
php artisan vendor:publish --tag=laranail::validation-config
```

Nothing else changes: the builders never needed a provider and still do not. If you set
`BatchDatabaseChecker::$maxValuesPerGroup` in your own `AppServiceProvider`, it still works —
but the config key is now the better home, and the provider applies it at boot.

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

### `unique` and `exists` now agree with the database

Two fixes to the batched presence check. Both make previously-passing input fail, and both
matter because the failure they replace was silent.

The batched query matches with `whereIn`, so the **database** decides what equals what — its
collation, its padding, its numeric coercion. The results were then compared to the submitted
values as exact PHP array keys, which is a different comparison. On any case-insensitive
collation — MySQL's `utf8mb4_0900_ai_ci` default, SQL Server's default, a Postgres `citext`
column — a `unique` rule reported "not taken" for a value the database considered taken, and
the duplicate was written to a column the database treats as unique. Where the two comparisons
cannot be shown to agree, the group now falls back to per-item queries: correct, and slower.

Separately, batched groups were keyed on table and column alone and **assigned**, so a second
field checking the same column replaced the first outright and left it unchecked. Two fields
against one column is ordinary — a primary and a backup address both against `users.email`.
The tell was that the outcome depended on declaration order.

**Audit if:** you rely on `unique` against a case-insensitive column, or two fields in one
request validate against the same table and column. Rows admitted under the old behaviour are
still in your database; this fixes new writes, not existing ones.

### Conditional rules now see a `boolean` dependent the way Laravel does

Laravel converts a dependent rule's `true`/`false` parameters to real booleans whenever the
dependent field is **declared** `boolean`, not only when its submitted value already is one.
Two paths skipped that:

- `exclude_unless:notify,true` did not match a `notify` of `1` or `'1'` — values the `boolean`
  rule accepts — and under `exclude_unless` a non-match *excludes*, so the field disappeared
  from `validated()` while Laravel kept it.
- Removing a satisfied attribute for the fast-check phase hid it from Laravel's own
  `shouldConvertToBoolean()`, so `required_if:items.*.notify,true` stopped matching and the
  field went unenforced. This only affected wildcard-expanded attributes, since only those
  enter the fast-check map.

**Audit if:** you send booleans as `0`/`1` from a form — the common case — and gate a field on
them with `required_if`, `exclude_unless`, `prohibited_if` or any other dependent rule. A field
you expected in `validated()` may have been missing, and a required field may not have been
required.

### Exclusions no longer carry between array items

A per-item validator is reused across every item sharing a rule shape, and Laravel's
`$excludeAttributes` is append-only — `passes()` and `setData()` both leave it alone. So once
one item excluded an attribute, every later item skipped its own copy too, with no error
raised. `exclude_if` / `exclude_unless` are pre-evaluated and were never affected; `exclude`,
`exclude_with` and `exclude_without` were.

**Audit if:** you use `exclude_with` / `exclude_without` inside an array. Items after the first
excluded one were not validated at all.

---

[← Docs index](README.md#documentation)
