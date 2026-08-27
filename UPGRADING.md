# Upgrading

Breaking changes, and what to do about them. Versions not listed here need no action.

## v0.1.0 - 2026-08-27

The provider rename below can be applied automatically:

```bash
vendor/bin/rector process app/ --config vendor/laranail/validation/rector-migrate-0.1.php
```

The config declares no paths of its own, so pass them: `app/` at minimum, and usually `tests/` too —
a Testbench `getPackageProviders()` is the single most common place the old class name survives.
`config/` and `bootstrap/` are worth a pass. `testbench.yaml` is not PHP, so Rector will not see it;
grep for the old name there by hand.

The translation-key rename is a string change, which Rector has no clean rule for. It is a
find-and-replace, described below.


### The service provider moved into `Providers/`

| Before | After |
|---|---|
| `Simtabi\Laranail\Validation\ValidationServiceProvider` | `Simtabi\Laranail\Validation\Providers\ValidationServiceProvider` |

**Most applications need no change.** Laravel's package auto-discovery finds the provider through
`composer.json`, which moved with it.

You need this change if you name the class yourself:

- a Testbench `getPackageProviders()` in a test case,
- a manual entry in `config/app.php` or `bootstrap/providers.php`,
- a `testbench.yaml` `providers:` list — worth grepping for specifically, since it is neither
  `.php` nor `.json` and a namespace sweep over source files misses it.

Left unchanged, it fatals with "class not found" the moment the provider is registered — at boot,
for the whole application.

### The translation namespace is now `laranail/validation::`

| Before | After |
|---|---|
| `laranail-validation::validation.iban` | `laranail/validation::validation.iban` |
| `lang/vendor/laranail-validation/` | `lang/vendor/laranail/validation/` |

**There is no alias.** The old namespace is not registered at all, so a key spelled the old way
returns *itself* — `laranail-validation::validation.iban` renders where the message should be. No
exception is raised, which is what makes this worth checking for rather than waiting to notice.

Two places to look:

```bash
grep -rn 'laranail-validation::' app/ resources/ config/ lang/
ls lang/vendor/laranail-validation 2>/dev/null   # a published override, now read from elsewhere
```

If you published the translations, move the directory:

```bash
mkdir -p lang/vendor/laranail
git mv lang/vendor/laranail-validation lang/vendor/laranail/validation
```

That nesting is not incidental — Laravel interpolates the namespace into the override path itself
(`FileLoader::loadNamespaceOverrides()` reads
`{$path}/vendor/{$namespace}/{$locale}/{$group}.php`), so the slash is exactly where it looks.

## v1.0.0 - 2026-08-24

The mechanical break below (`getEachRules()`) can be applied automatically:

```bash
vendor/bin/rector process --config vendor/laranail/validation/rector-migrate-1.0.php
```


### The PHP floor is now `^8.5`

The package requires PHP 8.5. The `Rules/Chrono/` family and the 1.0 platform work target the
8.5 runtime, and the test matrix runs 8.5 only. Applications on PHP 8.4 must upgrade the runtime
before taking this version; no code changes are required beyond that.

### `ArrayRule::getEachRules()` is removed

It was an exact alias of `getEachKeyedRules()` — same body, wider advertised type. Replace
calls one-for-one:

| Before | After |
|---|---|
| `$rule->getEachRules()` | `$rule->getEachKeyedRules()` |

The narrow getters carry the whole surface: `getEachKeyedRules(): ?array` for the keyed form,
`getEachListRule(): ?ValidationRule` for the list form.

### The Laravel-11 compatibility branches are gone

`contains()` always constructs `Rules\Contains` (the pipe-string fallback and its serializer
are removed), `doesntContain()` no longer throws a `RuntimeException` that could not occur on
the supported floor, and `requiredUnless()`/`prohibitedUnless()` with a closure or bool now
construct `RequiredUnless`/`ProhibitedUnless` directly instead of inverting into the `If`
twins. Verdicts are unchanged; only unreachable code and misleading comments went away. If an
application type-checked against the inverted `RequiredIf` instance, match on
`RequiredUnless` instead.

## v0.1.1 - 2026-08-24

### The optimized pipeline now agrees with Laravel where it silently did not (P1–P5)

Five fast-path defects made `RuleSet::validate()` (and FormRequests using the optimizer) return a
different verdict from a vanilla Laravel validator. Each is now byte-identical to Laravel, which
means previously-accepted input can now fail — if data relied on the old behaviour, it was relying
on the bug:

- **`regex` on a PCRE error fails (P1, security).** A pattern that aborts mid-match (backtrack
  limit, malformed pattern) was treated as passing, so a ReDoS-shaped input sailed past a regex
  deny-list. Laravel rejects on a PCRE error; the fast path now agrees. `not_regex` never
  fabricates a verdict on an error either — the server's (Laravel-identical) answer stands.
