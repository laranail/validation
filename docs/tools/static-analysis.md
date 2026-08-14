# Static analysis

An opt-in arch test that catches the one mistake the type system cannot: chaining a
type-specific method onto the untyped `FluentRule::field()` builder.

## The footgun

Every builder uses Laravel's `Macroable`, which means an unknown method call is routed to
`__call` rather than rejected at compile time. `FluentRule::field()` is the untyped escape
hatch, so it accepts any modifier — including ones that only make sense on a typed builder:

```php
FluentRule::field()->digits(5);      // digits() belongs on numeric()
FluentRule::field()->mimes('pdf');   // mimes() belongs on file()
```

These parse, pass static analysis, and only surface at runtime. The package raises
`TypedBuilderHint` when it can, but the reliable fix is to catch them at CI time across the
whole codebase.

## `BansFieldRuleTypeMethods`

`Simtabi\Laranail\Validation\Testing\Arch\BansFieldRuleTypeMethods` walks the PHP files under
the paths you give it and returns every file containing such a chain. An empty result means the
codebase is clean.

```php
use Simtabi\Laranail\Validation\Testing\Arch\BansFieldRuleTypeMethods;

arch('FluentRule::field() does not chain type-specific methods')
    ->expect(BansFieldRuleTypeMethods::scope('app/'))
    ->toBeEmpty();
```

It returns a plain array of file paths, so it works from PHPUnit just as well:

```php
public function test_no_untyped_type_specific_chains(): void
{
    $this->assertSame([], BansFieldRuleTypeMethods::scope('app/'));
}
```

> Requires `nikic/php-parser`, listed under `suggest` in `composer.json` rather than pulled in
> as a hard dependency. Without it the helper throws a `RuntimeException` carrying the install
> command.

```bash
composer require --dev nikic/php-parser
```

## The rest of the surface is checked by PHPStan

Beyond this helper the package carries no bespoke analysis rules. The typed builders are the
mechanism: because `FluentRule::string()` returns a `StringRule` with no `mimes()` on it, an
invalid chain on a *typed* builder is already a PHPStan error with no extra configuration. Only
`field()` needs the arch test, precisely because it is deliberately untyped.

The package's own suite runs PHPStan at level `max` with `treatPhpDocTypesAsCertain: false`,
strict rules, and 100% type coverage on returns, params, properties and `declare` — see
[Contributing](../../CONTRIBUTING.md) for running the same locally.

---

[← Docs index](../../README.md#documentation)
