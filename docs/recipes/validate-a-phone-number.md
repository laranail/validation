# Validate a phone number

Accept the number, reject the wrong country and the wrong line type, and catch the duplicate a plain
`unique` would miss.

Install `laranail/phone` alongside this package — it is a suggest, because it carries
libphonenumber's numbering-plan metadata:

```bash
composer require laranail/phone
```

## The common case

```php
use Simtabi\Laranail\Validation\FluentRule;

public function rules(): array
{
    return [
        'phone' => FluentRule::phone()->required()->country('KE')->mobile(),
    ];
}
```

## A country picker beside the field

```php
return [
    'phone_country' => FluentRule::string()->required()->size(2),
    'phone'         => FluentRule::phone()->required()->countryFrom('phone_country')->mobile(),
];
```

`countryFrom()` reads the sibling at validation time, so the rule follows whatever the user actually
picked rather than a country the developer assumed.

## A global form with no picker

```php
FluentRule::phone()->required()->international();
```

Requires the input to carry its own calling code. Better than guessing a default country: a wrong
guess does not fail loudly, it silently files one country's numbers under another.

## No duplicates

```php
FluentRule::phone()->required()->country('KE')->unique('contacts', 'phone');
```

The phone rule's `unique()` normalises to E.164 before querying. Laravel's generic one does not, so
`0712 123456` sails past a stored `+254712123456`.

On an edit form, exclude the record being edited:

```php
use Simtabi\Laranail\Validation\Rules\Telecom\UniquePhone;

FluentRule::phone()
    ->required()
    ->unique('contacts', 'phone', fn (UniquePhone $rule) => $rule->ignore($this->route('contact')->id));
```

## Storing what you validated

Validation does not normalise the value — it only judges it. Convert before writing, or let an
Eloquent cast do it:

```php
use Simtabi\Laranail\Phone\Casts\AsPhoneNumber;

protected function casts(): array
{
    return ['phone' => AsPhoneNumber::class . ':phone_country'];
}
```

The column then holds E.164 whatever the user typed. See
[`laranail/phone` → Store E.164 and a country column](https://opensource.simtabi.com/documentation/laranail/phone/recipes/store-e164-and-country).

## Testing it

Use real example numbers rather than invented ones:

```php
use Simtabi\Laranail\Phone\PhoneNumberFactory;

$factory = app(PhoneNumberFactory::class);

$factory->e164('KE');      // valid
$factory->invalid('KE');   // correctly shaped, unallocated
$factory->junk();          // not a number at all
```

`+15551234567` is **not** a valid US number — 555-1234 is unallocated, so `isValidNumber()` rejects
it. A fixture built from it tests your validator rather than your feature, and the failure looks like
a bug in your code.

Feed all three. `junk()` proves nothing throws; `invalid()` proves the rule rejects a *plausible*
number rather than only obvious rubbish.

## Relaxing for a new numbering range

```php
FluentRule::phone()->required()->possible();
```

Checks the shape rather than the allocation, for plans that move faster than libphonenumber's release
cadence. The cost is letting through numbers that look right and do not exist yet.

---

[← Docs index](../../README.md#documentation)
