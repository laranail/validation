# Troubleshooting

Common failure modes and what causes them.

**`validated()` is missing nested keys (children, each)**
Add `use HasFluentRules` to your form request. Without the trait, FluentRule objects self-validate in isolation and nested keys don't appear in `validated()` output.

**Labels not working ("The name field" instead of "The Full Name field")**
Add `use HasFluentRules`. The trait extracts labels from rule objects and passes them to the validator. Without it, labels are only used inside the rule's self-validation.

**Cross-field wildcard references don't work (`requiredUnless('items.*.type', ...)`)**
These require `HasFluentRules` or `FluentValidator` to resolve wildcard paths. Standalone FluentRule objects self-validate in isolation.

**Child form request loses or corrupts parent rules**
`array_merge_recursive` flattens FluentRule objects into arrays. See [Extending parent rules](recipes/extend-parent-form-request-rules.md) for the supported merge patterns (spread, clone, `modifyEach`, `modifyChildren`).

**Method not found on a rule type**
Use `->rule('method_name')` as an escape hatch for any Laravel rule not yet available as a fluent method. Accepts strings, objects, and `['rule', ...$params]` tuples.
If you think it should be a native method, [open an issue](https://github.com/laranail/validation/issues) and we'll add it.

**`UnknownFluentRuleMethod: FluentRule::field() has no method ...()`**
`FluentRule::field()` is the untyped builder; type-specific rules (`min`, `max`, `regex`, `email`, `digits`, `mimes`, `before`, `after`, `contains`) live on the typed builders. The exception message names the builders that expose the method. Pick the one matching your field's type:

```php
FluentRule::numeric()->required()->min(5);   // numeric value
FluentRule::string()->required()->min(5);    // string length
FluentRule::array()->required()->min(5);     // element count
FluentRule::file()->required()->min('2mb');  // file size
```

The smell-form `FluentRule::field()->rule('min:1')` (or any `->rule('some_type_rule:...')` on `field()`) works at runtime but is non-idiomatic. Pick the typed builder. To catch these across a codebase at CI time, see [Static analysis](tools/static-analysis.md).

**`HasFluentValidation` conflicts with Filament's `InteractsWithForms` / `InteractsWithSchemas`**
Use `HasFluentValidationForFilament` instead, with the `insteadof` block. See [Livewire → Filament components](tools/livewire.md).

**Converting a large existing rule set**
There is no automated converter for this package. Convert incrementally — fluent builders and
plain rule strings coexist in the same array, so a partially migrated `rules()` is valid at every
step. See [Migrate existing rules](recipes/migrate-existing-rules.md).


---

[← Docs index](../README.md#documentation)
