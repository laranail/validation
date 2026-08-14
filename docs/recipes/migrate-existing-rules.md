# Migrate existing rules by hand

Convert a `rules()` array to fluent builders incrementally — both forms can coexist in one array.

You don't need to convert all your rules at once. Fluent rules mix freely with string rules and native rule objects in the same array:

```php
$rules = [
    'name'   => FluentRule::string()->required()->min(2)->max(255),  // fluent
    'email'  => 'required|string|email|max:255',               // string, still works
    'role'   => ['required', LaravelRule::in(['admin', 'user'])],  // array, still works
];
```

**Step 1:** Add `use HasFluentRules` to your form request. This works even before you convert any rules.

**Step 2:** Convert fields. Start with the ones that benefit most from autocompletion: complex conditionals, date comparisons, nested arrays. Common conversions:

| Before                                              | After                                                                     |
|-----------------------------------------------------|---------------------------------------------------------------------------|
| `'items.*.name' => 'required\|string'`              | `FluentRule::array()->each(['name' => FluentRule::string()->required()])` |
| `'search' => 'array'` and `'search.value' => '...'` | `FluentRule::array()->children(['value' => ...])`                         |
| `Rule::in([...])`                                   | `->in([...])` or `->in(MyEnum::class)`                                    |
| `Rule::unique('users')`                             | `->unique('users')`                                                       |
| `Rule::forEach(fn () => ...)`                       | `FluentRule::array()->each(...)`                                          |

All conditional methods (`requiredIf`, `excludeUnless`, etc.) accept `Closure|bool` in addition to field references. `each()` and `children()` nest naturally. Flat dot-notation keys like `columns.*.data.sort` become nested `each([...children([...])])` trees that mirror the data shape.

> [!TIP]
> **Using Boost?** If you have [Laravel Boost](https://github.com/laravel/boost) installed, ask your AI assistant to run the `fluent-validation-optimize` skill. It scans your codebase for convertible rules, prioritizes by impact, and applies changes file by file.

**Step 3:** For rules without a direct fluent method, use the `rule()` escape hatch:

```php
FluentRule::string()->rule('email:rfc,dns')           // string rule
FluentRule::string()->rule(new MyCustomRule())         // object rule
FluentRule::file()->rule(['mimetypes', ...$types])     // array tuple
```


---

[← Docs index](../../README.md#documentation)
