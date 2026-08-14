# Configuration

Almost nothing needs configuring. Behaviour is set on the rule chain itself; the config file
exists for the two things that cannot live there — a global safety fuse, and the opt-in string
rule aliases.

## Publishing

```bash
php artisan vendor:publish --tag=laranail::validation-config
```

That writes `config/laranail-validation.php`. The file name is prefixed so publishing can
never clobber an application's own `config/validation.php`; the settings themselves read from
the flat `laranail.validation` key.

The builders work without publishing anything. Adding `HasFluentRules` to a form request is
what enables the optimized path — see [Getting started](getting-started.md). Nothing else
needs switching on.

## String rule aliases are opt-in

`laranail.validation.aliases.enabled` is `false` by default, and every alias the extended rule
library registers is prefixed (`laranail_iban`, not `iban`).

This is deliberate. Laravel keeps validator extensions in a flat map and resolves them
last-writer-wins, so registering a generic name silently replaces whatever a sibling package,
a third-party package, or the application itself already put there — and the damage surfaces
far away as the wrong rule running. Rule classes are the canonical surface; aliases are a
convenience you switch on knowingly. The prefix is configurable so an application that already
owns the name can move ours aside rather than fight it.

## The batch query cap

`BatchDatabaseChecker::$maxValuesPerGroup` caps how many distinct values a single batched
`whereIn` may carry, so a hostile payload cannot turn one request into an unbounded `IN` list.
It defaults to `10_000`.

Set it in the published config — the provider applies it once at boot:

```php
// config/laranail-validation.php
'batch' => ['max_values_per_group' => 5_000],
```

The static is still writable directly, which is what you want in a test. Either way it is
**boot-time only**: mutating it per request is unsafe under Octane, where the value would leak
across requests sharing a worker.

```php
// AppServiceProvider::boot()
use Simtabi\Laranail\Validation\BatchDatabaseChecker;

BatchDatabaseChecker::$maxValuesPerGroup = 25_000;
```

> Set this once during boot. It is a mutable static, so changing it at request time is **not**
> safe under Octane or Swoole, where the value would leak across requests on the same worker.

The cap is a defence-in-depth fuse against a forgotten parent `max:N` or hostile bulk input, not
a performance tuning dial. Raising it means allowing a larger single query; the better fix is
usually to declare `max:N` on the parent array so the request is rejected before any query runs.

### What happens when a limit is hit

`BatchLimitExceededException` is thrown at validator-construction time, before any query
reaches the database. It carries the table, column, rule type, value count, limit, and a
`reason` of either:

| Reason | Meaning |
|---|---|
| `parent-max` | The parent array's own declared `max:N` was breached. `$attribute` is the concrete parent path. |
| `hard-cap` | `$maxValuesPerGroup` was exceeded. `$attribute` is `null`. |

At the documented entry points — `HasFluentRules`, `RuleSet::validate()`, `RuleSet::check()` —
it is remapped to `Illuminate\Validation\ValidationException`, so ordinary
`catch (ValidationException $e)` sites and Laravel's exception handler behave normally. Catch
`BatchLimitExceededException` directly if you want the structured fields.

> Because the throw happens while the validator is being built, the validator never exists — so
> a FormRequest's `failedValidation()` hook does **not** run for this exception.

Batching is skipped entirely, with no cap involved, when it cannot be done safely: when no
default `DatabasePresenceVerifier` is registered, when an `exists`/`unique` rule has a closure
callback, or when the rule has no explicit column and relies on Laravel inferring it from the
attribute name. Those fall back to per-item queries. See
[Batched database validation](performance.md#batched-database-validation).

## Per-chain options

Everything else is set on the rule that needs it, not globally:

| Call | Effect |
|---|---|
| `ArrayRule::withoutEachRules()` | Drop the `each()` rules from this chain |
| `EmailRule::withNativeValidation(bool $allowUnicode = false)` | Use PHP's native email validation instead of the default strategy |
| `RuleSet::withBag(string $name)` | Send failures to a named error bag |
| `FluentRulesTester::withRoute(array $parameters)` | Supply route parameters when testing a route-aware form request |

## Extending the builders

`FluentRule`, `RuleSet`, `FluentSchema` and each `Rules\*` class use Laravel's `Macroable`, so
an application can add its own methods. The package registers no macros itself — the names are
yours. Register them in a service provider's `boot()` for the same reason the batch cap belongs
there.

See [Rule reference](tools/fluent-rule.md) for the macro hook and the full per-type surface.

---

[← Docs index](../README.md#documentation)
