## FluentRule Validation

- This project uses `laranail/validation` for type-safe validation rules. Use `FluentRule::` instead of string rules or `Rule::` where possible.
- FormRequests MUST use `HasFluentRules` trait. Livewire components MUST use `HasFluentValidation` trait.
- Do NOT use `->rule('string_rule')` when a native FluentRule method exists. Check the skill references before using escape hatches.
- Typed nodes: `FluentRule::string()`, `integer()`, `numeric()`, `date()`, `dateTime()`, `boolean()`, `accepted()`, `declined()`, `array()`, `list()`, `file()`, `image()`, `email()`, `password()`, `enum()`, `field()` — and their OWN nodes for `url()` (`UrlRule`), `ip()`/`ipv4()`/`ipv6()` (`IpAddressRule`), `macAddress()` (`MacAddressRule`) and `username()` (`UsernameRule`). Do NOT type-hint these five as `StringRule`; hint the node or `FluentRuleContract`.
- `FluentRule::url()` carries defaults: `http`/`https` only and no `user:pass@` credentials. A form that accepts other schemes needs `->scheme([...])`; credentials need `->allowCredentials()`.
- Convenience shortcuts returning `StringRule`: `uuid()`, `ulid()`, `json()`, `timezone()`, `hexColor()`, `activeUrl()`, `regex()`. `FluentRule::anyOf()` composes alternative rule sets.
- `email()` and `password()` use app defaults (`Email::defaults()`, `Password::defaults()`) ONLY while no mode method is chained. Calling any of `rfcCompliant()`, `strict()`, `validateMxRecord()`, `preventSpoofing()` or `withNativeValidation()` replaces the app defaults entirely — that is a second way to opt out besides `defaults: false`, and it is easy to do by accident. If an app sets `Email::defaults(fn () => Rule::email()->preventSpoofing())`, then `FluentRule::email()->validateMxRecord()` silently drops the spoof check.
- All conditional modifiers (`requiredIf`, `excludeIf`, `prohibitedIf`, etc.) accept both `(string $field, ...$values)` AND `(Closure|bool)` — do NOT wrap in `Rule::requiredIf()`.

## Extended rule library

- The package also ships 54 domain rules under `Simtabi\Laranail\Validation\Rules\`, for formats Laravel has no rule for. They are plain `ValidationRule` objects: `['account' => ['required', new Iban()]]`, or in a chain via `->rule(new Iban())`.
- Families: `AntiSpam\` (Honeypot, SubmissionTiming), `Banking\` (Iban, Bic, Isin, Luhn), `Colour\` (CssColor), `Codes\` (Gtin, Ean, Isbn, Issn), `Crypto\` (BitcoinAddress, EthereumAddress), `Database\` (Authorized, ModelsExist), `Email\` (EmailDomainIs, EmailDomainIsNot, NotDisposableEmail, NotRoleEmail), `Fiscal\` (NationalIdentifier), `Geo\` (Latitude, Longitude, LatLng, UsState, CaProvince), `Identifiers\` (Imei, Vin, SemVer, Jwt), `Markup\` (Xml), `Net\` (DomainName, Subdomain, Cidr, PublicIp, PrivateIp), `Network\` (DeliverableEmail), `Numbers\` (Parity, MonetaryAmount), `Postal\` (PostalCode), `Profanity\` (NoProfanity — supply your own word list; none ships), `Structure\` (Delimited), `Telecom\` (Phone, UniquePhone — require the optional `laranail/phone`), `Vendor\` (VendorIdentifier), `Text\` (Slug, Username, PersonName, CaseStyle, HtmlClean, WithoutSpaces).
- The email rules have named methods on the email node: `FluentRule::email()->notDisposable()`, `->notRole()`, `->domainIs([...])`, `->domainIsNot([...])`.
- `PostalCode` reads its country from a sibling field via `PostalCode::reference('country')`.
- String aliases (`laranail_iban`, `laranail_postal_code:US`) exist but are OFF by default and must be enabled in config. Prefer the rule objects — they are type-checked and do not depend on a config flag.
- Do NOT reimplement these, and do NOT reach for a third-party package for them.
- Country/currency/language codes are NOT here (use `laranail/atlas`), nor enums (`laranail/enumerator`), timezones (`laranail/chrono`), password strength (`laranail/toolkit`), or captcha (`laranail/captcha`).
- `ulid`, `uuid`, `url`, `hex_color`, `mac_address`, `timezone`, `ascii`, `lowercase`, `uppercase` and `multiple_of` are native Laravel rules — use those, not a custom rule.

## Skills

- For converting validation rules, activate the `laranail-validation-optimize` skill which has a complete method reference.
- For Livewire-specific guidance, activate the `laranail-validation-livewire` skill.
