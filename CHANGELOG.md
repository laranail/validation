# Changelog

All notable changes to `laranail/validation` are documented in this file.

The format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/) and this project
adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

Entries below `Unreleased` are written by CI from the GitHub release body — see
[docs/release.md](docs/release.md). Do not hand-edit released sections.

> **One published release: `v0.1.0`.** The org floors every package at `v0.1.0` while pre-stable, so
> that tag is the only one consumers resolve and it moves as the package moves. Sections under
> *Internal history* below were cut as tags during development and later withdrawn; their content is
> part of `v0.1.0`. They are kept for provenance, not because those versions are installable.

## Unreleased

_Nothing yet._

## v0.1.0 - 2026-08-27

Two breaking changes that landed on `main` during org-wide sweeps and were never written down.
Both are mechanical; the provider rename is codemod-able.

### Changed

- **Breaking. The translation namespace is now the composer package name**, `laranail/validation::`,
  where it was `laranail-validation::`.

  There is no alias. `hasTranslations()` is called without an argument, so the old namespace is not
  registered at all and every key spelled the old way returns *itself* instead of a message — no
  exception, no warning, just the raw key rendered wherever a validation error would have been. An
  application that never names these keys directly is unaffected; one that overrode a message, or
  published the translations, is not.

  Published files follow the namespace, so an override now lives at
  `lang/vendor/laranail/validation/`. That nesting is where Laravel reads it back from —
  `FileLoader::loadNamespaceOverrides()` interpolates the namespace into
  `{$path}/vendor/{$namespace}/{$locale}/{$group}.php`.

- **Breaking. The service provider moved** to
  `Simtabi\Laranail\Validation\Providers\ValidationServiceProvider`.

  Package auto-discovery handles this on its own. Anything that names the class explicitly — a
  Testbench `getPackageProviders()`, a manual entry in `config/app.php`, a `testbench.yaml` — fatals
  with "class not found" until it is updated. `rector-migrate-0.1.php` rewrites it.

  The move brings the package in line with the family convention that every provider sits in a
  `Providers/` directory, which Laravel's own skeleton does with `app/Providers`.

- The final re-audit against the 1.0 plan closed four release-gate gaps: the README
  Stability section and installation guide now speak in shipped-1.0 terms (both still said
  "pre-1.0, pin `^0.1`" after the major); `CREDITS.md` records the Phase-2 datasets (ISO
  code tables and their pinned registry snapshots, the card-brand catalogue, the VAT
  format/checksum sources, the reserved-username lists); and `release.yml` generates and
  attaches a CycloneDX SBOM via `laranail::package-tools.sbom`, the §12.3 item the release
  had shipped without.

- Package paths resolve through `package-tools`' `packagePath()` rather than hand-counted
  `__DIR__ . '/../..'` strings, which cannot drift when a file moves.

## Internal history (not published)

These were tagged during development and the tags have since been withdrawn. Nothing here is
separately installable — it all ships inside `v0.1.0` above.

## v1.0.1 - 2026-08-24

### Added

- The password sister packages are cross-linked: `laranail/password-tools`
  (`password()->strength()`) and `laranail/password-history` (`password()->notReused()`)
  join `suggest`, and the `password()` reference documents both — resolving the two
  "not in this library" rows the 1.0 plan recorded. The coupling is entirely theirs:
  this package carries no `class_exists` checks and no optional imports.

### Changed

- GitHub Actions are pinned to commit SHAs across every workflow (org supply-chain rule);
  `release-benchmark.yml` gains a `workflow_dispatch` so the benchmark table can be injected
  into tag-driven releases, whose workflow-token events fire no release-event workflows.

## v1.0.0 - 2026-08-24

The 1.0 major: the package graduates to real SemVer, the README's stability contract becomes
binding (stable surfaces break only in a major; deprecations live at least one minor), and the
`rector-migrate-1.0.php` set auto-migrates the one mechanical break. See `UPGRADING.md`.

### Fixed

- `CachedDnsResolver` crashed mid-validation when the cache backend resolved but could not
  answer — a database store with no migrated cache table being the canonical case. The store
  erroring now falls back to a direct lookup, the same contract as having no cache at all: an
  optimization's infrastructure failure costs speed, never a verdict.

### Added

