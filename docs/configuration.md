# Configuration

There is no published config file and nothing to add to `.env`. The package reads no `config()`
key and registers no service provider — behaviour is set on the rule chain itself, with one
global safety fuse.

## There is nothing to publish

`vendor:publish` has no tag for this package. If you are looking for a `config/validation.php`
to tune, it does not exist by design: the rule chain carries its own configuration, and the
optimizations are transparent rather than opt-in.

Adding `HasFluentRules` to a form request is what enables the optimized path — see
[Getting started](getting-started.md). Nothing else needs switching on.

## The one global: the batch query cap

`BatchDatabaseChecker::$maxValuesPerGroup` caps how many distinct values a single batched
`whereIn` may carry. It defaults to `10_000`.

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
