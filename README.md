# laranail/validation

[![Latest version on Packagist](https://img.shields.io/packagist/v/laranail/validation.svg)](https://packagist.org/packages/laranail/validation)
[![Tests](https://github.com/laranail/validation/actions/workflows/run-tests.yml/badge.svg)](https://github.com/laranail/validation/actions/workflows/run-tests.yml)
[![Static analysis](https://github.com/laranail/validation/actions/workflows/phpstan.yml/badge.svg)](https://github.com/laranail/validation/actions/workflows/phpstan.yml)
[![License: MIT](https://img.shields.io/badge/license-MIT-blue.svg)](LICENSE)

> Write Laravel validation rules with IDE autocompletion instead of memorising string syntax — each rule type exposes only the methods that apply to it, `each()` and `children()` keep parent and child rules in one place, and large wildcard arrays validate tens of times faster — see the [benchmarks](docs/performance.md).

Targets PHP `^8.5` on Laravel `^13`.

```php
// Before
'name'         => 'required|string|min:2|max:255',
'email'        => ['required', 'email', Rule::unique('users')->ignore($id)],
'role'         => Rule::when($isAdmin, 'required|string|in:admin,editor'),
'items'        => 'array',
'items.*.id'   => 'required|integer|exists:items,id',
'items.*.name' => 'required|string|max:255',

// After
'name'  => FluentRule::string('Full Name')->required()->min(2)->max(255),
'email' => FluentRule::email('Email')->required()->unique('users', 'email', fn ($r) => $r->ignore($id)),
'role'  => FluentRule::string()->when($isAdmin, fn ($r) => $r->required()->in(['admin', 'editor'])),
'items' => FluentRule::array()->each([
    'id'   => FluentRule::integer()->required()->exists('items', 'id'),
    'name' => FluentRule::string()->required()->max(255),
]),
```

## Install

```bash
composer require laranail/validation
```

## Quick start

Add `HasFluentRules` to a form request and return fluent rules from `rules()`. The trait is
what enables the optimized path — without it the builders still work, they just compile to
ordinary Laravel rules.

```php
use Illuminate\Foundation\Http\FormRequest;
use Simtabi\Laranail\Validation\FluentRule;
use Simtabi\Laranail\Validation\HasFluentRules;
use Simtabi\Laranail\Validation\Rules\Banking\Iban;

final class StorePayoutRequest extends FormRequest
{
    use HasFluentRules;

    public function rules(): array
    {
        return [
            'account' => FluentRule::string('Account')->required()->rule(new Iban()),
            'email'   => FluentRule::email()->required()->notDisposable(),
            'lines'   => FluentRule::array()->required()->max(50)->each([
                'sku'    => FluentRule::string()->required()->exists('products', 'sku'),
                'amount' => FluentRule::numeric()->required()->min(0.01),
            ]),
        ];
    }
}
```

`$request->validated()` returns the same shape it always did. For Livewire use
`HasFluentValidation`; outside both, `RuleSet::from([...])->check($data)`.

## <a name="documentation"></a>Documentation

Full documentation is at
**[opensource.simtabi.com/documentation/laranail/validation](https://opensource.simtabi.com/documentation/laranail/validation/)**.

### Guides

- [Installation](docs/installation.md) — Composer install, supported versions, optional Boost skills
- [Getting started](docs/getting-started.md) — a form request end to end, then the same builders elsewhere
- [Configuration](docs/configuration.md) — the publishable config, opt-in string rule aliases, and the per-chain options
- [Architecture](docs/architecture.md) — builders, compiler, optimized execution, and why the fast path is safe
- [Performance](docs/performance.md) — what makes it fast, the benchmarks, and when none of it helps
- [Comparison](docs/comparison.md) — how this differs from rule strings and Laravel's `Rule` class
- [Troubleshooting](docs/troubleshooting.md) — common failure modes and their causes
- [Release](docs/release.md) — versioning, tagging, and the CI-managed changelog

### Reference

- [Rule reference](docs/tools/fluent-rule.md) — every entry point, modifier, conditional, and macro hook
- [Rule library](docs/tools/rule-library.md) — the extended rules: IBAN, IMEI, postal codes, IP classification, and the rest
- [Phone rule](docs/tools/phone-rule.md) — countries, line types, strictness, and E.164-normalised uniqueness
- [Person names](docs/tools/person-name.md) — any number of name fields, several names in one field, and "at least one"
- [Identity and network fields](docs/tools/identity-and-network.md) — usernames, URLs, IP and MAC addresses, and the email additions
- [`RuleSet`](docs/tools/rule-set.md) — build, compose, inspect, export, and validate
- [Array validation](docs/tools/array-validation.md) — `each()` for wildcards, `children()` for fixed keys
- [Error messages](docs/tools/error-messages.md) — labels and per-rule messages on the rule itself
- [Livewire](docs/tools/livewire.md) — the `HasFluentValidation` trait, plus Filament
- [Testing](docs/tools/testing.md) — `FluentRulesTester` and the Pest expectations
- [Static analysis](docs/tools/static-analysis.md) — the opt-in arch test for untyped `field()` chains

### Recipes

- [Migrate existing rules](docs/recipes/migrate-existing-rules.md) — convert incrementally; both forms coexist
- [Extend parent form-request rules](docs/recipes/extend-parent-form-request-rules.md) — add to or override inherited rules
- [Validate a phone number](docs/recipes/validate-a-phone-number.md) — country, line type, and the duplicate a plain `unique` misses
- [Reject profanity](docs/recipes/reject-profanity.md) — your word list, the package's matching
- [Contribute a locale](docs/recipes/contribute-a-locale.md) — the completeness bar every shipped language meets

### Project

- [Changelog](CHANGELOG.md) · [Upgrading](UPGRADING.md) · [Credits](CREDITS.md) · [Contributing](CONTRIBUTING.md) · [Security](SECURITY.md) · [Code of conduct](CODE_OF_CONDUCT.md)

## Stability

Pre-1.0. The **stable surface** — what 1.0 will cover under SemVer — is: `FluentRule`,
`FluentSchema`, `RuleSet` (including its events and `before()`/`after()` hooks), the rule
classes and their constructor signatures, the contracts (`ClientCheckable`,
`PrecognitionSkippable`, `TermList`, `FluentRuleContract`), `Check`, `Regex`,
`Validation::fake()`, `RuleRegistrar`, the console commands, and the `laranail.validation.*`
config keys.

Everything marked `@internal` — the fast-check compiler, the optimizer validators, the batch
machinery, everything under `Internal\` — may change in a minor, and an arch test enforces the
boundary. Build on the stable list; the optimizer is an implementation detail behind it.

Constrain to `^0.1` and read [UPGRADING.md](UPGRADING.md) before moving between versions: this
package fixes divergences from Laravel's own validator, and a fix can mean input an application
previously accepted is now correctly rejected.

## Local development

```bash
composer install
composer test          # Pest
composer phpstan       # level max, 100% type coverage
composer format        # Pint
composer rector
php benchmark.php      # the optimizer's headline numbers
```

Tests run on Orchestra Testbench — there is no host application, so use `vendor/bin/testbench`
rather than `php artisan`. Fixtures live under `workbench/`.

## Sister packages

| Package | What it owns |
|---|---|
| [`laranail/atlas`](https://github.com/laranail/atlas) | Country, currency and language data — and the rules over it |
| [`laranail/enumerator`](https://github.com/laranail/enumerator) | Enum values, names and transitions |
| [`laranail/chrono`](https://github.com/laranail/chrono) | Timezones, date existence and ambiguity |
| [`laranail/toolkit`](https://github.com/laranail/toolkit) | Password strength and common-password rejection |
| [`laranail/captcha`](https://github.com/laranail/captcha) | Turnstile, hCaptcha and reCAPTCHA |
| [`laranail/email`](https://github.com/laranail/email) | Maintained disposable and role-account lists, and a production DNS resolver |
| [`laranail/package-tools`](https://github.com/laranail/package-tools) | The package scaffolding this one is built on |

Email deliverability ships here as `Rules\Network\DeliverableEmail`, over a bundled cached
resolver. [`laranail/email`](https://github.com/laranail/email) supplies maintained
disposable-domain and role-account lists and a production resolver, replacing those fallbacks
through the same contracts — installing it changes nothing you call.

## Community

Questions and ideas belong in
[Discussions](https://github.com/laranail/validation/discussions); bugs in
[Issues](https://github.com/laranail/validation/issues).

## Credits

Originally written by [Sander Muller](https://github.com/sandermuller), and maintained here under
the laranail org with thanks to
[all contributors](https://github.com/laranail/validation/graphs/contributors).

## Contributing & security

Issues and PRs are welcome — see [CONTRIBUTING.md](CONTRIBUTING.md). Report vulnerabilities per
[SECURITY.md](SECURITY.md) (opensource@simtabi.com); participation follows the
[Code of Conduct](CODE_OF_CONDUCT.md).

## License

MIT © Simtabi LLC. See [LICENSE](LICENSE).
