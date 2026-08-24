# Contribute a locale

The package ships English; additional locales are welcome as contributions, and the suite
holds them to the same completeness bar as `en`.

## Add the language file

Copy the English file and translate every sentence, keeping the keys and `:placeholders`
exactly as they are:

```bash
cp resources/lang/en/validation.php resources/lang/de/validation.php
```

Keys mirror the rule's snake_case name and stay **flat** — a nested structure makes a missing
key render as a bare dotted string instead of failing loudly in tests.

## The completeness bar

`tests/RuleMessagesResolveTest.php` asserts every message key a rule can emit resolves to a
real sentence. It runs against each shipped locale, so a partial translation is a red build,
not a file that quietly falls back to English for half its rules. Run it before opening the
PR:

```bash
composer test -- --filter=RuleMessagesResolve
```

## Overriding locally instead

An application that only needs a few translated messages does not need a locale contribution —
publish and edit:

```bash
php artisan vendor:publish --tag=laranail::validation-translations
```

Laravel reads overrides from `lang/vendor/laranail-validation/{locale}/validation.php`.

---

[← Docs index](../../README.md#documentation)
