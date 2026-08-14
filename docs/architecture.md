# Architecture

How a fluent rule chain becomes a validated payload: the builder layer that models rules as
objects, the compiler that lowers them to native Laravel rules, and the optimized execution
path that avoids Laravel's validator when it safely can.

## The three layers

The package is one pipeline with three separable stages. Each stage has a public entry point,
so tooling can hook in without reimplementing the stage above it.

| Layer | Entry point | Responsibility |
|---|---|---|
| Builders | `FluentRule` + `Rules\*` | Model a rule chain as a typed object |
| Compiler | `RuleSet` | Lower rule objects to native Laravel rules, and extract labels/messages |
| Execution | `HasFluentRules`, `OptimizedValidator`, `MemoizingValidator` | Run the compiled rules, fast-pathing where the result is provably identical |

### Builders

`FluentRule` is a static factory, not a class you hold. `FluentRule::string()` returns a
`Builder\Nodes\StringRule`, `FluentRule::date()` a `Builder\Nodes\DateRule`, and so on across
the twelve node classes in `src/Builder/Nodes/`. The type-specific surface lives on the
concrete class, which is what makes the autocompletion narrow — `StringRule` has no `mimes()`
to offer.

What every node shares lives in traits under `src/Builder/Concerns/`: `HasFieldModifiers` for
the modifier/conditional surface, `HasEmbeddedRules` for `unique()`/`exists()`/`enum()`, and
`SelfValidates` so a single node can validate a value on its own as an
`Illuminate\Contracts\Validation\ValidationRule`.

> `src/Rules/` is deliberately left free for the extended rule library (`Iban`, `Vin`,
> `PostalCode`, …). Builder nodes and domain rules are different things and do not share a
> namespace. Bind to `Contracts\FluentRuleContract` rather than a concrete node class.

`FluentRule::field()` is the untyped escape hatch. It accepts any modifier, which is why the
package also ships an opt-in arch test (`Testing\Arch\BansFieldRuleTypeMethods`) to keep
type-specific calls off it.

### Compiler

`RuleSet` turns an array of rule objects into the `string|array` shape Laravel's validator
expects. Three things happen in that lowering:

- **Flattening.** `each()` and `children()` are nested structures; Laravel's validator only
  understands flat dot-notation keys. The compiler walks the tree and emits `items.*.name`
  style keys.
- **Metadata extraction.** A label or per-rule message attached to a builder is pulled out into
  the `attributes` and `messages` arrays Laravel takes separately. This is why there is no
  parallel `messages()` method to keep in sync — see [Error messages](tools/error-messages.md).
- **Stringification where safe.** Rules compile to a pipe-joined string when every element can
  be represented as one, because that is the fastest form for Laravel to parse. Rule objects
  that cannot survive stringification stay as objects.

