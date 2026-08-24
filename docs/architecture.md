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
| Schemas | `Schemas\*` | Compose a multi-field concern into a `RuleSet` |
| Compiler | `RuleSet` | Lower rule objects to native Laravel rules, and extract labels/messages |
| Execution | `HasFluentRules`, `OptimizedValidator`, `MemoizingValidator` | Run the compiled rules, fast-pathing where the result is provably identical |

### Builders

`FluentRule` is a static factory, not a class you hold. `FluentRule::string()` returns a
`Builder\Nodes\StringRule`, `FluentRule::date()` a `Builder\Nodes\DateRule`, and so on across
the seventeen node classes in `src/Builder/Nodes/`. The type-specific surface lives on the
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

### Schemas

A rule object answers for one value. Some concerns are not one value: a person's name is spread
across however many columns a schema chose, and "at least one of them is filled" is a property
of the set rather than of any member. `Schemas\*` is where those live.

A schema is a builder that returns a `RuleSet`, not a new kind of rule — so what comes out
composes with `merge()`, `only()`, `validate()` and the optimized form-request path like
anything else. `Schemas\PersonNameSchema` is the first, and the shape to copy: the field set is
the caller's, the defaults are the safe ones, and every option is a copy-on-write method so a
schema shared between two form requests cannot be mutated by one of them.

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

## Why the phone rules depend on `laranail/phone`

The phone rules are the only ones in this package that need an external body of knowledge. Whether
`+254712345678` is a real number is not something a pattern can answer — it depends on which ranges
Kenya has actually allocated, which changes, and which lives in Google's libphonenumber metadata.

So `laranail/phone` owns the parsing, the normalisation and the metadata, and this package owns the
rule. The dependency points that way round because a rule needs a parser and a parser does not need a
rule; inverting it would drag every validation consumer into a phone-number library.

It is a **`suggest`**, not a `require`. libphonenumber's metadata is a real weight — a per-region
metadata file load and several megabytes resident once warmed — and a project validating only strings
and dates should not carry it. Every entry point checks first and throws with a sentence naming the
package, rather than a class-not-found from three frames deeper.

### Why `unique()` is overridden there and nowhere else

`FluentRule::phone()->unique()` does not compile to Laravel's `Rule::unique()`. It compiles to
`Rules\Telecom\UniquePhone`, which normalises the value to E.164 and queries the canonical form.

The generic rule compares the attribute exactly as it arrived, and for phone numbers that is simply
wrong: a row holding `+254712123456` and a user typing `0712 123456` are different strings, so the
query finds nothing and a duplicate is created. Nothing in the table shows why.

It is a separate rule class rather than a change to the shared `unique()` because `unique` is used on
every other kind of column, where comparing the value as-typed is exactly the right behaviour. One
column type needing a different comparison is not a reason to make the general case surprising.

## Source layout

```
src/
├── FluentRule.php                static factory — the public entry point
├── Builder/Nodes/                the seventeen typed builder nodes
├── Builder/Concerns/             the shared modifier / embedding / self-validation traits
├── Rules/                        the extended rule library — Iban, Vin, PostalCode, …
├── Schemas/                      composed multi-field rule sets — PersonNameSchema
├── Contracts/                    FluentRuleContract, ClientCheckable, the email seams
├── RuleSet.php                   the compiler and its public escape hatches
├── FastCheckCompiler.php         dispatcher over the per-family closure compilers
├── FastCheck/                    one compiler per rule family
├── OptimizedValidator.php        wildcard fast-check validator subclass
├── MemoizingValidator.php        rule-parse memoizing validator subclass
├── BatchDatabaseChecker.php      collapses per-item exists/unique into one query
├── HasFluentRules.php            form-request trait; activates the optimized path
├── HasFluentValidation.php       Livewire equivalent
├── ValidationServiceProvider.php config, translations, opt-in string aliases
├── Internal/                     per-item loop, caches, reducers — not public API
└── Testing/                      FluentRulesTester, Pest expectations, arch helper
```

Everything under `Internal/` is marked `@internal`: it is reachable, but not covered by the
package's semver promise. The stable surface is `FluentRule`, the `Rules\*` classes, `RuleSet`,
the two traits, and `Testing\*`.

## Naming and host registries

The laranail convention requires every name a package registers into a host-owned registry to
carry both the vendor and the package slug, because those registries are flat maps: a second
package claiming the same key silently replaces the first.

The builders themselves register nothing. They are value objects plus two traits a host class
opts into by `use`-ing, and PHP's own namespacing already keeps those unambiguous.

