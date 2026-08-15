# laranail/validation

[![Latest version on Packagist](https://img.shields.io/packagist/v/laranail/validation.svg)](https://packagist.org/packages/laranail/validation)
[![Tests](https://github.com/laranail/validation/actions/workflows/run-tests.yml/badge.svg)](https://github.com/laranail/validation/actions/workflows/run-tests.yml)
[![Static analysis](https://github.com/laranail/validation/actions/workflows/phpstan.yml/badge.svg)](https://github.com/laranail/validation/actions/workflows/phpstan.yml)
[![License: MIT](https://img.shields.io/badge/license-MIT-blue.svg)](LICENSE)

> Write Laravel validation rules with IDE autocompletion instead of memorising string syntax — each rule type exposes only the methods that apply to it, `each()` and `children()` keep parent and child rules in one place, and large wildcard arrays validate up to 160x faster.

Targets PHP `^8.4.1` on Laravel `^13`.

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
- [Rule library](docs/tools/rule-library.md) — the 38 extended rules: IBAN, IMEI, postal codes, IP classification, and the rest
- [Phone rule](docs/tools/phone-rule.md) — countries, line types, strictness, and E.164-normalised uniqueness
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

### Project

- [Changelog](CHANGELOG.md) · [Upgrading](UPGRADING.md) · [Credits](CREDITS.md) · [Contributing](CONTRIBUTING.md) · [Security](SECURITY.md) · [Code of conduct](CODE_OF_CONDUCT.md)

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
