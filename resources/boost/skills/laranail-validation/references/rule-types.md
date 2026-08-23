# Rule Type Reference

## String

- Length: `min($n)`, `max($n)`, `between($min, $max)`, `exactly($n)`
- Pattern: `alpha(ascii?)`, `alphaDash(ascii?)`, `alphaNumeric(ascii?)`, `ascii()`, `regex($p)`, `notRegex($p)`
- Starts/ends: `startsWith(...$v)`, `endsWith(...$v)`, `doesntStartWith(...$v)`, `doesntEndWith(...$v)`
- Case: `lowercase()`, `uppercase()`
- Email: `email(...$modes)` — e.g. `email()`, `email('rfc', 'dns')`
- Format: `url()`, `activeUrl()`, `uuid()`, `ulid()`, `json()`, `ip()`, `ipv4()`, `ipv6()`, `macAddress()`, `timezone()`, `hexColor()`
- Encoding: `encoding($encoding)` — validates string encoding (e.g., `encoding('UTF-8')`)
- Date: `date()`, `dateFormat($format)`
- Auth: `currentPassword($guard?)`
- Comparison: `confirmed()`, `same($field)`, `different($field)`, `inArray($field)`, `inArrayKeys($field)`, `distinct($mode?)`

## Email

- `FluentRule::email()` uses `Email::default()` when app defaults are configured. Pass `defaults: false` for basic validation: `FluentRule::email(defaults: false)`
- Explicit modes override defaults: `rfcCompliant(strict?)`, `strict()`, `validateMxRecord()`, `preventSpoofing()`, `withNativeValidation(allowUnicode?)`
- Constraints: `max($n)`, `confirmed()`, `same($field)`, `different($field)`
- Embedded: `in($values)`, `notIn($values)`, `enum($class, $callback?)`, `unique($table, $column?)`, `exists($table, $column?)`
- Also available as `FluentRule::string()->email(...$modes)` for inline use

## Password

- `FluentRule::password()` uses `Password::default()` from AppServiceProvider. Pass `defaults: false` for plain `Password::min(8)`: `FluentRule::password(defaults: false)`
- Length: `min($n)`, `max($n)` — `min()` overrides the default minimum
- Strength: `letters()`, `mixedCase()`, `numbers()`, `symbols()`
- Security: `uncompromised($threshold?)` — check against breached password databases
- Comparison: `confirmed()`

## Numeric / Integer

- `FluentRule::integer()` — shorthand for `FluentRule::numeric()->integer()`, common for ID fields
- Type: `integer(strict?)`, `decimal($min, $max?)`
- Size: `min($n)`, `max($n)`, `between($min, $max)`, `exactly($n)` — `exactly()` implicitly adds `integer()`
- Digits: `digits($n)`, `digitsBetween($min, $max)`, `minDigits($n)`, `maxDigits($n)`
- Comparison: `greaterThan($field)`, `greaterThanOrEqualTo($field)`, `lessThan($field)`, `lessThanOrEqualTo($field)`, `multipleOf($n)`, `confirmed()`, `same($field)`, `different($field)`, `inArray($field)`, `inArrayKeys($field)`, `distinct($mode?)`

## Date

All comparison methods accept `DateTimeInterface|string`:

- Format: `format($format)` — REPLACES the `date` base type with `date_format:$format`. Use for time-only: `FluentRule::date()->format('H:i')` → `date_format:H:i`
- Today: `beforeToday()`, `afterToday()`, `todayOrBefore()`, `todayOrAfter()`
- Now: `past()`, `future()`, `nowOrPast()`, `nowOrFuture()`
- Compare: `before($date)`, `after($date)`, `beforeOrEqual($date)`, `afterOrEqual($date)`, `between($from, $to)`, `betweenOrEqual($from, $to)`, `dateEquals($date)`, `same($field)`, `different($field)`

## Boolean

`FluentRule::boolean()` validates that the value is `true`, `false`, `1`, `0`, `'1'`, or `'0'`. It does NOT accept `'yes'`, `'on'`, `'no'`, `'off'`.

- `accepted()` — value must be `'yes'`, `'on'`, `'1'`, `1`, `true`, or `'true'`
- `acceptedIf($field, ...$values)` — accepted only when another field has a given value
- `declined()` — value must be `'no'`, `'off'`, `'0'`, `0`, `false`, or `'false'`
- `declinedIf($field, ...$values)` — declined only when another field has a given value

**Footgun:** `FluentRule::boolean()->accepted()` compiles to `boolean|accepted` — `boolean` rejects `'yes'` / `'on'` which `accepted` permits. For HTML-form-style inputs, use the standalone `FluentRule::accepted()` factory (below) instead.

## Accepted

`FluentRule::accepted()` is the permissive checkbox/opt-in rule (no `boolean` base). Accepts `true`, `1`, `'1'`, `'yes'`, `'on'`. Use this for terms-of-service, consent, marketing opt-in, and similar HTML form fields where the browser may post `'yes'` or `'on'`.

- `acceptedIf($field, ...$values)` — conditional; replaces the unconditional base

## Array