`ValidationServiceProvider` is the part that reaches into the host, and every name it writes
carries the vendor and the slug:

| Registry | Registered name |
|---|---|
| Config key | `laranail.validation`, from `config/laranail-validation.php` |
| Translation namespace | `laranail-validation` |
| Publish tags | `laranail::validation-config`, `laranail::validation-translations` |
| Validator extensions | `laranail_<rule>` — opt-in, off by default |

The config key and the file name deliberately differ. `hasConfigFile()` derives one from the
other, which would yield `laranail.validation.laranail-validation`, so the merge is written out
by hand instead: the file has to be prefixed or `vendor:publish` clobbers an application's own
`config/validation.php`, and the key has to be flat to match the family convention.

The string rule aliases are the case worth reading twice. Laravel's validator extension map is
a flat, last-writer-wins registry, so a library claiming `iban`, `slug` or `username` unasked
would be precisely the collision this convention exists to prevent. They are therefore off by
default, prefixed when enabled, and the prefix is configurable so an application that already
owns a name can move ours rather than fight it.

The three container bindings — `Contracts\Email\DisposableDomainList`, `RoleAccountList` and
`DnsResolver` — are keyed by interface FQCN, which PHP already namespaces, and are bound with
`singletonIf` so `laranail/email` wins whichever provider registers first.

No view namespace, Blade component prefix, middleware alias or Artisan command is registered,
because the package ships none of those. `tests/NamingConventionTest.php` asserts this by
reading the live registries rather than the registration code.

`Macroable` is carried by `FluentRule`, `RuleSet`, `FluentSchema` and each `Rules\*` class, but
the package defines no macros of its own — that registry belongs to the consuming application.

One more registry sits outside Laravel entirely. `resources/boost/skills/` ships four skills that
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

## Not in this library — and why

The migration audit swept every feature of the legacy packages (`enekia` v1/v2). Most were
ported, usually improved; the rest were dropped or relocated **on purpose**, and this section
is the durable record of each decision so nobody re-litigates them one at a time.

- **`RemoteRule`** (a rule POSTing the value to a DB-configured URL) — dropped as designed.
  A validation rule that ships user input to a runtime-configurable endpoint is an SSRF and
  exfiltration hazard wearing a helpful name. The sanctioned shapes are a container-resolved
  invokable rule (your code, your endpoint) or the validation-js transport layer, which is
  security-reviewed before any dynamic endpoint ships.
- **SMTP deep-check verification** — `laranail/email`'s scope. `Networking\DeliverableEmail`
  answers the narrower MX question here; an SMTP conversation needs sending infrastructure a
  validation library must not own.
- **zxcvbn strength scoring / password history** — their own packages
  (`laranail/password-strength`, `laranail/password-history`), bridged into `password()` via
  guarded macros and listed under `suggest` only.
- **Disposable-phone lists** — `laranail/phone`'s scope, beside the phone metadata they
  describe.
- **The framework-free PHP tier** — enekia v1's vanilla validators are not restored. The
  successor is Laravel-coupled by design; the portable role belongs to the JS engine, which is
  framework-free from the ground up.
- **`is*()`/`assert*()` magic statics** — replaced by the typed fluent API and the explicit
  `Check::` statics, which give the same one-liner ergonomics with real signatures.
- **`Transfigure`'s ~55 type predicates** — a utility library that lived inside a validation
  package; neither ported nor replaced, because type predicates are not validation rules.
- **`Str` contains-all/contains-any rules** — deferred, not refused: core `contains` plus the
  string builder cover the common cases, and the dedicated rules land if real demand appears.
- **`MissingFromDB`** — expressible as `Rule::unique` directly; a wrapper would rename a core
  rule without changing it.
- **JAN and UPC-A** — documented onto `Ean` and `Gtin` rather than duplicated: each IS the
  other rule with a prefix, and a prefix-only class would restate a checksum to say less.

Two probes the plan recommended dropping were instead **redesigned and implemented** by owner
decision — `Networking\ImageUrl` (guarded, redirect-refusing, fail-closed) and
`Networking\HasGravatar` (https, sha256, fail-open, privacy cost documented). The enekia
v1-only rules all resolve to core or existing successors: `Equals` → `same`, `Coordinate` →
`Geo\LatLng`, `Timezone` → core `timezone` (identifiers) + `Chrono\TimezoneAbbreviation`
(abbreviations), `IsAStateInNorthAmerica` → `Geo\UsState` / `Geo\CaProvince`,
`IncludesHtml` → `Text\HtmlClean`'s `mustContainHtml:` flag.

---

[← Docs index](../README.md#documentation)
