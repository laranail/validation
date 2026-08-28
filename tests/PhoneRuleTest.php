<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Simtabi\Laranail\Validation\FluentRule;
use Simtabi\Laranail\Phone\Enums\PhoneNumberType;
use Simtabi\Laranail\Validation\Rules\Telecom\UniquePhone;

// =========================================================================
// PhoneRule
// =========================================================================

it('accepts a valid international number and rejects nonsense', function (): void {
    expect(makeValidator(['phone' => '+254712123456'], ['phone' => FluentRule::phone()->required()])->passes())->toBeTrue()
        ->and(makeValidator(['phone' => 'not a number'], ['phone' => FluentRule::phone()->required()])->passes())->toBeFalse();
});

it('accepts any country by default', function (string $number): void {
    expect(makeValidator(['phone' => $number], ['phone' => FluentRule::phone()])->passes())->toBeTrue();
})->with([
    'Kenya'          => ['+254712123456'],
    'Türkiye'        => ['+905301111111'],
    'United Kingdom' => ['+447400123456'],
    'Brazil'         => ['+5511961234567'],
]);

// =========================================================================
// Country constraints
// =========================================================================

it('rejects a number from a country that is not allowed', function (): void {
    $rule = FluentRule::phone()->country('KE');

    expect(makeValidator(['phone' => '+254712123456'], ['phone' => $rule])->passes())->toBeTrue()
        ->and(makeValidator(['phone' => '+905301111111'], ['phone' => $rule])->passes())->toBeFalse();
});

it('accepts any of several allowed countries', function (): void {
    $rule = FluentRule::phone()->country(['KE', 'TZ', 'UG']);

    expect(makeValidator(['phone' => '+254712123456'], ['phone' => $rule])->passes())->toBeTrue()
        ->and(makeValidator(['phone' => '+256712345678'], ['phone' => $rule])->passes())->toBeTrue()
        ->and(makeValidator(['phone' => '+447400123456'], ['phone' => $rule])->passes())->toBeFalse();
});

/*
| A single configured country doubles as the parse hint, so a bare national number validates without
| the caller having to write it in international form.
*/
it('parses a bare national number against a single configured country', function (): void {
    expect(makeValidator(['phone' => '0712 123456'], ['phone' => FluentRule::phone()->country('KE')])->passes())
        ->toBeTrue();
});

it('reads the country from a sibling field', function (): void {
    $rules = ['phone' => FluentRule::phone()->countryFrom('phone_country')];

    expect(makeValidator(['phone' => '0712 123456', 'phone_country' => 'KE'], $rules)->passes())->toBeTrue()
        // The same digits are not a valid British number.
        ->and(makeValidator(['phone' => '0712 123456', 'phone_country' => 'GB'], $rules)->passes())->toBeFalse();
});

it('normalises the country code read from a sibling field', function (): void {
    expect(makeValidator(
        ['phone' => '0712 123456', 'phone_country' => 'ke'],
        ['phone' => FluentRule::phone()->countryFrom('phone_country')],
    )->passes())->toBeTrue();
});

// =========================================================================
// Line types
// =========================================================================

it('constrains the line type', function (): void {
    $mobile = FluentRule::phone()->mobile();

    expect(makeValidator(['phone' => '+905301111111'], ['phone' => $mobile])->passes())->toBeTrue()
        // A Turkish landline.
        ->and(makeValidator(['phone' => '+902125111111'], ['phone' => $mobile])->passes())->toBeFalse();
});

/*
| The regression that catches most implementations. In the NANP mobile and fixed-line share ranges,
| so libphonenumber reports FIXED_LINE_OR_MOBILE and a strict `=== MOBILE` comparison rejects every
| valid North American mobile.
*/
it('treats an ambiguous NANP number as satisfying both mobile and fixed-line', function (): void {
    expect(makeValidator(['phone' => '+12125551234'], ['phone' => FluentRule::phone()->mobile()])->passes())->toBeTrue()
        ->and(makeValidator(['phone' => '+12125551234'], ['phone' => FluentRule::phone()->fixedLine()])->passes())->toBeTrue();
});

it('accepts a list of acceptable types', function (): void {
    $rule = FluentRule::phone()->type([PhoneNumberType::Mobile, PhoneNumberType::TollFree]);

    expect(makeValidator(['phone' => '+905301111111'], ['phone' => $rule])->passes())->toBeTrue()
        ->and(makeValidator(['phone' => '+902125111111'], ['phone' => $rule])->passes())->toBeFalse();
});

// =========================================================================
// Strictness
// =========================================================================

/*
| Possible-but-not-valid is where real data lives: a newly allocated range is correctly shaped for
| months before Google's metadata knows about it. `possible()` is the setting for a signup form,
| where turning away a real customer costs more than accepting an unreachable number.
*/
it('separates possible from valid', function (): void {
    $unallocated = '+254011111111';

    expect(makeValidator(['phone' => $unallocated], ['phone' => FluentRule::phone()])->passes())->toBeFalse()
        ->and(makeValidator(['phone' => $unallocated], ['phone' => FluentRule::phone()->possible()])->passes())->toBeTrue();
});

it('can be switched back to strict', function (): void {
    expect(makeValidator(['phone' => '+254011111111'], ['phone' => FluentRule::phone()->possible()->strict()])->passes())
        ->toBeFalse();
});

// =========================================================================
// Extensions
// =========================================================================

it('accepts an extension by default and rejects one on request', function (): void {
    $number = '+1 555 123 4567 x890';

    expect(makeValidator(['phone' => $number], ['phone' => FluentRule::phone()->possible()])->passes())->toBeTrue()
        ->and(makeValidator(['phone' => $number], ['phone' => FluentRule::phone()->possible()->withoutExtension()])->passes())
        ->toBeFalse();
});

