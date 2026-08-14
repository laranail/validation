# Changelog

All notable changes to `laranail/validation` are documented in this file.

The format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/) and this project
adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

Entries below `Unreleased` are written by CI from the GitHub release body — see
[docs/release.md](docs/release.md). Do not hand-edit released sections.

## Unreleased

### Added

- Initial laranail release. Type-aware fluent rule builders (`FluentRule` and the eleven
  `Rules\*` classes), structured array validation via `each()` and `children()`, labels and
  per-rule messages carried on the rule itself, the `RuleSet` compiler, the optimized wildcard
  validator behind `HasFluentRules`, the Livewire bridge, and the `FluentRulesTester` /
  Pest testing helpers.
- Four Laravel Boost skills shipped under `resources/boost/skills/`, namespaced
  `laranail-validation*`.

[Unreleased]: https://github.com/laranail/validation/commits/main
