# Rule library

Forty-nine validation rules for the formats Laravel does not ship, grouped by family.

Every rule is a plain `Illuminate\Contracts\Validation\ValidationRule`. There is nothing to
register and nothing to configure — construct one and put it in a rule array:

```php
use Simtabi\Laranail\Validation\Rules\Banking\Iban;

$request->validate(['account' => ['required', new Iban()]]);
```

They compose with the fluent builder through `rule()`, which keeps the rule object intact
rather than stringifying it:

```php
FluentRule::string()->required()->rule(new Iban());
```

The email rules also have named methods on the email node — see [Fluent rule](fluent-rule.md).

## Reading this page

| Column | Meaning |
|---|---|
| **Rule** | The class, under `Simtabi\Laranail\Validation\Rules\{Family}\` |
| **Parameters** | Constructor arguments, with their defaults |
| **Alias** | The [opt-in string form](../configuration.md#string-rule-aliases-are-opt-in), behind the `laranail_` prefix. Off by default |
| **Message key** | Under `laranail-validation::validation.`, overridable per application |

Every rule here is **Pure tier** except the two in `Database`, which perform one indexed read
each, and the one in `Network`, which performs a cached DNS lookup. No rule in this library
writes. See [Architecture](../architecture.md) for what the tiers guarantee and the arch tests
that enforce them.

## Anti-spam

Cheap first filters. Neither replaces a CAPTCHA — `laranail/captcha` is that.

| Rule | Parameters | Alias | Message key |
|---|---|---|---|
| `Honeypot` | — | `honeypot` | `honeypot` |
| `SubmissionTiming` | `int $minimumSeconds = 3, int $maximumSeconds = 7200` | *(none)* | `submission_timing.*` |

- **`Honeypot`** — a decoy field that must arrive empty. Hide it with CSS, not `type="hidden"`,
  which many bots skip. Whitespace counts as empty, because a browser autofill leaves some and
  rejecting a real person for that is the expensive error. `"0"` counts as **filled** — the
  value a lazy bot posts, and the one `empty()` gets wrong.

  > Hide it accessibly: a screen reader follows the accessibility tree, not the visual one, so
  > the field needs `aria-hidden="true"` and `tabindex="-1"` as well. Without those a
  > screen-reader user fills it in and is treated as a bot.

- **`SubmissionTiming`** — a form submitted in milliseconds was not filled in by a person.
  Render `SubmissionTiming::token()` into a hidden field and validate it back. The timestamp is
  **encrypted, not plain**: a plain one is attacker-supplied, so a bot posts whatever passes and
  the check becomes decoration. It has no string alias on purpose — the two bounds are a
  security setting, and a rule string invites them to be tuned in a view until one is `0`.

  It does not prevent replay *within* the window; that needs a nonce the application records as
  spent.

## Banking

| Rule | Parameters | Alias | Message key |
|---|---|---|---|
| `Iban` | — | `iban` | `iban` |
| `Bic` | — | `bic` | `bic` |
| `Isin` | — | `isin` | `isin` |
| `Luhn` | — | `luhn` | `luhn` |
| `BsbNumber` | — | `bsb_number` | `bsb_number` |

- **`Iban`** — an International Bank Account Number (ISO 13616). Checks the country's declared
  length before the ISO 7064 MOD-97-10 checksum, so a truncated number fails as a length error
  rather than as a checksum coincidence.
- **`Bic`** — a BIC / SWIFT business identifier code (ISO 9362), 8 or 11 characters.
- **`Isin`** — an International Securities Identification Number (ISO 6166): two-letter country
  prefix, nine alphanumerics, Luhn check digit over the letter-expanded string.
- **`Luhn`** — the bare Luhn mod-10 checksum (ISO/IEC 7812-1), for card numbers and anything
  else carrying one. It checks the checksum only; it does not identify a card brand.
- **`BsbNumber`** — an Australian Bank State Branch number, `062-000` or `062000`. Format-only
  and honestly so: BSBs carry no check digit, and the live-prefix register churns as banks
  merge, so existence is the payment network's question.

## Codes

| Rule | Parameters | Alias | Message key |
|---|---|---|---|
| `Gtin` | `array $lengths = [8, 12, 13, 14]` | `gtin` | `gtin` |
| `Ean` | — | `ean` | `ean` |
| `Isbn` | `array $editions = [10, 13]` | `isbn` | `isbn` |
| `Issn` | — | `issn` | `issn` |
| `Asin` | — | `asin` | `asin` |
| `Ismn` | — | `ismn` | `ismn` |
| `UpcE` | — | `upc_e` | `upc_e` |

- **`Gtin`** — a GS1 Global Trade Item Number. Pass `$lengths` to accept only some of GTIN-8,
  -12, -13 and -14: `new Gtin([13])`, or `laranail_gtin:13`. A UPC-A is exactly a GTIN-12 — use
  this rule for it.
- **`Ean`** — a European Article Number, the retail barcode, in its 8 or 13 digit form. A JAN
  (Japanese Article Number) is an EAN-13 with GS1 prefix 45/49 — validate it with this rule.
- **`Isbn`** — an International Standard Book Number (ISO 2108). Accepts both editions by
  default; `new Isbn([13])` restricts to ISBN-13. ISBN-10's check digit uses mod-11 with `X` as
  the value 10, which is why an `X` in the final position is valid and nowhere else.
- **`Issn`** — an International Standard Serial Number (ISO 3297), same `X` escape.
- **`Asin`** — an Amazon Standard Identification Number: `B` + nine uppercase alphanumerics, or
  a checksum-valid ISBN-10 (book ASINs are the book's ISBN). Amazon publishes no checksum for
  the `B` form, so only its shape can be verified.
- **`Ismn`** — an International Standard Music Number (ISO 10957), the current 13-digit `9790…`
  form with its GTIN-13 check digit; hyphens and spaces in the printed form are ignored.
- **`UpcE`** — the 8-digit zero-suppressed UPC. The check digit is verified against the
  **expanded** UPC-A, which is the part quick implementations get wrong.

## Colour

| Rule | Parameters | Alias | Message key |
|---|---|---|---|
| `CssColor` | `array\|string $notations = []` | `css_color` | `css_color` |

**`CssColor`** accepts a colour in any notation CSS parses — `#fff`, `#ffff`, `#ffffff`,
`#ffffffff`, `rgb()`/`rgba()`, `hsl()`/`hsla()`, and the 148 CSS Color 4 named colours plus
`transparent` and `currentColor`. Narrow it by naming notations: `new CssColor([CssColor::HEX])`,
or `laranail_css_color:hex,rgb`.

