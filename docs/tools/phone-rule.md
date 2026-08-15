# Phone rule

Fourteen methods for validating a phone number against Google's numbering-plan metadata —
`FluentRule::phone()`, backed by `Simtabi\Laranail\Validation\Rules\Telecom\Phone`.

```php
use Simtabi\Laranail\Validation\FluentRule;

'phone' => FluentRule::phone()->required()->country('KE')->mobile(),
```

It is an **IO-tier** rule: the check reads libphonenumber's metadata from disk, cached in-process
after the first lookup for a given prefix. It is not a regex and it cannot be — no pattern knows that
`+254712345678` is an allocated Kenyan mobile range while `+254012345678` is not.

In this section: [Countries](#countries) · [Types](#types) · [Strictness](#strictness) ·
[Rejections](#rejections) · [Uniqueness](#uniqueness) · [Messages](#messages) ·
[Method reference](#method-reference)

## Requires `laranail/phone`

A `suggest`, not a `require`. It pulls libphonenumber's numbering-plan metadata, and a project
validating only strings and dates should not have to carry that:

```bash
composer require laranail/phone
```

Without it, the rule throws with a sentence naming the package rather than a class-not-found from
three frames deeper.

## Countries

A bare national number cannot be checked without knowing which plan to check it against. There are
three ways to supply one.

### A fixed list

```php
FluentRule::phone()->country('KE');
FluentRule::phone()->country(['KE', 'UG', 'TZ']);
```

With **exactly one** country it doubles as the parse hint for bare national input. With several it
does not — picking one arbitrarily would make the outcome depend on array order — so pair a
multi-country list with `countryFrom()` or expect international input.

### From a sibling field

For a form with a country picker beside the number:

```php
'phone_country' => FluentRule::string()->required()->size(2),
'phone'         => FluentRule::phone()->required()->countryFrom('phone_country'),
```

### International only

```php
FluentRule::phone()->international();
```

Requires the input to carry its own calling code. The right constraint for a global signup form: it
removes the ambiguity rather than guessing at it.

## Types

```php
FluentRule::phone()->mobile();
FluentRule::phone()->fixedLine();
FluentRule::phone()->tollFree();
FluentRule::phone()->voip();
FluentRule::phone()->type([PhoneNumberType::Mobile, PhoneNumberType::Voip]);
```

> **`mobile()` accepts North American numbers.** The NANP does not distinguish mobile from
> fixed-line, so libphonenumber reports `FIXED_LINE_OR_MOBILE` for every US and Canadian number.
> Rejecting those would fail every valid American mobile number — a bug that is easy to ship and hard
> to notice from a single-country test suite.

## Strictness

| | Asks | Fails when |
|---|---|---|
| `strict()` — the default | Is this number allocated? | The range exists in the plan but has not been issued |
| `possible()` | Is this number correctly shaped? | The length is wrong for the plan |

```php
FluentRule::phone()->possible();   // shape only
FluentRule::phone()->strict();     // back to the default
```

Strict is the right default, and it has a real cost worth knowing about: newly allocated ranges are
*possible* before Google's metadata knows about them, so a small number of genuine customers are
turned away until the next libphonenumber release. Where reach matters more than precision — signup
forms, lead capture — `possible()` relaxes to a shape check.

## Rejections

```php
FluentRule::phone()->withoutExtension();     // reject `;ext=`, accepted by default
FluentRule::phone()->rejectShortNumbers();   // reject short codes
FluentRule::phone()->rejectEmergency();      // reject emergency numbers
```

`rejectEmergency()` is worth reaching for on any field that will later be dialled or messaged
automatically. A contact record holding an emergency number is a support incident waiting to happen.

## Uniqueness

```php
FluentRule::phone()->country('KE')->unique('contacts', 'phone');
```

**This is not Laravel's `unique`.** The phone rule overrides it, because the generic one compares the
attribute exactly as it arrived — and with a row holding `+254712123456`, a user typing
`0712 123456` passes. The strings differ, so the query finds nothing, and you get a duplicate contact
that no amount of squinting at the table explains.

The override normalises to E.164 first and queries the canonical form, so every spelling of the same
number collides:

| Typed | Stored | Collides |
|---|---|:---:|
| `0712 123456` | `+254712123456` | yes |
| `+254 712 123456` | `+254712123456` | yes |
| `00254712123456` | `+254712123456` | yes |
| `0722 123456` | `+254712123456` | no — different number |

The country hint follows whatever the chain was already told, so it does not have to be repeated.

For an edit form, exclude the row being edited or it always collides with itself:

```php
use Simtabi\Laranail\Validation\Rules\Telecom\UniquePhone;

FluentRule::phone()->unique('contacts', 'phone', fn (UniquePhone $rule) => $rule->ignore($id));
```

Unparseable input is **not** reported as a duplicate. It is not a number, so it cannot collide with
one — and reporting the same input twice for two different reasons only obscures which one the user
has to fix.

## Messages

Seven keys rather than one, because a single "invalid phone number" tells the user nothing about
which part to change:

| Key | Fires when |
|---|---|
| `phone` | Not a valid number |
| `phone_possible` | Not even correctly shaped |
| `phone_country` | Valid, but from the wrong country |
| `phone_type` | Valid, but the wrong line type |
| `phone_extension` | An extension was included and is not allowed |
| `phone_short_code` | A short code where a full number was required |
| `phone_emergency` | An emergency number |
| `phone_unique` | Already taken |

Override per-chain the same way as any other rule:

```php
FluentRule::phone('Mobile number', 'We could not recognise that as a phone number.');
```

> Shipping these at all is a differentiator rather than table stakes: none of the Filament phone
> packages this was designed against ships any message, and neither does
> `propaganistas/laravel-phone` — its README tells you to add the key yourself.

## Method reference

| Method | |
|---|---|
| `country(string\|array $countries)` | Accept these ISO 3166-1 alpha-2 codes only |
| `countryFrom(string $field)` | Read the country from a sibling field |
| `international()` | Require the input to carry its own calling code |
| `type(PhoneNumberType\|array $types)` | Accept these line types only |
| `mobile()` · `fixedLine()` · `tollFree()` · `voip()` | Shorthands for `type()` |
| `possible()` | Shape check instead of allocation check |
| `strict()` | Allocation check — the default |
| `withoutExtension()` | Reject an extension |
| `rejectShortNumbers()` | Reject short codes |
| `rejectEmergency()` | Reject emergency numbers |
| `unique($table, $column, $callback, $message)` | E.164-normalised uniqueness |

Everything from the shared builder — `required()`, `nullable()`, `when()`, `label()`, `exists()` —
applies as usual. See [Rule reference](fluent-rule.md).

---

[← Docs index](../../README.md#documentation)
