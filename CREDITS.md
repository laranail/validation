# Credits

## Original author

The fluent builder, the `RuleSet` compiler and the optimization engine were
written by **[Sander Muller](https://github.com/sandermuller)** as
[`sandermuller/laravel-fluent-validation`](https://github.com/SanderMuller/laravel-fluent-validation)
(MIT). That copyright is retained in [LICENSE](LICENSE) alongside Simtabi LLC's.

## Rule provenance

Every rule in the extended library records where its algorithm came from. The
distinction that matters is **specification vs implementation**: a checksum
defined by an ISO standard is not anyone's copyrighted work, but somebody's
*code* for it is.

| Family | Source of the algorithm | Notes |
|---|---|---|
| `Banking\Luhn` | ISO/IEC 7812-1 | Implemented from the standard. |
| `Banking\Iban` | ISO 13616, ISO 7064 MOD-97-10; length table from the SWIFT IBAN Registry | Implemented from the standards. The length table is factual reference data. |
| `Banking\Bic` | ISO 9362 | Implemented from the standard. |
| `Banking\Isin` | ISO 6166 | Implemented from the standard, composing `Luhn`. |
| `Codes\Gtin` | GS1 General Specifications | Implemented from the standard; one mod-10 routine serves GTIN-8/12/13/14. |
| `Codes\Ean` | GS1 General Specifications | The retail subset of `Gtin`, delegating to it. |
| `Codes\Isbn` | ISO 2108 | Implemented from the standard. ISBN-10 is mod-11; ISBN-13 is a GTIN-13 with a 978/979 prefix. |
| `Codes\Issn` | ISO 3297 | Implemented from the standard. |
| `Identifiers\Imei` | 3GPP TS 23.003 | Implemented from the standard, composing `Luhn`. |
| `Identifiers\Vin` | ISO 3779; check digit per the US NHTSA rule | Implemented from the standards. The transliteration table is factual reference data. |
| `Identifiers\SemVer` | [semver.org](https://semver.org) 2.0.0 | The officially published pattern, unmodified; timed against pathological input in the suite. |
| `Identifiers\Jwt` | RFC 7515 §3.1 | Implemented from the RFC. Validates form only, never trust. |
| `Net\DomainName` | RFC 1035, RFC 5891 | Implemented from the RFCs. The A-label vs NR-LDH-label split follows the approach in the abandoned in-house `enekia` codebase; no code was carried over. |
| `Net\Subdomain` | RFC 1035 | Implemented from the RFC. |
| `Net\Cidr` | RFC 4632 | Implemented from the RFC. |
| `Net\PublicIp`, `Net\PrivateIp` | RFC 1122, 1918, 4193, 6598, 6666, 5737, 2544, 3849 | Range tables are factual reference data, taken from the RFCs and the IANA special-purpose address registries. |

## Packages studied but not depended on

- **[`intervention/validation`](https://github.com/Intervention/validation)**
  (MIT) — the richest single source of extra Laravel rules, and the obvious
  candidate to wrap. We do not, because its service provider auto-registers all
  42 of its rules as **bare** string aliases (`iban`, `slug`, `username`, …) and
  claims the generic `validation::` translation namespace. Laravel resolves
  `extra.laravel.dont-discover` from the *application's* `composer.json` only —
  `PackageManifest` is constructed with the app's base path — so a library
  cannot suppress a dependency's discovery on its consumers' behalf. Requiring
  it would inflict those aliases on every consuming application with no opt-out,
  which is exactly the collision the naming convention exists to prevent. Rules
  are therefore implemented here instead.

- **[`proengsoft/laravel-jsvalidation`](https://github.com/proengsoft/laravel-jsvalidation)**
  (MIT) — prior art for the planned JSON rule-export contract, in particular its
  positional-to-named parameter mapping and its "unknown rule defaults to a
  server round-trip" policy.

- **[`square/laravel-hyrule`](https://github.com/square/laravel-hyrule)**
  (Apache-2.0) and
  **[`IndexZer0/laravel-validation-provider`](https://github.com/IndexZer0/laravel-validation-provider)**
  (MIT) — architectural references for the typed node tree and the composable
  rule-set providers respectively. Neither can be depended on: they are capped
  at Laravel 10 and 11.

No code from any of the above is copied into this package. Where a future rule
does adapt third-party code, its licence and attribution are recorded in this
file and in the rule's docblock before the rule is merged.