One parameterised rule rather than the five near-identical classes the plan listed. A field
almost always means "a colour", and `rgb()` and `rgba()` are the same function in CSS Color 4 —
splitting them encodes a distinction the spec removed.

Two deliberate limits:

- **Component ranges are not enforced.** `rgb(300, 0, 0)` renders as red in every browser, so
  rejecting it would make the rule stricter than the thing it validates for.
- **`hsv` is off unless named.** No browser parses `hsv()`. It is supported because colour
  pickers emit it, not because it is CSS.

Hex alone overlaps Laravel's native `hex_color` — use the native rule if that is all you need.

## Numbers

| Rule | Parameters | Alias | Message key |
|---|---|---|---|
| `Parity` | `string $parity` | `parity` | `parity.even`, `parity.odd` |
| `MonetaryAmount` | `int $decimals = 2, bool $allowNegative = false` | `monetary_amount` | `monetary_amount` |

- **`Parity`** — `Parity::EVEN` or `Parity::ODD`. Only integers have parity, so `2.5` is
  rejected rather than truncated; numeric strings are accepted because form input arrives as
  `"4"`. Negatives work: PHP's `%` keeps the sign of the dividend, so `-3 % 2` is `-1`, and the
  naive `=== 1` check calls every negative odd number even.
- **`MonetaryAmount`** — an amount in plain decimal form. Distinct from `numeric|decimal:0,2`,
  which accepts `1e3`, `0x1A` and `INF` — all numeric to PHP, none of them a price anyone typed.
  The decimal count is a parameter because currencies differ: JPY has none, KWD has three. The
  value is never rewritten; a rule that turned `1,234.50` into `1234.50` would leave the
  application storing the original.

## Crypto

| Rule | Parameters | Alias | Message key |
|---|---|---|---|
| `BitcoinAddress` | `bool $testnet = false` | `bitcoin_address` | `bitcoin_address` |
| `EthereumAddress` | — | `ethereum_address` | `ethereum_address` |

- **`BitcoinAddress`** — checksum-verified rather than pattern-matched: Base58Check
  (double-SHA256) for P2PKH and P2SH, Bech32 and Bech32m (BIP-173 / BIP-350) for SegWit. A
  regex alone accepts a mistyped address, which is the failure that loses money. Pass
  `$testnet` for testnet prefixes.
- **`EthereumAddress`** — `0x` followed by 40 hexadecimal characters. **Shape only.** EIP-55
  mixed-case checksum verification needs Keccak-256, which PHP core does not provide, and this
  package will not add a dependency for it. An all-lowercase or all-uppercase address is
  indistinguishable from a checksummed one here.

## Database

The only two rules that touch the database. One indexed read each, no writes.

| Rule | Parameters | Alias | Message key |
|---|---|---|---|
| `Authorized` | `string $ability, string $model, ?string $guard = null, array $arguments = []` | `authorized` | `authorized` |
| `ModelsExist` | `string $model, ?string $column = null` | `models_exist` | `models_exist.array`, `models_exist.missing` |

