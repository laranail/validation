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
| `Email\*` | — | Original. The wildcard-domain concept appeared in the abandoned in-house `enekia` codebase and in `ashallendesign/email-utilities`; no code was carried over from either. |
| bundled disposable-domain list | [disposable-email-domains](https://github.com/disposable-email-domains/disposable-email-domains) | Data only, dedicated to the public domain under **CC0 1.0**. Snapshot of 8,201 domains taken 2026-08-14. |
| bundled role-account list | RFC 2142, plus convention | Compiled here; RFC 2142 mandates several of the entries. |
| `Database\Authorized`, `Database\ModelsExist` | — | Original. The concepts appear in `spatie/laravel-validation-rules`; no code was carried over. `Authorized` differs from Laravel's own `Rule::can()` in resolving the value to a model before the Gate check. |
| `Structure\Delimited` | — | Original. The concept (per-item validation of a delimited string) appeared in the abandoned in-house `enekia` codebase and in `spatie/laravel-validation-rules`; no code was carried over from either. |
| `Crypto\BitcoinAddress` | Base58Check; BIP-173 (Bech32); BIP-350 (Bech32m) | Implemented from the BIPs. Checksums are verified, not pattern-matched. |
| `Crypto\EthereumAddress` | EIP-55 | Shape only — EIP-55 verification needs Keccak-256, which PHP core does not provide. Stated in the class docblock and asserted in a test. |
| `Postal\PostalCode` | Universal Postal Union member formats | Pattern table derived from the abandoned in-house `enekia` codebase, re-verified and corrected: three codes it carried (`KV`, `XY`, `ZU`) are not ISO 3166-1 alpha-2 — `KV` became `XK` for Kosovo, the other two were dropped — and its Canadian pattern made the final digit optional, accepting the five-character `K1A 0B`. No code was carried over. |
| `Geo\Latitude`, `Geo\Longitude`, `Geo\LatLng` | — | Original. Decimal-degree ranges are definitional, not proprietary. |
| `Geo\UsState` | USPS state abbreviations | Original. The code/name table is factual reference data. |
| `Geo\CaProvince` | Canada Post provincial abbreviations | Original. The code/name table is factual reference data. |
| `Text\Slug`, `Text\Username`, `Text\CaseStyle`, `Text\WithoutSpaces` | — | Original. Conventional formats with no governing standard. |
| `Text\PersonName` | — | Original. Unicode letter/mark classes rather than an ASCII assumption. |
| `Text\HtmlClean` | — | Original. A data-shape rule; explicitly not an XSS defence. |
| `Net\PublicIp`, `Net\PrivateIp` | RFC 1122, 1918, 4193, 6598, 6666, 5737, 2544, 3849 | Range tables are factual reference data, taken from the RFCs and the IANA special-purpose address registries. |
| `Geo\Latitude`, `Geo\Longitude`, `Geo\LatLng` (via `Geo\Coordinate`) | — | Original. Decimal-degree bounds are arithmetic, not anyone's table. |
| `Geo\UsState`, `Geo\CaProvince` (via `Geo\Subdivisions`) | USPS and Canada Post abbreviations | Original. The shared lookup helper carries the same factual reference data as the rules above. |
| `Telecom\Phone`, `Telecom\UniquePhone` | Google's libphonenumber numbering-plan metadata, reached through `laranail/phone` | The rules are original; the numbering-plan data is **not** bundled here. `laranail/phone` wraps `giggsey/libphonenumber-for-php-lite` (Apache-2.0), which carries Google's metadata (Apache-2.0). Suggested rather than required, so a project that does not validate phone numbers never installs it. |
| `Numbers\Parity`, `Numbers\MonetaryAmount` | — | Original. Arithmetic and formatting, no governing standard. |
| `Colour\CssColor` | CSS Color Module Level 4 | Implemented from the specification. The 148 named colours are factual reference data reproduced from it. |
| `AntiSpam\Honeypot`, `AntiSpam\SubmissionTiming` | — | Original. The timestamp is encrypted with Laravel's own encrypter. |
| `Vendor\VendorIdentifier` | Each vendor's published identifier format | Original. Formats are documented facts; no vendor code or data is used. |
| `Markup\Xml` | W3C XML 1.0 and XML Schema | Uses PHP's libxml. External entity expansion is disabled. |
| `Network\DeliverableEmail` | RFC 5321 §5.1 (address-record fallback) | Original. Performs one DNS lookup through an injected resolver; the bundled resolver uses PHP's own `checkdnsrr`. No third-party service or data. |
| `Fiscal\NationalIdentifier` | Dutch 11-proef; Brazilian CPF mod-11; French NIR mod-97 (INSEE); US SSA unissued ranges; UK NINO reserved prefixes | Implemented from the published algorithms and verified against published or derived vectors. No national register data is bundled, and none of these can confirm a number was issued. |
| `Profanity\NoProfanity` | — | Original matching only. **No word list ships** — LGPL sources cannot be used in an MIT package, and the circulating lists record no licence. The application supplies the terms via `Contracts\TermList`. |


## Datasets added with the 1.0 rule families (Phase 2)

| Dataset | Source | Licence / status |
|---|---|---|
| ISO 3166-1 country codes (`resources/data/`) | ISO 3166 via the pinned registry snapshot in `tools/data-sources/iso-3166.txt`, rebuilt by `tools/build-datasets.php` (a suite test pins generator output to the committed data) | Factual reference data; codes themselves are not copyrightable |
| ISO 4217 currency codes | ISO 4217 list-one XML snapshot in `tools/data-sources/` | Factual reference data |
| ISO 639 language codes | ISO 639-2 registry snapshot in `tools/data-sources/` | Factual reference data |
| IANA timezone identifiers | PHP's own `DateTimeZone::listIdentifiers()` (tzdata) — no bundled copy | tzdata is public domain |
| Card-brand catalogue (`Support/Payment/CardBrand.php`, `BundledCardBrandCatalogue`) | IIN ranges, lengths and CVC rules compiled from the brands' published numbering plans (ISO/IEC 7812 assignments); written as data, not scraped from any library | Factual reference data; per-range provenance in the class docblocks |
| VAT number formats and checksums (`Rules/Fiscal/VatNumber`) | Per-country formats from the EU Commission's published VIES structure notes; NL/BE/DE/IT/SE/EL/LU/FR check-digit algorithms implemented from each authority's published specification | Implemented from specifications; no code carried over |
| Reserved-username lists (`Support/Text/*ReservedUsernameList`) | Compiled from RFC 2142 mailbox names plus commonly reserved route/subdomain terms; original compilation | Original work, MIT with the package |

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