// =========================================================================
// Presence modifiers still work
// =========================================================================

it('composes with the shared presence modifiers', function (): void {
    expect(makeValidator(['phone' => null], ['phone' => FluentRule::phone()->nullable()])->passes())->toBeTrue()
        ->and(makeValidator([], ['phone' => FluentRule::phone()->required()])->passes())->toBeFalse()
        ->and(makeValidator(['phone' => ''], ['phone' => FluentRule::phone()->required()])->passes())->toBeFalse();
});

it('normalises what the user actually pasted', function (string $input): void {
    expect(makeValidator(['phone' => $input], ['phone' => FluentRule::phone()->country('KE')])->passes())->toBeTrue();
})->with([
    'international'   => ['+254712123456'],
    'IDD prefix'      => ['00254712123456'],
    'spaced national' => ['0712 123 456'],
    'punctuated'      => ['(0712) 123-456'],
    // Typed on an Arabic keyboard. None of the surveyed packages handles this.
    'Arabic-Indic digits' => ['٠٠٢٥٤٧١٢١٢٣٤٥٦'],
]);

// =========================================================================
// Messages
// =========================================================================

it('reports why the number was rejected, not merely that it was', function (): void {
    $countryFail = makeValidator(['phone' => '+905301111111'], ['phone' => FluentRule::phone()->country('KE')]);
    $countryFail->passes();

    $typeFail = makeValidator(['phone' => '+902125111111'], ['phone' => FluentRule::phone()->mobile()]);
    $typeFail->passes();

    // Distinct keys, so the user is told the thing to change. All three upstream Filament phone
    // packages ship no messages at all.
    expect($countryFail->errors()->first('phone'))->toContain('KE')
        ->and($typeFail->errors()->first('phone'))->toContain('mobile');
});

it('honours a custom message', function (): void {
    $v = makeValidator(['phone' => 'nonsense'], ['phone' => FluentRule::phone()->message('Give us a real number.')]);
    $v->passes();

    expect($v->errors()->first('phone'))->toBe('Give us a real number.');
});

it('honours a label', function (): void {
    $v = makeValidator(['phone' => 'nonsense'], ['phone' => FluentRule::phone()->label('mobile number')]);
    $v->passes();

    expect($v->errors()->first('phone'))->toContain('mobile number');
});

// =========================================================================
// Uniqueness
// =========================================================================

/**
 * The reason `unique()` is overridden at all. Laravel's compares the attribute exactly as it
 * arrived, so a stored `+254712123456` and a typed `0712 123456` are different strings and the query
 * finds nothing — you get a duplicate contact and no way to see why from the table.
 */
it('collides across formats', function (): void {
    Schema::create('contacts', function (Blueprint $table): void {
        $table->id();
        $table->string('phone');
    });

    DB::table('contacts')->insert(['phone' => '+254712123456']);

    $rules = ['phone' => FluentRule::phone()->country('KE')->unique('contacts', 'phone')];

    // Every one of these is the same number wearing different clothes.
    foreach (['0712 123456', '+254712123456', '+254 712 123456', '00254712123456'] as $spelling) {
        expect(validator(['phone' => $spelling], $rules)->fails())
            ->toBeTrue("[{$spelling}] should have collided with the stored E.164 value");
    }
});

it('lets a genuinely different number through', function (): void {
    Schema::create('contacts', function (Blueprint $table): void {
        $table->id();
        $table->string('phone');
    });

    DB::table('contacts')->insert(['phone' => '+254712123456']);

    expect(validator(
        ['phone' => '0722 123456'],
        ['phone' => FluentRule::phone()->country('KE')->unique('contacts', 'phone')],
    )->fails())->toBeFalse();
});

it('ignores the row being edited', function (): void {
    Schema::create('contacts', function (Blueprint $table): void {
        $table->id();
        $table->string('phone');
    });

    $id = DB::table('contacts')->insertGetId(['phone' => '+254712123456']);

    // Without ignore(), an edit form always collides with itself.
    expect(validator(
        ['phone' => '0712 123456'],
        ['phone' => FluentRule::phone()->country('KE')->unique('contacts', 'phone', function (UniquePhone $rule) use ($id): void {
            $rule->ignore($id);
        })],
    )->fails())->toBeFalse();
});

it('takes the country from a sibling field', function (): void {
    Schema::create('contacts', function (Blueprint $table): void {
        $table->id();
        $table->string('phone');
    });

    DB::table('contacts')->insert(['phone' => '+254712123456']);

    expect(validator(
        ['phone' => '0712 123456', 'phone_country' => 'KE'],
        ['phone' => FluentRule::phone()->countryFrom('phone_country')->unique('contacts', 'phone')],
    )->fails())->toBeTrue();
});

/**
 * Junk is not a duplicate — it is not a number, so it cannot collide with one. Reporting the same
 * input twice for two different reasons only obscures which one the user has to fix.
 */
it('does not report unparseable input as a duplicate', function (): void {
    Schema::create('contacts', function (Blueprint $table): void {
        $table->id();
        $table->string('phone');
    });

    DB::table('contacts')->insert(['phone' => '+254712123456']);

    $errors = validator(
        ['phone' => 'call reception'],
        ['phone' => FluentRule::phone()->country('KE')->unique('contacts', 'phone')],
    )->errors()->get('phone');

    expect($errors)->toHaveCount(1)
        ->and($errors[0])->not->toContain('already been taken');
});