- **`Authorized`** — resolves the submitted identifier to a model, then asks the Gate. This is
  the difference from the native `Rule::can()`, which hands the policy the raw submitted value:
  a policy declaring `Post $post` must receive a `Post`, not the string `"1"`. A missing record
  and a denied one produce the **same** message, deliberately — distinguishing them turns the
  field into an oracle for which ids exist.
- **`ModelsExist`** — every value in a submitted array names an existing record, in one
  `whereIn` rather than one query per item. It names the missing values in the message, because
  "one of these does not exist" is not actionable when fifty were submitted. Duplicates in the
  input are counted once: a repeated selection is a UI artefact, not a missing record.
  `$column` defaults to the model's route key.

## Email

Domain and mailbox rules. Deliverability needs DNS, so it lives in the Network tier below.

| Rule | Parameters | Alias | Message key |
|---|---|---|---|
| `EmailDomainIs` | `array $domains` | `email_domain_is` | `email.domain_is`, `email.malformed` |
| `EmailDomainIsNot` | `array $domains` | `email_domain_is_not` | `email.domain_is_not`, `email.malformed` |
| `NotDisposableEmail` | `?DisposableDomainList $domains = null` | `not_disposable_email` | `email.disposable`, `email.malformed` |
| `NotRoleEmail` | `?RoleAccountList $localParts = null` | `not_role_email` | `email.role`, `email.malformed` |

- **`EmailDomainIs` / `EmailDomainIsNot`** — exact domains, or `*.example.com` for subdomains.
  The wildcard matches one or more subdomain labels and does **not** match the bare domain:
  `*.corp.example.com` that silently admitted the parent would be a quiet privilege widening.
  List both when both are wanted. Matching is on the domain after the last `@`, so a quoted
  local part like `"a@b"@example.com` still resolves correctly, and the two rules share one
  matcher so a pattern cannot mean different things to an allow-list and a deny-list.