- **`required` rejects whitespace-only strings (P2).** `"   "` and `"\t\n"` passed the fast path;
  Laravel's `required` trims. A form that accepted whitespace-only input now reports the missing
  field it always should have.
- **`date_equals` compares the full timestamp (P3).** `date_equals:2030-01-01` accepted
  `"2030-01-01 08:00:00"` by reducing both sides to the calendar day. It now fails, as it always
  did in Laravel.
- **`in:`/`not_in:` never out-decide the installed Laravel (P4).** The fast path compared
  strictly, so on Laravel ≤ 13.25 (loose comparison) `not_in:10` fast-accepted `"10.0"` and
  `"1e1"` — values that deny-list rejects. Laravel itself then went strict in v13.26, inside this
  package's supported range, so no single comparison is right on both. The fast path now decides
  only where loose and strict agree — `in` fast-passes on a strict match, `not_in` on a loose
  non-match — and hands the boundary cases to the installed Laravel, whose own semantics are the
  verdict.
- **`exists` + `unique->ignore()` on one field batch correctly (P5).** The edit-form idiom —
  the value must exist AND be unique ignoring the row being edited — could fail a valid submission
  (or admit a duplicate, with a non-batchable second rule) because both rules were answered from
  one shared lookup. Such columns now fall back to per-item verification; the verdict matches
  Laravel, at the cost of batching for that column only.

### Anchored patterns reject trailing newlines (P6, P7)

Every anchored single-line pattern in the rule library now carries the `D` modifier, closing the
PCRE quirk where `$` also matches just before a final `"\n"`. `"my-slug\n"` no longer passes
`Slug`, `"admin\n"` no longer slips past `Username`'s reserved list (or a `unique` index holding
`"admin"`), and the same applies to `Jwt`, `DomainName`, `PersonName`, `EthereumAddress`, `SemVer`,
`Subdomain`, `MacAddress`, `CaseStyle`, `Vin`, `Iban`, `Bic`, `Isin`, `Isbn`, `Issn`,
`VendorIdentifier`, `Parity`, `NationalIdentifier`, `SubmissionTiming` and the postal-code
patterns. Rules that normalize input by design (`PostalCode`, `CssColor`, `MonetaryAmount`,
`Parity`, `NationalIdentifier` trim before matching) still tolerate surrounding whitespace — that
tolerance is deliberate and now pinned by tests. If a stored value carries a trailing newline it
was stored with the bug; trim it on the way in.

### `date_format` combined with a date comparison uses the slow path

`date_format:Y-m-d|after:2029-06-15` fast-accepted any value that merely matched the format — the
comparison never ran. The combination no longer compiles to a fast check and is decided by
Laravel itself. No API change; only wrong verdicts change.

### `FluentRule::url()`, `ip()`, `ipv4()`, `ipv6()` and `macAddress()` return their own node

They returned `StringRule` and now return `UrlRule`, `IpAddressRule` and `MacAddressRule`. The
surface each offers is narrower and specific to the field — which is the package's whole premise,
and these five were the places it was not being kept.

Existing chains are unaffected: `FluentRule::url()->required()->max(255)` compiles to the same
rules it always did. Two things break:

- **A type hint or an assignment declared as `StringRule`.** Change it to the new node, or to
  `Contracts\FluentRuleContract`, which all of them implement.
- **A call to a `StringRule` method that never applied to the field** — `->hexColor()` on an IP
  field, `->uuid()` on a URL. Those were always meaningless and now do not exist.

`FluentRule::url()` also gains defaults it did not have: `http`/`https` only, and no `user:pass@`
in the authority. A form that legitimately accepts `ftp://` needs `->scheme(['ftp'])`, and one that
accepts credentials needs `->allowCredentials()`. Both were previously accepted silently, which is
the reason for the change rather than an argument against it.

### `Rules\Text\Username` rejects reserved names by default

`admin`, `support`, `api`, `root` and about thirty more — every one a name that breaks something
concrete rather than merely being undesirable. Matching is case-insensitive and ignores separators,
so `a.d.m.i.n` and `ad-min` are refused too.

Restore the old behaviour with `new Username(reserved: [])`, or
`FluentRule::username()->reserved([])`. To keep the list and add to it, use
`->alsoReserved(['acme'])`.

### `Rules\Text\PersonName` accepts several names in one field

It always did — the change is that the count is now bounded on request rather than unbounded
always, and that a count failure reports the count instead of the character message. Nothing that
passed before fails now. `PersonName::single()` is the opt-in to the strict single-token reading.

The `person_name` translation key is unchanged; `person_name_min`, `person_name_max` and
`person_name_required` are new. A published `lang/vendor/laranail-validation/en/validation.php`
needs those three added, or those failures render as the raw key.

## v0.1.0 - 2026-08-15

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