- Initial laranail release. Type-aware fluent rule builders (`FluentRule` and the twelve
  `Builder\Nodes\*` classes), structured array validation via `each()` and `children()`, labels
  and per-rule messages carried on the rule itself, the `RuleSet` compiler, the optimized
  wildcard validator behind `HasFluentRules`, the Livewire bridge, and the `FluentRulesTester` /
  Pest testing helpers.
- Four Laravel Boost skills shipped under `resources/boost/skills/`, namespaced
  `laranail-validation*`.
- An extended rule library under `Rules\`: 84 rules across AntiSpam, Banking, Codes, Colour,
  Crypto, Database, Email, Fiscal, Geo, I18n, Identifiers, Markup, Net, Network, Numbers, Postal,
  Profanity, Structure, Telecom, Text and Vendor. Each is a plain `ValidationRule`
  usable on its own, in a rule array, or through the builder's `rule()` escape hatch. See
  [the reference](docs/tools/rule-library.md).
- `Network\DeliverableEmail`, the first Network-tier rule: a cached MX lookup behind the
  `DnsResolver` contract, skipped entirely during a precognitive request, and passing rather
  than failing when the lookup itself is unreachable. The bundled `Actions\CachedDnsResolver`
  is bound only if nothing else has bound the contract.
- Rule tiers — Pure, Database and Network — enforced by arch tests rather than convention:
  nothing under `Rules\` may write, database reads are confined to `Rules\Database\`, and a
  Network rule must implement `PrecognitionSkippable`.
- `ValidationServiceProvider`, a publishable `config/laranail-validation.php`, and English
  translations under the `laranail-validation::` namespace.
- Opt-in string rule aliases (`laranail_iban`, `laranail_postal_code:US`), off by default and
  vendor-prefixed. Registered through `Validator::extend` so rule parameters actually reach the
  rule — package-tools' `hasValidationRule()` constructs it with none.
- `notDisposable()`, `notRole()`, `domainIs()` and `domainIsNot()` on the email builder node,
  backed by contracts that `laranail/email` supplies maintained implementations for; a bundled
  disposable-domain and role-account snapshot serves as the fallback when it is not installed.
- `Contracts\ClientCheckable`, letting a rule advertise browser-equivalent NATIVE LARAVEL
  RULES for `laranail/validation-js` to export. It returns a LIST, which is what makes a rule
  like `Geo\Latitude` expressible: its browser form is `numeric` AND `between:-90,90`, not a
  regex contorted into a numeric range. Implemented by `Text\Slug`, `Text\WithoutSpaces`,
  `Identifiers\SemVer`, `Net\Subdomain`, `Crypto\EthereumAddress`, `Text\CaseStyle`,
  `Text\Username`, `Text\PersonName`, `Numbers\MonetaryAmount`, `Vendor\VendorIdentifier`,
  `Postal\PostalCode`, `Banking\BsbNumber`, `Identifiers\HashDigest`,
  `Geo\Latitude`, `Geo\Longitude` and `Colour\CssColor` — the rules whose entire check is a pattern, which they return so there is no second
  implementation to drift. `PostalCode` sends only the named countries' patterns and nothing at
  all when the country comes from a sibling field.
  Deliberately NOT implemented by any rule performing a checksum, a query or IO: advertising a
  shape-only pattern for an IBAN would pass a mistyped account number in the browser and fail
  it on the server, which is exactly what client-side validation exists to prevent.
- `UPGRADING.md` and `CREDITS.md`.

### Changed

- **Breaking.** PHP floor is now `^8.4.1 || ^8.5` and Laravel `^13.0` only, adopting the
  laranail foundation line. PHP 8.3 and Laravel 12 are no longer supported.
- **Breaking.** The twelve builder nodes moved from `Rules\*Rule` to `Builder\Nodes\*Rule`, and
  their shared traits from `Rules\Concerns\` to `Builder\Concerns\`, freeing `Rules\` for the
  rule library. `FluentRule`, `FluentSchema`, `RuleSet` and `Contracts\FluentRuleContract` — the
  documented surface — did not move. See `UPGRADING.md` for the mapping.

### Fixed

Divergences from Laravel's own validator, found by differential testing against it. Each can
change whether input an application previously accepted still passes, so read `UPGRADING.md`
before upgrading.

- `EmailRule` pipe-joined its rules unconditionally, so any rule containing a literal `|` — a
  `regex:` in particular — was split into fragments and threw `BadMethodCallException` at
  validation time, and `exists()`/`unique()` closure constraints were silently dropped, letting
  those rules match rows the closure was written to exclude.
- The fast-check phase accepted `'tomorrow'`, `'now'` and `'2024-02-31'` as `date`; accepted
  `file://` and `mailto:` as `url`; split `in:`/`not_in:` on a bare comma rather than as CSV;
  and truncated a fractional `min:` toward zero.
