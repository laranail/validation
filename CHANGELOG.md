# Changelog

All notable changes to `laranail/validation` are documented in this file.

The format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/) and this project
adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

Entries below `Unreleased` are written by CI from the GitHub release body — see
[docs/release.md](docs/release.md). Do not hand-edit released sections.

## Unreleased

### Added

- Initial laranail release. Type-aware fluent rule builders (`FluentRule` and the twelve
  `Builder\Nodes\*` classes), structured array validation via `each()` and `children()`, labels
  and per-rule messages carried on the rule itself, the `RuleSet` compiler, the optimized
  wildcard validator behind `HasFluentRules`, the Livewire bridge, and the `FluentRulesTester` /
  Pest testing helpers.
- Four Laravel Boost skills shipped under `resources/boost/skills/`, namespaced
  `laranail-validation*`.
- An extended rule library under `Rules\`: 80 rules across AntiSpam, Banking, Codes, Colour,
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

[Unreleased]: https://github.com/laranail/validation/commits/main
