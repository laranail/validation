# Identity and network fields

Usernames, URLs, IP addresses, MAC addresses, email addresses and phone numbers — the typed
builders, and what each one checks past the point Laravel's rule stops.

## Why these got their own builders

The package's premise is that each rule type exposes only the methods that apply to it. Four of
these did not:

```php
FluentRule::url()         // → StringRule
FluentRule::ip()          // → StringRule
FluentRule::macAddress()  // → StringRule
FluentRule::username()    // did not exist at all
```

A URL field autocompleted `hexColor()`, `uuid()` and `dateFormat()` and offered nothing about
schemes or hosts. Each factory was a one-line delegate to Laravel's bare string rule, so the
package's own `PublicIp`, `PrivateIp`, `DomainName` and `Username` were unreachable from the fluent
surface — you had to know the class name and reach for `->rule(new …)`.

Each is now a node with a narrow surface. **A chain that only used the old form still compiles the
same way**: `FluentRule::url()->required()->max(255)` is unchanged.

## Usernames

```php
'handle' => FluentRule::username()->required()->unique('users', 'handle'),
'handle' => FluentRule::username(3, 15)->required()->lowercase()->separators('_'),
```

Letters, digits, and single separators between them — never at the start, at the end, or doubled.
`admin.`, `_admin` and `admin..b` all read as `admin` at a glance, and a doubled separator is
invisible in most fonts.

ASCII only, by design. A username is an identifier people type, read aloud and compare visually;
allowing Unicode invites homograph impersonation (`аdmin` with a Cyrillic а), which is a worse
problem here than an ASCII-only handle is an inconvenience.

### Reserved names

A short list ships and is **on by default**. Every entry breaks something concrete rather than
merely being undesirable: `admin` and `support` are impersonation, `api` and `assets` collide with
routes, `me` and `new` collide with the conventional sub-paths of a profile URL.

```php
FluentRule::username()->reserved([])                          // off
FluentRule::username()->alsoReserved(['acme', 'enterprise'])  // add to the shipped list
FluentRule::username()->reserved(['boss'])                    // replace it
```

Matching is case-insensitive and runs against the value **with its separators stripped**, because
`a-d-m-i-n` and `ad.min` are the same claim. A list that compared the literal value would let every
one of those through.

> `separators()` takes a character set, and it is escaped before it reaches a character class. A
> bare `-` between two characters there is a **range**: `.-_` unescaped compiles to "dot through
> underscore" and silently admits `/`, every digit, `:` and `@`.

## URLs

```php
'website'  => FluentRule::url()->required(),
'webhook'  => FluentRule::url()->required()->secure()->publicHost(),
'callback' => FluentRule::url()->required()->hostIs(['*.example.com'])->port(443),
```

Every one of these passes Laravel's `url`, and none of them is malformed:

| Value | What it is |
|---|---|
| `https://user:password@example.com/` | credentials in a stored link |
| `http://169.254.169.254/latest/meta-data` | the cloud metadata endpoint |
| `https://example.com:22/` | a scheme and port that disagree |
| `ftp://files.example.com/` | a scheme nobody meant to allow |

The defaults are what a link a user typed should be: `http` or `https`, a real host, and no
credentials.

| Method | Effect |
|---|---|
| `scheme(['https'])` / `secure()` | restrict the scheme |
| `hostIs([...])` / `hostIsNot([...])` | allow- and deny-list, `*.example.com` for subdomains |
| `port(443)` | restrict the port |
| `allowCredentials()` | permit `user:pass@` (off by default) |
| `withoutIpHost()` | require a name, not `192.0.2.1` |
| `publicHost()` | reject reserved IP literals and loopback names |
| `allowSingleLabelHost()` | accept `intranet` |
| `withoutQuery()` / `withoutFragment()` | for a field holding a base address |
| `maxLength(n)` | defaults to 2048 |
| `active()` | also require it to resolve — **network tier** |

The host allow-list is the same matcher the email domain rules use, which is deliberate: two copies
of an allow-list matcher is two chances to leave a gap. `*.example.com` matches subdomains and
**not** the bare domain — list both when both are wanted — and it will not accept
`evil-example.com` or `example.com.attacker.test`, which are the two shapes a hand-written suffix
check gets wrong.

> **`publicHost()` is hygiene, not an SSRF boundary.** It rejects an IP literal in a reserved range
> and the loopback names, so `http://169.254.169.254/` and `http://127.0.0.1:6379/` do not get
> through — including the `::ffff:127.0.0.1` wrapping that defeats most naive filters. It cannot
> reject `https://evil.test/` that resolves to loopback, because answering that needs DNS, and even
> a rule that resolved would be defeated by rebinding between the check and the request. A real
> defence resolves at request time, pins the address it validated, and refuses redirects.