- **`NotDisposableEmail`** — rejects throwaway-mailbox providers. The list comes from the
  container; a bundled CC0 snapshot of 8,201 domains is the fallback.
  [`laranail/email`](https://github.com/laranail/email) replaces it with a refreshable one, and
  no call site changes.
- **`NotRoleEmail`** — rejects shared mailboxes (`info@`, `sales@`, `postmaster@`). Strips a
  plus tag first, since `info+signup@` is still the `info` mailbox.
- **`NoSubaddressing`** — rejects `user+tag@example.com`. Off by default and it should stay off
  nearly everywhere: subaddressing is a legitimate feature people use to filter their own mail. It
  earns its place on the one kind of field that grants something per account, where one mailbox
  minting unlimited distinct addresses is how a single person takes a free trial repeatedly.

## Encoding

| Rule | Parameters | Alias | Message key |
|---|---|---|---|
| `Base64` | — | `base64` | `base64` |
| `Base64Image` | `array $mimes = ['jpeg','png','gif','webp','bmp'], ?int $maxBytes = null` | *(none)* | `base64_image` / `base64_image_size` |
| `DataUri` | `array $mediaTypes = []` | *(none)* | `data_uri` |

- **`Base64`** — canonical base64, verified by strict decode + re-encode round trip. The round
  trip catches what a charset regex cannot: missing padding, embedded whitespace, and padding
  with non-zero discarded bits (`aGVsbG9=` decodes, but nothing encodes *to* it).
- **`Base64Image`** — the payload a JavaScript cropper or `canvas.toDataURL()` posts, bare or
  as a full data URI. Validates the **decoded bytes**: canonical base64, then the MIME type
  sniffed from content (never from the attacker-written data-URI label). `$maxBytes` caps the
  decoded size, and its message states the limit in human units ("2 MB"). To run Laravel's own
  file rules (`dimensions`, `File::image()`) on the same payload, bridge it with
  `Support\Encoding\Base64File::toUploadedFile()` — that writes a temp file, which is why it
  lives outside `Rules\`.
- **`DataUri`** — an RFC 2397 data URI. The payload is validated for what the header claims:
  strict base64 when flagged, URL-encoded text otherwise. `$mediaTypes` restricts the declared
  type, exact or by family (`image/*`); a restriction requires a *declared* type, because the
  RFC's implied `text/plain` default is what omitting the header gets for free. It checks the
  declaration, not the content — use `Base64Image` when the bytes are the question.

## Fiscal

| Rule | Parameters | Alias | Message key |
|---|---|---|---|
| `NationalIdentifier` | `string $country` | `national_identifier` | `national_identifier` |

**`NationalIdentifier`** validates a national identification number in a particular country's
scheme. One rule parameterised by country, because the field always means "this person's
national id" and which scheme applies is a property of the country.

| Constant | Scheme | Checked by |
|---|---|---|
| `NL` | burgerservicenummer | the 11-proef |
| `BR` | Cadastro de Pessoas Físicas | two mod-11 check digits |
| `FR` | NIR / numéro de sécurité sociale | mod-97 key |
| `US` | Social Security Number | format and unissued ranges — **no checksum exists** |
| `GB` | National Insurance number | format and reserved prefixes — **no checksum exists** |

Where a scheme has a checksum it is computed, not pattern-matched: the entire value of these
numbers is that a transposed pair fails arithmetic instead of sailing through a regex. Where a
scheme has none, that is stated rather than faked.

Two details these get wrong when written quickly:

- The Dutch 11-proef weights the **final** digit `-1`. A plain weighted sum accepts a different
  last digit, which is the case the check exists for.
- A French NIR from Corsica writes its department as `2A` or `2B`, which are not digits. The
  published rule substitutes `19` and `18` before the modulo; without that every Corsican
  number fails.

> These identify people. Storing one makes the record personal data under GDPR and equivalents,
> and validating one does not make it safe to log. Nothing here writes the value anywhere, and
> the failure message never echoes it.

None of these can tell you a number was **issued** — only that it is well-formed. That needs
the issuing authority.

## Geo

| Rule | Parameters | Alias | Message key |
|---|---|---|---|
| `Latitude` | — | `latitude` | `latitude` |
| `Longitude` | — | `longitude` | `longitude` |
| `LatLng` | — | `lat_lng` | `lat_lng` |
| `UsState` | `bool $includeTerritories = false` | `us_state` | `us_state` |
| `CaProvince` | — | `ca_province` | `ca_province` |

- **`Latitude`** / **`Longitude`** — decimal degrees, -90..90 and -180..180 inclusive.
- **`LatLng`** — a `latitude,longitude` pair in that order.
- **`UsState`** — a US state by USPS two-letter code or by full name. `$includeTerritories`
  adds PR, GU, VI and the rest; `laranail_us_state:true`.
- **`CaProvince`** — a Canadian province or territory, by code or full name.

Country, currency and language **codes** live in the I18n family below; `laranail/atlas` owns
the richer per-country dataset (names, regions, coordinates) and can replace the bundled code
sets by binding the same contracts.

## I18n

| Rule | Parameters | Alias | Message key |
|---|---|---|---|
| `CountryCode` | `bool $alpha3 = false, bool $caseInsensitive = false` | `country_code` | `country_code` |
| `CurrencyCode` | `bool $numeric = false, bool $symbol = false, bool $caseInsensitive = false` | `currency_code` | `currency_code` / `currency_code_numeric` / `currency_symbol` |
| `LanguageCode` | `bool $caseInsensitive = false` | `language_code` | `language_code` |

- **`CountryCode`** — an assigned ISO 3166-1 code: alpha-2 (`KE`) by default, alpha-3 (`KEN`)
  with `alpha3: true` (`laranail_country_code:true`). The bundled set is the full registry —
  the 249 assigned alpha-2 codes plus the user-assigned `XK`, which real address data needs
  and which deliberately has no alpha-3.
- **`CurrencyCode`** — a **current** ISO 4217 identifier: the alpha code (`USD`) by default,
  the numeric code (`840`) with `numeric: true`, or a recognised symbol (`€`) with
  `symbol: true`. One representation per instance — asking for two throws. Retired codes
  (`HRK`, `SLL`, `ZWL`) fail; the bundled data is generated from the official registry and a
  suite test pins it to its committed source.
- **`LanguageCode`** — an assigned ISO 639-1 code, lowercase canonical (`en`, `sw`).

All three are strict about case by default because they usually guard a column an exact-match
lookup reads later; `caseInsensitive: true` folds before checking (it validates the folded
code — it does not rewrite the stored value).

The code sets are container contracts (`Contracts\I18n\CountryDataset`, `CurrencyDataset`,
`LanguageDataset`) with bundled full-registry defaults bound `singletonIf`. Bind your own to
narrow (the locales an app actually ships) or extend (a ledger accepting historical currency
codes) without touching the rules.

## Identifiers

| Rule | Parameters | Alias | Message key |
|---|---|---|---|
| `Imei` | — | `imei` | `imei` |
| `Vin` | `bool $checkDigit = false` | `vin` | `vin` |
| `HashDigest` | `string $algorithm` | `hash_digest` | `hash_digest` |
| `SemVer` | — | `semver` | `semver` |
| `Jwt` | — | `jwt` | `jwt` |

- **`Imei`** — an International Mobile Equipment Identity (3GPP TS 23.003): 15 digits, Luhn.
- **`Vin`** — a Vehicle Identification Number (ISO 3779): 17 characters, with `I`, `O` and `Q`
  excluded because they are confusable with `1` and `0`. `$checkDigit` additionally enforces
  the North American position-9 check digit, which is **off by default** because it is not
  applied worldwide and would reject valid non-NA numbers.
- **`SemVer`** — a Semantic Versioning 2.0.0 string, including prerelease and build metadata.
- **`HashDigest`** — a hex digest of a named algorithm (`new HashDigest('sha256')`,
  `laranail_hash_digest:sha256`): hex in either case at exactly the algorithm's width, from a
  table of 28 admitted algorithms. An unknown name throws at construction with the known names
  in the message. Shape, not provenance — a well-formed digest says nothing about what was
  hashed.
- **`Jwt`** — a JSON Web Token in JWS compact serialisation (RFC 7515 §3.1). Structural only:
  three base64url segments with a decodable JSON header. It does **not** verify the signature
  and is not an authentication check.

## Markup

| Rule | Parameters | Alias | Message key |
|---|---|---|---|
| `Xml` | `?string $schema = null` | `xml` | `xml.malformed`, `xml.schema`, `xml.schema_missing` |

**`Xml`** — well-formed XML, and optionally valid against an XSD. Nothing else in the Laravel
ecosystem validates against a schema, which is the reason it exists: "is it XML" is a weak
question, and "is it the document we agreed on" is the one an integration asks.

```php
'payload' => ['required', new Xml(schema: resource_path('schemas/invoice.xsd'))],
```

**External entities are not expanded.** Parsing untrusted XML with substitution on is XXE — the
path to reading local files or making the server issue requests for an attacker. `LIBXML_NONET`
is passed explicitly rather than relying on a libxml version default, and the error-handling
mode is restored afterwards because it is process-global.

A missing schema file reports separately from a bad document: that is a deployment fault, and
failing the input would blame the user for it.

## Net

| Rule | Parameters | Alias | Message key |
|---|---|---|---|
| `DomainName` | `bool $requireTld = true` | `domain_name` | `domain_name` |
| `Subdomain` | — | `subdomain` | `subdomain` |
| `Cidr` | — | `cidr` | `cidr` |
| `InCidrRange` | `list<string> $networks` | `in_cidr_range` | `in_cidr_range` |
| `PublicIp` | — | `public_ip` | `public_ip` |
| `PrivateIp` | — | `private_ip` | `private_ip` |
| `MacAddress` | formats, bytes, unicast, universal, OUIs | `mac_address` | `mac_address.*` |
| `Url` | schemes, hosts, ports, credentials, … | — (see below) | `url.*` |

- **`DomainName`** — a fully-qualified domain name per RFC 1035 as amended by RFC 5891 for
  internationalised names, so an A-label (`xn--`) is validated as one rather than as an
  arbitrary hyphenated label. `$requireTld: false` accepts a bare hostname.
- **`Subdomain`** — a single DNS label, for a user-chosen subdomain.
- **`Cidr`** — an address, a slash, and a prefix length, with the length checked against the
  address family.
- **`PublicIp`** / **`PrivateIp`** — complements, sharing one classifier. `PublicIp` is the
  **SSRF guard**: it rejects private (RFC 1918), loopback, link-local, shared address space
  (RFC 6598), unique local v6 (RFC 4193), documentation, benchmarking and discard ranges, and
  it resolves IPv4-mapped IPv6 (`::ffff:127.0.0.1`) before classifying — the mapped form is the
  standard bypass for a naive check.

- **`InCidrRange`** — membership of one or more CIDR networks. Comparison is on packed bytes, so
  a prefix that does not land on a byte boundary works, the two address families never match each
  other, and IPv4-mapped v6 is unwrapped first. A malformed network in the list matches nothing
  rather than everything: an allow-list with a typo should fail closed.
- **`MacAddress`** — notation (`colon`, `hyphen`, `dotted`, `bare`), EUI-48 vs EUI-64, the I/G and
  U/L bits, and OUI prefixes. Refuses broadcast and null outright, and says which rather than
  blaming a bit. `MacAddress::normalise()` converts any notation to the canonical colon form.
- **`Url`** — the parts Laravel's `url` does not look at: scheme, host allow/deny lists (the same
  matcher the email domain rules use), port, `user:pass@` credentials, IP-literal hosts, query and
  fragment, and length. Internationalised hosts are converted to A-label form so the Unicode and
  Punycode spellings agree.

> **`Url` has no string alias, on purpose.** It carries eleven settings, four of them lists, and a
> rule string is a flat positional tail — there is no honest way to write
> `hostIs(['*.example.com']) + port([80, 443]) + publicHost()` in one. An alias exposing only the
> schemes would be the dangerous half: the shape check without the host and credential checks
> anyone reading `laranail_url` would assume it had. Use `FluentRule::url()`.

> **`Url::publicHost()` and `PublicIp` are hygiene, not an SSRF boundary.** Both validate an
> **address** and neither resolves a hostname. A URL whose host is a name still has to be resolved,
> and a name can resolve differently between the check and the request. A real defence resolves at
> request time, pins the address it validated, and refuses redirects.

## Network

The only tier that performs IO, and the only rules skipped during a precognitive request.

| Rule | Parameters | Alias | Message key |
|---|---|---|---|
| `DeliverableEmail` | `?DnsResolver $resolver = null` | `deliverable_email` | `email.undeliverable`, `email.malformed` |

**`DeliverableEmail`** — the address's domain can actually receive mail. One DNS lookup, behind
the `DnsResolver` contract so it is injectable, cached and fakeable; Laravel's own `email:dns`
calls egulias' `DNSCheckValidation` directly, with no injection point and no caching.

```php
'email' => ['required', 'email', new DeliverableEmail()],
```

Three things it deliberately is not:

- **Not a mailbox check.** Only an SMTP conversation establishes that, most providers now
  answer it dishonestly to defeat harvesting, and running one from a signup form is a good way
  to get the sending host blocked. This answers the narrower question — does the domain accept
  mail at all — which is enough to catch `gmial.com`.
- **Not a security boundary.** A lookup that fails for any reason **passes**. An unreachable or
  rate-limited resolver is not the same as an undeliverable domain, and rejecting every signup
  for the duration of a DNS outage is the worse error.
- **Not MX-only.** RFC 5321 §5.1 says a domain with an address record and no MX takes delivery
  at that address, and small domains rely on it, so the bundled resolver falls back to A/AAAA.

It implements `PrecognitionSkippable` and performs no lookup during a precognitive request.
Laravel's precognition filter narrows by attribute rather than by what a rule does, so without
that a debounced email field would issue one DNS lookup per keystroke.

The bundled `Actions\CachedDnsResolver` is bound only if nothing else has bound the contract,
so `laranail/email` can replace it without any call site changing. Cache lifetime is
`laranail.validation.dns.ttl`, default one hour.

## Vendor identifiers

| Rule | Parameters | Alias | Message key |
|---|---|---|---|
| `VendorIdentifier` | `string $vendor` | `vendor_identifier` | `vendor_identifier` |

**`VendorIdentifier`** validates an id issued by a third-party service, in that service's
format — the values a settings screen collects and pastes into a script tag, where a typo
surfaces days later as "why is there no data".

| Constant | Shape |
|---|---|
| `GOOGLE_ANALYTICS` | `G-XXXXXXXXXX` (GA4 measurement id) |
| `GOOGLE_TAG_MANAGER` | `GTM-XXXXXXX` |
| `FACEBOOK_PIXEL` | 15 or 16 digits |
| `MICROSOFT_TENANT` | a UUID, or `common` / `organizations` / `consumers` |
| `AWS_REGION` | `us-east-1`, `eu-west-3`, `us-gov-west-1` |
| `DISCORD_USERNAME` | lowercase, 2–32 chars, no consecutive dots |

Case handling is **per vendor, not global**: Google ids are folded up because they are pasted in
any case, AWS regions are left alone because they are lowercase by specification, and a Discord
username is left alone because folding either way would admit a value Discord itself refuses.

These are format checks, not existence checks. A well-formed id for a property you do not own
passes; verifying ownership means calling the vendor, which is Network tier.

## Profanity

| Rule | Parameters | Alias | Message key |
|---|---|---|---|
| `NoProfanity` | `TermList\|list<string> $terms, list<string> $allowed = []` | *(none)* | `no_profanity` |

**No word list ships with this package**, and that is deliberate on two counts. The obvious
sources are unusable — `laravel-validation-rules/offensive` is LGPL-3.0 and cannot be copied
into an MIT package, and the multi-language lists circulating in the ecosystem generally record
no licence at all. It is also the wrong shape: what counts as unacceptable differs by audience,
jurisdiction and moderation policy, and changes over time. A list frozen into a package is
wrong for most of the people who install it.

What ships is the **matching**, which is the part naive implementations get wrong:

```php
'bio' => ['required', new NoProfanity($myTerms, allowed: ['scunthorpe'])],
```

- Character substitution is folded in the value: `b4dger`, `b@dger` and `ｂａｄｇｅｒ` all match
  `badger`.
- Separators and repeats are absorbed by the **pattern**, not by rewriting the value:
  `b.a.d.g.e.r` and `baaaadger` match, while `assess` and `class` still do not match a term of
  `ass`. Those requirements pull in opposite directions — stripping separators to catch the
  first destroys the word boundary protecting the second — so the term becomes a pattern that
  tolerates both while staying anchored.
- The allow-list is applied **before** the terms, for the real words that contain one. Without
  it any list with a short term rejects Scunthorpe, Penistone and assess, and the people it
  rejects are the least able to work around it.

It is a filter, not a moderation system. Anyone determined to get a word past it will; the
point is to catch the careless case without insulting the innocent one. There is no string
alias — a rule string cannot carry a word list.

## Postal

| Rule | Parameters | Alias | Message key |
|---|---|---|---|
| `PostalCode` | `array\|string $countries = [], ?string $countryField = null` | `postal_code` | `postal_code` |

**`PostalCode`** validates against a specific country's format — 100 countries, 20 distinct
patterns. Name the country directly, or read it from another field in the same payload:

```php
'zip' => new PostalCode('US'),
'zip' => new PostalCode(['US', 'CA']),
'zip' => PostalCode::reference('country'),      // reads the sibling field
```

It implements `DataAwareRule` for the reference form. Inside a wildcard, a sibling reference
resolves within the same row: under `addresses.*.postcode`, referencing `addresses.*.country`
uses that row's country rather than the first one.

As a string alias the sibling form takes an `@` sigil, because a bare parameter cannot say
whether `country` means the ISO code or a field of that name:

```php
'zip' => 'laranail_postal_code:US',
'zip' => 'laranail_postal_code:@country',
```

## Structure

| Rule | Parameters | Alias | Message key |
|---|---|---|---|
| `Delimited` | `array $rules, string $separator = ',', ?int $min = null, ?int $max = null, bool $distinct = false, bool $trim = true` | *(none)* | `delimited.*` |

**`Delimited`** validates every item of a delimited string against the same rules — a comma-
separated tag list, a semicolon-separated set of addresses:

```php
'emails' => new Delimited(['email'], separator: ',', max: 5, distinct: true),
```

It reports which item failed, not just that one did. This is the one rule with **no string
alias**: it takes a nested rule set, and `delimited:email|min:3` cannot survive the pipe
splitting that produced it. Use the object.

## Telecom

| Rule | Parameters | Alias | Message key |
|---|---|---|---|
| `Phone` | `string\|array $countries = [], ?string $countryField = null` + fluent modifiers | *(none — see below)* | `phone.*` |
| `UniquePhone` | `string $table, string $column = 'phone'` + the `Phone` surface | *(none — see below)* | `unique_phone` |

Both **require [`laranail/phone`](https://github.com/laranail/phone)**, which is a `suggest`
rather than a `require`: it pulls libphonenumber's numbering-plan metadata (megabytes an app
with no phone field should not carry). Constructing either rule without it throws with the
install command in the message.

**`Phone`** checks a number against Google's numbering-plan metadata — IO tier, not a regex,
because no pattern can know that `+254712345678` is an allocated Kenyan mobile range while
`+254012345678` is not. The default is `valid` (the range is actually allocated);
`possibleOnly()` relaxes to a shape check for signup-style forms where turning away a
newly-allocated range costs more than precision. A bare national number needs a country: pass a
fixed list, or `countryField('country')` to read the picker beside the input.

**`UniquePhone`** enforces uniqueness on the **E.164 normalisation**, not the raw string —
`0712 345 678`, `+254712345678` and `254712345678` are one number and collide as one. A raw
`unique:` on the column treats them as three distinct values, which is how duplicate accounts
happen.

Neither rule has a string alias, and the exception is enforced where the alias completeness
test lives (`EXTERNALLY_ALIASED_NAMESPACES`): their surfaces ride the optional dependency, so
the alias map stays free of references to it.

## Text

| Rule | Parameters | Alias | Message key |
|---|---|---|---|
| `Slug` | — | `slug` | `slug` |
| `Username` | `int $min = 3, int $max = 32` | `username` | `username` |
| `PersonName` | `bool $allowDigits = false` | `person_name` | `person_name` |
| `CaseStyle` | `string $style` | `case_style` | `case_style.{style}` |
| `HtmlClean` | `bool $mustContainHtml = false` | `html_clean` | `html_clean` / `contains_html` |
| `WithoutSpaces` | — | `without_spaces` | `without_spaces` |
| `MaxWords` | `int $max` | `max_words` | `max_words` |
| `MinWords` | `int $min` | `min_words` | `min_words` |
| `Salutation` | `?array $accepted = null` | `salutation` | `salutation` |

- **`Slug`** — lowercase alphanumerics separated by single hyphens, no leading or trailing one.
- **`Username`** — letters, digits and single internal separators, within `$min`..`$max`;
  `laranail_username:3,20`.
- **`PersonName`** — a human name. Allow-lists rather than deny-lists: Unicode letters and
  combining marks (`\p{L}`, `\p{M}`) plus spaces, hyphens and apostrophes, so `O'Neill`,
  `Jean-Luc`, `Müller` and `李` all pass while digits, emoji and markup do not. `$allowDigits`
  permits the systems that genuinely carry them.
- **`CaseStyle`** — an identifier in a given convention. `CaseStyle::CAMEL`, `KEBAB`, `PASCAL`,
  `SNAKE`, `TITLE`; as a string, `laranail_case_style:camel`.
- **`HtmlClean`** — the value contains no HTML markup. A rejection, not a sanitiser: it tells
  the user their input was not accepted rather than silently rewriting it.
  `mustContainHtml: true` (`laranail_html_clean:true`) inverts it — a rich-text field whose
  plain-text submission means the editor never loaded.
- **`MaxWords`** / **`MinWords`** — word-count bounds. A word is a run of letters, numbers or
  apostrophes in any script; hyphenated compounds split. `laranail_max_words:200`.
- **`Salutation`** — a recognised honorific (`Mr`, `Prof.`, `Madame`), case- and dot-insensitive.
  The bundled list is a curated everyday set; pass your own `$accepted` (lowercase, no dots) to
  replace it — that is also the localisation hook.
- **`WithoutSpaces`** — no whitespace of any kind, including the Unicode separators a
  `\s`-based check misses.

## Checking a rule in the browser

Twelve rules implement `Contracts\ClientCheckable`, which lets
[`laranail/validation-js`](https://github.com/laranail/validation-js) run them in the browser
instead of routing them to the server:

| Rule | Notes |
|---|---|
| `Text\Slug`, `Text\WithoutSpaces`, `Identifiers\SemVer`, `Net\Subdomain`, `Crypto\EthereumAddress` | The whole check is one pattern |
| `Text\CaseStyle` | The configured style's pattern; nothing for an unknown style |
| `Text\Username` | Shape and length in one pattern — see below |
| `Numbers\MonetaryAmount` | The pattern built from `$decimals` and `$allowNegative` |
| `Vendor\VendorIdentifier` | The vendor's pattern, carrying the same case handling the rule applies |
| `Postal\PostalCode` | Only the named countries' patterns — never the hundred-country table |
| `Geo\Latitude`, `Geo\Longitude` | `numeric` **and** `between` — two rules, not a pattern |
| `Colour\CssColor` | One pattern per configured notation, named colours included |

`Username` is worth a note because its length bound used to be a `strlen()` check, which counts
**bytes**, while a regex lookahead counts characters. They cannot differ here: the character
class is ASCII-only, so anything that could pass has one byte per character. A rule with a
Unicode class could not make the same move.

`PostalCode` advertises nothing when built with `reference('country')` — the country comes from
a sibling field, so which pattern applies is not knowable while exporting.

They advertise their **own pattern** rather than a JavaScript implementation. That is the whole
design: a hand-written twin of every rule would drift from the PHP one and disagree with the
server in the cases nobody tested. One pattern, exported, executed in both places.

**No rule performing a checksum, a query or IO implements it, and none should.** Advertising a
shape-only pattern for an IBAN would pass a mistyped account number in the browser and fail it
on the server — the precise failure client-side validation exists to prevent. A test asserts
that Iban, Luhn, Isin, Imei, Vin, Isbn, Gtin, BitcoinAddress, NationalIdentifier, Authorized,
ModelsExist and DeliverableEmail stay off the list.

Another test checks each advertised pattern gives the same verdict as the rule itself over a
grid of values, so the two cannot drift apart silently.

### Why the contract returns a LIST

`clientRules()` returns several rules, all of which must pass — not one.

That is what makes `Geo\Latitude` expressible. Its check is `is_numeric` plus a range, so its
browser form is `numeric` **and** `between:-90,90`: two rules the runner already implements.
Forced into one rule it would have to be a regex contorted into a bounded numeric range —
unreadable, rewritten for every bound, and wrong at exactly the boundary values that matter.

`Colour\CssColor` inlines the 150 named colours into its pattern, about 2 KB. Omitting them
would mean a browser rejecting `red`, which is a real defect; the size never was one, in a
package that ships an 8,201-entry domain list.

### Rules that still do not advertise

Anything whose check includes a **checksum** (`Iban`, `Luhn`, `Isin`, `Imei`, `Vin`, `Isbn`,
`Gtin`, `BitcoinAddress`, `NationalIdentifier`), a **database query** (`Authorized`,
`ModelsExist`) or **IO** (`Network\DeliverableEmail`). A shape-only approximation would pass a
mistyped account number in the browser and fail it on the server. A test asserts they stay
off.

## Messages

Every message lives under `laranail-validation::validation.` and is overridable the usual way,
by publishing the language file or by passing a custom message:

```php
$request->validate(
    ['account' => ['required', new Iban()]],
    ['account' => 'That does not look like a valid IBAN.'],
);
```

Only English ships. The package deliberately does not carry translations it cannot maintain.

## Not in this library

| You want | It lives in |
|---|---|
| Country / currency / language codes | `laranail/atlas` |
| Enum values, names, transitions | `laranail/enumerator` |
| Timezones, date existence and ambiguity | `laranail/chrono` |
| Password strength, common-password rejection | `laranail/toolkit` |
| Captcha verification | `laranail/captcha` |
| Licence keys | `laranail/license-kit` |
| Phone-number engine (metadata, formatting, types) | `laranail/phone` — the validation rules for it ship HERE ([Telecom](#telecom)), riding that package |
| Maintained disposable / role lists, and a production DNS resolver | `laranail/email` |

`ulid`, `uuid`, `url`, `hex_color`, `mac_address`, `timezone`, `ascii`, `lowercase`,
`uppercase` and `multiple_of` are all native Laravel rules. This library does not reimplement
them.

---

[← Docs index](../../README.md#documentation)
