<?php declare(strict_types=1);

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Validator;
use Simtabi\Laranail\Validation\Contracts\Payment\CardBrandCatalogue;
use Simtabi\Laranail\Validation\Rules\Payment\CardCvc;
use Simtabi\Laranail\Validation\Rules\Payment\CardExpiry;
use Simtabi\Laranail\Validation\Rules\Payment\CardNumber;
use Simtabi\Laranail\Validation\Support\Payment\CardBrand;

afterEach(function (): void {
    Carbon::setTestNow();
});

// =========================================================================
// CardNumber — brand identification, length, Luhn
// =========================================================================

it('accepts valid numbers across the brand catalogue', function (string $number, string $brand): void {
    expect(ruleAccepts(new CardNumber(), $number))->toBeTrue()
        ->and(resolve(CardBrandCatalogue::class)->identify(preg_replace('/\D/', '', $number) ?? '')?->name)
        ->toBe($brand);
})->with([
    ['4242424242424242', 'visa'],
    ['4222222222222', 'visa'],               // the 13-digit Visa length
    ['5555555555554444', 'mastercard'],
    ['2223003122003222', 'mastercard'],      // the 2-series range
    ['378282246310005', 'amex'],
    ['6011111111111117', 'discover'],
    ['6221260000000000', 'discover'],        // low end of 622126-622925
    ['6212340000000001', 'unionpay'],        // 62, outside the Discover range
    ['30569309025904', 'dinersclub'],
    ['3530111333300000', 'jcb'],
    ['2200000000000004', 'mir'],
    ['9792000000000003', 'troy'],
    ['5019717010103742', 'dankort'],
    ['6759649826438453', 'maestro'],
    ['6062826786276634', 'hipercard'],
    ['6007220000000004', 'forbrugsforeningen'],
    ['4026000000000002', 'visaelectron'],
    ['4242 4242 4242 4242', 'visa'],         // spaces and hyphens are stripped
    ['4242-4242-4242-4242', 'visa'],
]);

it('rejects failing checksums, wrong lengths and unknown ranges', function (mixed $value): void {
    expect(ruleAccepts(new CardNumber(), $value))->toBeFalse();
})->with([
    '4242424242424241',    // Luhn fails
    '424242424242424',     // 15 digits is not a Visa length
    '1234567890123452',    // Luhn-valid but no brand ranges start with 1
    '9791000000000004',    // 9791 is not Troy (the legacy pattern matched ALL of 9xx)
    'not a card',
    4242424242424242,      // numbers arrive as strings; an int is a coding error
    null,
]);

it('can be restricted to named brands', function (): void {
    $visaOnly = new CardNumber(brands: ['visa']);

    expect(ruleAccepts($visaOnly, '4242424242424242'))->toBeTrue()
        ->and(ruleAccepts($visaOnly, '5555555555554444'))->toBeFalse();
});

it('separates the failure messages a cardholder can act on', function (): void {
    // The legacy engine's typed exceptions, folded into distinct message
    // keys: a wrong length and a failed checksum send the user to
    // different corrections.
    $messages = static fn (string $number): string => Validator::make(
        ['card' => $number],
        ['card' => new CardNumber()],
    )->errors()->first('card');

    expect($messages('424242424242424'))->toContain('digits')
        ->and($messages('4242424242424241'))->toContain('check')
        ->and($messages('1234567890123452'))->toContain('recognised');
});

it('honours a bound catalogue', function (): void {
    $this->app->instance(CardBrandCatalogue::class, new class implements CardBrandCatalogue {
        public function brands(): array
        {
            return [$this->store()];
        }

        public function identify(string $number): ?CardBrand
        {
            return str_starts_with($number, '999') ? $this->store() : null;
        }

        private function store(): CardBrand
        {
            return new CardBrand('storecard', 'Store Card', [['999', '999']], [10], [3], luhn: false);
        }
    });

    expect(ruleAccepts(new CardNumber(), '9990000000'))->toBeTrue()
        ->and(ruleAccepts(new CardNumber(), '4242424242424242'))->toBeFalse();
});

// =========================================================================
// CardCvc
// =========================================================================

it('accepts 3 or 4 digits when no brand context is given', function (): void {
    expect(ruleAccepts(new CardCvc(), '123'))->toBeTrue()
        ->and(ruleAccepts(new CardCvc(), '1234'))->toBeTrue()
        ->and(ruleAccepts(new CardCvc(), '12'))->toBeFalse()
        ->and(ruleAccepts(new CardCvc(), '12345'))->toBeFalse()
        ->and(ruleAccepts(new CardCvc(), '12a'))->toBeFalse()
        ->and(ruleAccepts(new CardCvc(), 123))->toBeFalse();
});

it('narrows to the brand of a sibling card number', function (): void {
    $data = static fn (string $number, string $cvc): bool => Validator::make(
        ['number' => $number, 'cvc' => $cvc],
        ['cvc' => new CardCvc(numberField: 'number')],
    )->passes();

    expect($data('4242424242424242', '123'))->toBeTrue()      // Visa: 3
        ->and($data('4242424242424242', '1234'))->toBeFalse()
        ->and($data('378282246310005', '1234'))->toBeTrue()   // Amex: 3 or 4
        ->and($data('378282246310005', '123'))->toBeTrue()
        // An unrecognised number falls back to 3-or-4: the CVC field must
        // not fail because the NUMBER field is wrong — that rule says so.
        ->and($data('1234567890123452', '123'))->toBeTrue();
});

// =========================================================================
// CardExpiry
// =========================================================================

it('accepts current and future expiries in the common spellings', function (string $value): void {
    Carbon::setTestNow('2026-08-24 12:00:00');

    expect(ruleAccepts(new CardExpiry(), $value))->toBeTrue();
})->with(['08/26', '09/26', '12/2027', '08-26', '11-2030', '2027-03', '8/27']);

it('rejects past, malformed and implausibly distant expiries', function (mixed $value): void {
    Carbon::setTestNow('2026-08-24 12:00:00');

    expect(ruleAccepts(new CardExpiry(), $value))->toBeFalse();
})->with([
    '07/26',       // last month
    '12/25',       // last year
    '13/27',       // month 13
    '00/27',
    '08/47',       // beyond the plausible-typo horizon (20 years)
    '0826',
    'never',
    826,
    null,
]);

it('treats the current month as valid until it ends, in the given timezone', function (): void {
    // 23:30 UTC on Aug 31 is already September in Nairobi.
    Carbon::setTestNow('2026-08-31 23:30:00');

    expect(ruleAccepts(new CardExpiry(timezone: 'UTC'), '08/26'))->toBeTrue()
        ->and(ruleAccepts(new CardExpiry(timezone: 'Africa/Nairobi'), '08/26'))->toBeFalse();
});