Internationalised hosts are converted to their A-label form before the structural check, so
`münchen.de` and `xn--mnchen-3ya.de` get the same answer. Only the authority is converted — a host
reappearing in a redirect parameter is left alone — and non-ASCII elsewhere is percent-encoded, the
way a browser would send it. Requires `ext-intl`; without it non-ASCII input is rejected rather than
silently mishandled.

Control characters are rejected anywhere in the value, not just at the ends. A newline that later
reaches a header is response splitting, and a tab inside the host is how a browser and a
server-side parser are made to disagree about where the host ends.

## IP addresses

```php
'client_ip' => FluentRule::ip()->required(),
'origin'    => FluentRule::ip()->required()->v4()->public(),
'office'    => FluentRule::ip()->required()->inRange(['203.0.113.0/24']),
'internal'  => FluentRule::ip()->required()->private(),
```

`public()` is stricter than the usual `filter_var` shortcut
(`FILTER_FLAG_NO_PRIV_RANGE|FILTER_FLAG_NO_RES_RANGE`), which has two holes: it reads
`::ffff:127.0.0.1` as an ordinary global v6 address, and it lets the carrier-grade NAT block
`100.64.0.0/10` through. `private()` is its exact complement over valid addresses — both delegate to
one classifier, so a range cannot be private to one and public to the other.

`inRange()` is the rule an office allow-list or a webhook source check actually needs. Written at
the call site it is almost always either a string comparison (`str_starts_with($ip, '10.')`, which
matches `100.0.0.1` and misses `::ffff:10.0.0.1`) or an `ip2long` mask, which silently returns false
for every IPv6 address. Comparison is on packed bytes, so a prefix that does not land on a byte
boundary works, the two families never match each other, and IPv4-mapped v6 is unwrapped first.

A malformed network in the list matches nothing rather than everything — an allow-list with a typo
should fail closed, and should not throw mid-request on a value the user chose.

## MAC addresses

```php
'mac'    => FluentRule::macAddress()->required(),
'device' => FluentRule::macAddress()->required()->colon()->unicast()->universal(),
'switch' => FluentRule::macAddress()->required()->eui48()->oui(['00:1B:44']),
```

Laravel's `mac_address` answers whether the value is shaped like one. Three questions it cannot
answer bite in practice:

- **Which notation.** `00:1B:44:11:3A:B7`, `00-1B-44-11-3A-B7` and `001b.4411.3ab7` are one address
  written three ways. A column accepting all three cannot be looked up by equality, and the
  duplicate is invisible in the table. `colon()`, `hyphen()`, `dotted()` and `bare()` pin it, and
  `MacAddress::normalise()` converts to the canonical colon form.
- **Whether it names a device.** `FF:FF:FF:FF:FF:FF` is broadcast and `00:00:00:00:00:00` is null.
  Both pass a format check; both are refused here, and each says which it is rather than blaming a
  bit.
- **Whether it is real or randomised.** `universal()` is the one that matters most. Every modern
  phone presents a locally-administered address to networks it has not joined — a perfectly valid
  MAC and a useless identity, because it changes. A device register that stores one is storing
  something that will not match tomorrow.

`unicast()` and `universal()` read bit 0 and bit 1 of the first octet — the I/G and U/L bits.
`eui48()` and `eui64()` bound the length. `oui()` takes prefixes in any notation and any length, so
a 24-bit OUI and a longer MA-M/MA-S assignment both work.

## Email addresses

`FluentRule::email()` already carried the RFC/strict/DNS/spoof modes, `notDisposable()`,
`notRole()`, `domainIs()` and `domainIsNot()`. Three additions:

```php
FluentRule::email()->deliverable()            // MX lookup, cached and fakeable
FluentRule::email()->withoutSubaddressing()   // reject user+tag@example.com
FluentRule::email()->maxRfcLength()           // the RFC 5321 ceiling, 254
```

`deliverable()` and `validateMxRecord()` are not the same thing. The latter compiles to Laravel's
`email:dns`, which calls egulias' `DNSCheckValidation` directly and is therefore neither cached,
injectable nor fakeable. `deliverable()` goes through the `DnsResolver` contract, so the handful of
domains that dominate any signup form are looked up once, a test does not reach the network, and it
skips itself during a precognitive request — without which a debounced email field issues a lookup
per keystroke. The rule was already in the package; it just had no way in from the builder.

`withoutSubaddressing()` should stay off nearly everywhere. Subaddressing is a legitimate feature
people use to filter their own mail. It earns its place on one kind of field: the one that grants
something per account, where one mailbox minting unlimited distinct addresses is how a single
person takes a free trial repeatedly.

## Phone numbers

`FluentRule::phone()` is unchanged and already sits on Google's numbering-plan metadata, through
`laranail/phone` → `giggsey/libphonenumber-for-php`. See [Phone rule](phone-rule.md) for countries,
line types, strictness and E.164-normalised uniqueness.

---

[← Docs index](../../README.md#documentation)