The lowering steps are exposed individually (`compile`, `compileToArrays`,
`compileWithMetadata`, `extractMetadata`, `expandWildcards`) so the Livewire bridge and any
external codegen, migration or analysis tooling can reuse them. See
[Compile pipeline](tools/rule-set.md#compile-pipeline-advanced).

### Execution

`HasFluentRules` on a form request is what activates the optimized path. Without it, compiled
rules still work — they are ordinary Laravel rules — but they run through Laravel's stock
validator.

Two validator subclasses carry the optimizations, and both are written to be
behaviour-identical to the stock validator rather than merely close to it:

- `OptimizedValidator` fast-checks expanded wildcard attributes with compiled closures before
  falling back to Laravel.
- `MemoizingValidator` memoizes string-rule parsing. `ValidationRuleParser::parse('max:255')`
  is a pure function of its input, but the stock validator recomputes it repeatedly.

`Internal\ItemValidator` runs the per-item loop for a wildcard group, and owns the two caches
that make repetition cheap: rules are reduced once per distinct dispatch value, and `Validator`
instances are reused across items that share an effective rule set.

## Why the fast path is allowed to exist

The optimizations are only sound because of one rule the package holds to: **a value that fails
must fall through to Laravel.**

A fast-check closure is a simplification — `string|max:255` becomes
`is_string($v) && strlen($v) <= 255`, with no rule parsing, no method dispatch, no `BigNumber`
size comparison. That simplification is safe for deciding *pass*, but not for producing a
failure message. So a closure that returns false hands the value back to Laravel, which
produces the error exactly as it always would. There is no reimplemented message-formatting
layer to drift out of sync with the framework.

The same principle shapes batched database validation. `BatchDatabaseChecker` collapses N
per-item `exists`/`unique` queries into one `whereIn`, then serves the answers through
`PrecomputedPresenceVerifier`. The original `Exists`/`Unique` rule objects stay in place, so
custom messages and `:attribute` replacement keep working — only the query is replaced.

Rules that cannot be reduced this way — custom `Rule` objects, closures, `distinct`,
`exists`/`unique` with closure callbacks — go through Laravel untouched.

This is also what makes the parity tests (`tests/*ParityTest.php`) the load-bearing part of the
suite: each asserts the optimized path and the native path reach the same verdict for the same
input.

## Why wildcard expansion was rewritten

Laravel's `explodeWildcardRules()` flattens the payload with `Arr::dot()` and matches each
wildcard rule's regex against every resulting key. That is O(n²) in the size of the payload,
which is invisible on a login form and dominant on a thousand-row import.

`WildcardExpander` walks the data tree once and emits concrete paths as it descends. The result
is the same set of expanded attributes; only the cost changes. Measurements are in
[Performance](performance.md).

## Why conditionals are evaluated before validation

`exclude_if` / `exclude_unless` decide whether an attribute participates at all. Evaluating them
up front — in `PresenceConditionalReducer` and `ValueConditionalReducer` — lets the excluded
attributes be dropped from the rule set before the validator ever sees them. On a payload with
100 items and 47 conditional fields this takes the rule set from roughly 4,700 entries to 200,
so the saving compounds with every later stage.

## Source layout

```
src/
├── FluentRule.php            static factory — the public entry point
├── Rules/                    the eleven typed rule classes
│   └── Concerns/             the shared modifier / embedding / self-validation traits
├── RuleSet.php               the compiler and its public escape hatches
├── FastCheckCompiler.php     dispatcher over the per-family closure compilers
├── FastCheck/                one compiler per rule family
├── OptimizedValidator.php    wildcard fast-check validator subclass
├── MemoizingValidator.php    rule-parse memoizing validator subclass
├── BatchDatabaseChecker.php  collapses per-item exists/unique into one query
├── HasFluentRules.php        form-request trait; activates the optimized path
├── HasFluentValidation.php   Livewire equivalent
├── Internal/                 per-item loop, caches, reducers — not public API
└── Testing/                  FluentRulesTester, Pest expectations, arch helper
```

Everything under `Internal/` is marked `@internal`: it is reachable, but not covered by the
package's semver promise. The stable surface is `FluentRule`, the `Rules\*` classes, `RuleSet`,
the two traits, and `Testing\*`.

## Naming and host registries

The laranail convention requires every name a package registers into a host-owned registry to
carry both the vendor and the package slug, because those registries are flat maps: a second
package claiming the same key silently replaces the first.

On the Laravel side this package registers **nothing**. It ships no service provider, so there
is no config key, view namespace, translation namespace, Blade component prefix, middleware
alias, Artisan command, publish tag, or container binding to namespace. It is a library of
value objects plus two traits a host class opts into by `use`-ing it, and PHP's own namespacing
already keeps those unambiguous.

`Macroable` is carried by `FluentRule`, `RuleSet`, `FluentSchema` and each `Rules\*` class, but
the package defines no macros of its own — that registry belongs to the consuming application.

There is one registry it does write to. `resources/boost/skills/` ships four skills that
`boost sync` copies into the consuming application's `.claude/skills/<name>/`,
`.agents/skills/<name>/`, and equivalents. That directory is flat, host-owned, and keyed by the
bare skill name — the engine adds no vendor prefix of its own, which is why the shared catalog
occupies plain names like `code-review` and `readme`. Two packages shipping the same skill name
would not collide loudly; the second would overwrite the first.

So the four skills carry the vendor and slug themselves:

| Skill | Covers |
|---|---|
| `laranail-validation` | The `FluentRule` API reference |
| `laranail-validation-livewire` | `HasFluentValidation` in Livewire components |
| `laranail-validation-optimize` | Finding conversion opportunities in existing validation |
| `laranail-validation-migrate-messages` | Moving `messages(): array` to inline `message:` |

The practical consequence for the Laravel side: adding a service provider later is not a small
change. It is the point at which the rest of the convention starts to apply, and every name it
registers would need the `laranail-validation` prefix from its first release.

---

[← Docs index](../README.md#documentation)