- Values were read through `Arr::dot()`, which emits no key for a non-empty array node, so an
  array arrived as `null`, a `nullable` chain reported "satisfied", and the rule was dropped —
  an array silently passed a `string` rule.
- The batched presence check compared the database's own matches against submitted values as
  exact PHP array keys. On a case-insensitive collation — MySQL's default among them — a
  `unique` rule reported "not taken" for a value the database considered taken.
- Batched groups were keyed on table and column alone and assigned rather than merged, so a
  second field checking the same column replaced the first and left it unchecked.
- `exclude_unless` did not convert its `true`/`false` parameters for a dependent declared
  `boolean`, so it failed to match a dependent of `1` and excluded a field Laravel keeps.
- Removing a satisfied attribute for the fast-check phase hid it from Laravel's own
  `shouldConvertToBoolean()`, so `required_if:items.*.notify,true` stopped matching a `notify`
  of `1` and left the field unenforced.
- A per-item validator reused across array items carried its `$excludeAttributes` forward, so
  once one item excluded a field, every later item skipped it too.

## v0.1.1 - 2026-08-24

A security and correctness patch: the optimized pipeline's verdict is now byte-identical to a
vanilla Laravel validator, guarded by a new end-to-end parity harness that runs every cell
through both.

The headline fix is security-graded: a PCRE error — the ReDoS shape, where a catastrophic
pattern aborts mid-match — made the regex fast path treat "not zero" as a match, so a regex
deny-list accepted input Laravel rejects. The fast path now keeps only a definite match and
fails closed on errors, exactly as Laravel does. Alongside it: `required` rejects
whitespace-only strings again, `date_equals` compares full timestamps, `in`/`not_in` never
out-decide the installed Laravel (the framework itself changed loose→strict comparison inside
13.x — the optimizer now defers every boundary case to whichever version is installed), and the
edit-form idiom of `exists` + `unique->ignore()` on one field batches correctly instead of
answering both rules from one shared lookup.

Every anchored pattern in the rule library now carries the `D` modifier, closing the trailing
newline bypass: `"admin\n"` no longer slips past `Username`'s reserved list or a unique index
holding `"admin"`, and the same applies across `Slug`, `Jwt`, the banking and code checksums,
and the postal patterns. A configured URL port allow-list now resolves an omitted port to the
scheme default instead of rejecting effectively every real URL.

Verdict changes are breaking by design — input the optimizer wrongly accepted now fails as it
always did in vanilla Laravel. Every one is documented with before/after guidance in
`UPGRADING.md`. This release also carries the URL/IP/MAC/username builder split and
`PersonNameSchema` from the feature branch; those API changes are in `UPGRADING.md` too.

The hygiene pass rides along: a canary for the package's one reflection into Laravel internals,
dedicated service-provider tests, dead version-gate skips deleted, prose counts pinned to the
source by tests, the whole Telecom family documented in the rule reference, a bindable
`InlineTermList` with a profanity recipe, and an ext-intl assertion in CI.


---

<!-- benchmark-start -->
### Benchmark results

| Scenario | Optimizations | Native Laravel | Optimized | Speedup |
|----------|---------------|---------------:|----------:|--------:|
| Product import — 500 items, simple rules | Wildcard, fast-check | 192.2ms | 3.5ms | **~54x** |
| Nested order lines — 1000 orders × 5 line items | Wildcard, fast-check (nested) | 1312.2ms | 20.0ms | **~66x** |
| Event scheduling — 100 items, field-ref dates | Wildcard, partial fast-check | 33.5ms | 1.4ms | **~25x** |
| Article submission — 50 items, custom Rule objects | Wildcard only | 10.8ms | 3.3ms | **~3x** |
| Conditional import — 100 items, 47 conditional fields | Wildcard, pre-evaluation | 4200.0ms | 55.0ms | **~76x** |
| Login form — 3 fields, no wildcards | Fast-check (flat) | 0.2ms | 0.0ms | **~18x** |
<!-- benchmark-end -->

[Unreleased]: https://github.com/laranail/validation/commits/main