- Size: `min($n)`, `max($n)`, `between($min, $max)`, `exactly($n)`
- Structure: `list()`, `requiredArrayKeys(...$keys)`, `contains(...$values)`, `doesntContain(...$values)`
- Wildcard children: `each($rule)` for scalar items, `each([...])` for object items → produces `items.*.name`
- Fixed-key children: `children([...])` for known-key objects → produces `search.value` (no wildcard). Also available on `FluentRule::field()`
- Polymorphic fields: `FluentRule::field()->rule(FluentRule::anyOf([...]))->children([...])` for fields that can be different types with optional child keys
- Constructor: `FluentRule::array(['name', 'email'])` — restrict allowed keys; accepts `BackedEnum` values

## File

- Size: `min($size)`, `max($size)`, `between($min, $max)`, `exactly($size)` — accepts int (KB) or human-readable strings (`'5mb'`, `'1gb'`)
- Type: `extensions(...$ext)`, `mimes(...$mimes)`, `mimetypes(...$types)`

## Image (extends File)

- `allowSvg()` — allow SVG uploads
- `dimensions(Dimensions)` — pass an `Illuminate\Validation\Rules\Dimensions` instance
- `width($n)`, `height($n)` — exact dimensions
- `minWidth($n)`, `maxWidth($n)`, `minHeight($n)`, `maxHeight($n)`
- `ratio($value)` — aspect ratio (e.g. `16/9`)
- Inherits all file methods

## Field (untyped)

- No base type constraint — use for fields that need modifiers without a type
- Supports `children([...])` for fixed-key child rules
- Supports all field modifiers and embedded rules
- Comparison: `same($field)`, `different($field)`, `confirmed()`
- Also what an automated conversion falls back to when the type can't be narrowed from the original pipe/array rules. When reviewing converted code, consider whether a typed factory (`string()`, `integer()`) better expresses intent — don't leave `field()` in place if the field has an inherent type that was just obscured by the original string-rule syntax.

## Embedded Rules (string, numeric, date, email)

- `in($values)` — accepts array, `BackedEnum` class string, Arrayable (Collection): `in(StatusEnum::class)`, `in([1, 2, 3])`, `in($collection)`
- `notIn($values)` — same as `in()`, also accepts scalar: `notIn('admin')`, `notIn(42)`
- `unique($table, $column?, $callback?)`, `exists($table, $column?, $callback?)` — callback for `->where()`, `->ignore()`, `->withoutTrashed()`
- `enum($class, $callback?)` — callback receives the `Illuminate\Validation\Rules\Enum` instance

## Identity and Network Types

These return their own node, not a `StringRule`, so the surface is narrow and the package's own
rules are reachable from the chain. See `docs/tools/identity-and-network.md`.

- `FluentRule::url()` → `UrlRule`. Defaults to `http`/`https`, a real host, and no `user:pass@`.
  `->secure()`, `->scheme([...])`, `->hostIs(['*.example.com'])`, `->hostIsNot([...])`,
  `->port(443)`, `->publicHost()`, `->withoutIpHost()`, `->allowCredentials()`,
  `->allowSingleLabelHost()`, `->withoutQuery()`, `->withoutFragment()`, `->maxLength()`,
  `->active()` (DNS — network tier).
  `publicHost()` is hygiene, NOT an SSRF boundary: it cannot see where a name resolves.
- `FluentRule::ip()` → `IpAddressRule`. `->v4()`, `->v6()`, `->public()`, `->private()`,
  `->inRange(['10.0.0.0/8'])`. `ipv4()`/`ipv6()` are `ip()->v4()`/`->v6()`.
- `FluentRule::macAddress()` → `MacAddressRule`. `->colon()`, `->hyphen()`, `->dotted()`,
  `->bare()`, `->eui48()`, `->eui64()`, `->unicast()`, `->universal()`, `->oui([...])`.
  `universal()` is the one that matters: it separates a manufacturer's address from a phone's
  randomised one.
- `FluentRule::username($min = 3, $max = 32)` → `UsernameRule`. `->lowercase()`,
  `->separators('_')`, `->reserved([])`, `->alsoReserved([...])`, `->length($min, $max)`.
  A reserved-name list is ON by default — `admin`, `api`, `root` and about thirty more, matched
  case-insensitively with separators stripped.

Email gained three: `->deliverable()` (cached, fakeable MX lookup — prefer it over
`->validateMxRecord()`), `->withoutSubaddressing()`, `->maxRfcLength()`.

## Convenience Shortcuts

Still plain `StringRule` chains, because the format is the whole rule:

- `FluentRule::uuid()` — shorthand for `FluentRule::string()->uuid()`
- `FluentRule::ulid()` — shorthand for `FluentRule::string()->ulid()`
- `FluentRule::json()`, `timezone()`, `hexColor()`, `activeUrl()`, `regex($pattern)`
- `FluentRule::subdomain()` — one DNS label, Punycode rejected
- `FluentRule::domainName($requireTld = true)` — a full domain, IDN included
- All accept an optional `?string $label` parameter

## Combinators

- `FluentRule::anyOf([...])` — value passes if it matches any of the given rules. Requires Laravel 13+ (`AnyOf` class). Guarded by `class_exists()` at runtime.
