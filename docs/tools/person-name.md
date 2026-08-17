# Person names

Validating a name across however many fields a system has, and letting one field hold more than
one name.

Two pieces, and they are usable separately. `Rules\Text\PersonName` answers for one value —
is this a name, and does it hold the right number of them. `Schemas\PersonNameSchema` answers
for the set — which fields exist, and whether the person supplied any name at all.

## The assumption worth dropping first

A name is not one value and it is not three. Some people have one name; some have four given
names; some have two family names; some have a patronymic between the two. The `first`/`middle`/
`last` split is a guess about one naming culture, and a validator that hard-codes it is wrong
about a large fraction of the world.

So the field set is declared by the caller and nothing is assumed:

```php
use Simtabi\Laranail\Validation\Schemas\PersonNameSchema;

PersonNameSchema::make();                                       // first_name, middle_name, last_name
PersonNameSchema::make('given_name', 'family_name');
PersonNameSchema::make('first_name', 'middle_name', 'last_name', 'suffix');
PersonNameSchema::make('full_name');
PersonNameSchema::list('names');                                // an unbounded list
```

## In a form request

The schema produces a `RuleSet`, so it composes with everything else:

```php
use Illuminate\Foundation\Http\FormRequest;
use Simtabi\Laranail\Validation\FluentRule;
use Simtabi\Laranail\Validation\HasFluentRules;
use Simtabi\Laranail\Validation\RuleSet;
use Simtabi\Laranail\Validation\Schemas\PersonNameSchema;

final class StoreContactRequest extends FormRequest
{
    use HasFluentRules;

    public function rules(): RuleSet
    {
        return PersonNameSchema::make()->toRuleSet()->merge([
            'email' => FluentRule::email()->required(),
        ]);
    }

    protected function prepareForValidation(): void
    {
        $this->merge(PersonNameSchema::make()->normalise($this->all()));
    }
}
```

Outside a form request, `->toRuleSet()->check($data)` or `->validate($data)` work the same way.

## At least one name, not one particular name

The default is `requireAtLeastOne()`, and it is the interesting mode. Making any single column
mandatory forces a placeholder into it — a column full of `.` is a null that lies — while
requiring nothing lets a person exist with no name at all.

```php
PersonNameSchema::make()->requireAtLeastOne();   // the default
PersonNameSchema::make()->requireAll();          // every field
PersonNameSchema::make()->optional();            // a partial update
```

The requirement attaches to the **first** declared field only. Attaching it to all of them
reports one mistake once per field, and a form showing three copies of "provide at least one
name" reads as three problems.

The message names the fields the schema was actually given, because the stock one — "The first
name field is required when none of middle name / last name are present" — describes the
constraint rather than the fix:

```
Please provide at least one of first name, middle name or last name.
```

Override it with `requireAtLeastOne('We need something to call you.')`, and set the field names
it uses with `labels(['given_name' => 'first name'])`.

> **Give a form the fields it actually shows.** A form with two of the three boxes wants
> `PersonNameSchema::make('first_name', 'last_name')`, not a slice of the three-field rules.
> Slicing leaves the requirement pointing at a field that form does not have: it behaves, because
> an absent field counts as absent, but the message names a box the reader cannot see.

## Why every field is `nullable`, including the one carrying the requirement

This looks like it would cancel the requirement. It does not, and the asymmetry is the reason
the schema exists at all.

`required_without_all` is **implicit**, so it runs on an absent or null value. `string` and
`max` are not, so without `nullable` they run on a legitimately null first name and reject it —
telling someone with only a family name that "the first name field must be a string", which
names neither the rule nor the fix.

Both halves have been live bugs, and each looks like the fix for the other. Getting the first
wrong means a payload with no name validates cleanly and only a database constraint catches it,
with a 500 instead of a message. Getting the second wrong means half the valid name
combinations are refused.

## More than one name in one field

By default a field accepts any number of whitespace-separated names, and that default is
load-bearing: a person with three given names has to put them somewhere, and a form with one
`middle_name` box is where they go. `Ada Byron Gordon` is a correct answer to that question.

```php
PersonNameSchema::make();                          // one name or many — the default
PersonNameSchema::make()->singleNamePerField();    // exactly one, for a strict column
PersonNameSchema::make('full_name')->names(2);     // must carry a surname
PersonNameSchema::make()->names(1, 3);             // up to three
```

Counting is by whitespace run after trimming, so `Ada  Byron` is two names rather than three.
A count failure reports the count — "The full name must contain at least 2 names." — rather
than the character message, which would send the user hunting for a bad character that is not
there.

## A name as a list

For systems that store name parts as rows rather than columns:

```php
PersonNameSchema::list('names', max: 10);
```

Every entry is checked as a name, and the list itself must carry at least one. `$max` caps the
submission rather than the person: without one, a single request can carry an arbitrary number
of entries. Normalise with `normaliseList()` — `normalise()` throws for a list schema rather
than silently handing the input back.

## What counts as a name

`Rules\Text\PersonName` is permissive about letters and strict about everything else. The letter
class is Unicode (`\p{L}` plus combining marks `\p{M}`), not `[a-z]`, so `O'Neill`, `Jean-Luc`,
`Müller`, `李` and `Ait Ben Haddou` all pass. Spaces, apostrophes, hyphens and full stops are
allowed; digits, emoji, other symbols and markup punctuation are not. At least one actual letter
is required, so `'-.` fails.

```php
new PersonName();                    // one name or many
PersonName::single();                // exactly one
PersonName::names(2, 3);             // two or three
new PersonName(allowDigits: true);   // a regnal suffix, a legal entity
```

Digits are rejected by default because they are almost always a paste error or a bot.
`allowDigits` exists for the systems that genuinely carry them, and
`PersonNameSchema::allowDigits()` passes it through.

For an existing table whose rows would not pass the character check, `withoutCharacterCheck()`
keeps presence and length and drops the rest. Validating new input against a rule the stored
data violates turns every edit of an old record into an error the user cannot resolve.

## In the browser

`PersonName` implements [`ClientCheckable`](../architecture.md), so
[`laranail/validation-js`](https://github.com/laranail/validation-js) runs it client-side. It
advertises its **own** patterns — the character class, the at-least-one-letter test, and the
name count when bounded — rather than a hand-written JavaScript twin, so there is one source of
truth and nothing to drift.

---

[← Docs index](../../README.md#documentation)
