# Reject profanity

Match the words your product bans — with the obfuscation folding and false-positive handling done
for you — while the word list itself stays yours.

**No word list ships with this package**, deliberately: the usable open lists are LGPL or
unlicensed, and what counts as unacceptable is a product decision that changes by audience and
over time. The package ships the *matching*; you supply the *words*. See the
`Contracts\TermList` docblock for the full reasoning.

## Inline, for a handful of terms

```php
use Simtabi\Laranail\Validation\Rules\Profanity\NoProfanity;

public function rules(): array
{
    return [
        'display_name' => ['required', 'string', new NoProfanity(['badword', 'worseword'])],
    ];
}
```

## Bound once, used everywhere

Keep the list in config (or load it from a table) and bind the contract; every `NoProfanity`
that receives a `TermList` then reads one source:

```php
use Simtabi\Laranail\Validation\Contracts\TermList;
use Simtabi\Laranail\Validation\Support\InlineTermList;

// AppServiceProvider::register()
$this->app->singleton(TermList::class, fn () => new InlineTermList(
    terms: config('moderation.blocked_terms'),
    allowed: config('moderation.allowed_words'),
));

// The rule
new NoProfanity(app(TermList::class));
```

## The Scunthorpe problem

The matcher already refuses to fire inside ordinary words — `assess` never matches a list
containing `ass`, because matching is word-aware, with character-substitution folding on top
(`b4dger` is `badger`). `allowed` handles the residue: values that legitimately ARE a match by
those rules and must pass anyway — a place name, a phrase your product uses:

```php
new InlineTermList(terms: ['badger'], allowed: ['badger badger']);
```

## In tests

`InlineTermList` doubles as the test fixture — construct it with two terms and assert behaviour;
there is no separate fake to learn:

```php
it('rejects a banned display name', function (): void {
    $rule = new NoProfanity(new InlineTermList(['badword']));

    // …drive it with FluentRulesTester or a plain Validator.
});
```

---

[← Docs index](../../README.md#documentation)
