<?php declare(strict_types=1);

use Illuminate\Support\Facades\Validator;
use Simtabi\Laranail\Validation\Rules\Encoding\Base64;
use Simtabi\Laranail\Validation\Rules\Encoding\Base64Image;
use Simtabi\Laranail\Validation\Rules\Encoding\DataUri;
use Simtabi\Laranail\Validation\Support\Encoding\Base64File;

/** A real 1x1 PNG, 70 bytes decoded. */
const PNG_B64 = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==';

// =========================================================================
// Base64 — canonical encoding, verified by round trip
// =========================================================================

it('accepts canonical base64', function (string $value): void {
    expect(ruleAccepts(new Base64(), $value))->toBeTrue();
})->with(['aGVsbG8=', 'aGVsbG8gd29ybGQ=', PNG_B64]);

it('rejects non-canonical and malformed base64', function (mixed $value): void {
    // Round-tripping catches what a charset regex cannot: missing padding,
    // embedded whitespace, and non-zero discarded bits ('aGVsbG9=' decodes,
    // but nothing ever encodes TO it).
    expect(ruleAccepts(new Base64(), $value))->toBeFalse();
})->with(['aGVsbG9', "aGVsbG8=\n", 'aGVsbG 8=', '!!!!', 'aGVsbG9=', 12345, null]);

// =========================================================================
// Base64Image — decoded and sniffed, bare or data-URI form
// =========================================================================

it('accepts a real image, bare and as a data URI', function (): void {
    expect(ruleAccepts(new Base64Image(), PNG_B64))->toBeTrue()
        ->and(ruleAccepts(new Base64Image(), 'data:image/png;base64,' . PNG_B64))->toBeTrue();
});

it('rejects non-images and images outside the allowed types', function (): void {
    // 'aGVsbG8=' is valid base64 of text — the MIME sniff is what rejects it.
    expect(ruleAccepts(new Base64Image(), 'aGVsbG8='))->toBeFalse()
        ->and(ruleAccepts(new Base64Image(mimes: ['jpeg']), PNG_B64))->toBeFalse()
        ->and(ruleAccepts(new Base64Image(), 'not base64 at all'))->toBeFalse()
        ->and(ruleAccepts(new Base64Image(), 12345))->toBeFalse();
});

it('enforces the decoded size cap with a human-readable message', function (): void {
    expect(ruleAccepts(new Base64Image(maxBytes: 69), PNG_B64))->toBeFalse()
        ->and(ruleAccepts(new Base64Image(maxBytes: 70), PNG_B64))->toBeTrue();

    $validator = Validator::make(
        ['f' => PNG_B64],
        ['f' => new Base64Image(maxBytes: 42)],
    );

    // The size is stated in units a person reads, not raw bytes.
    expect($validator->errors()->first('f'))->toContain('42 B');
});

it('bridges a validated image to an UploadedFile for Laravel file rules', function (): void {
    $file = Base64File::toUploadedFile('data:image/png;base64,' . PNG_B64, 'avatar.png');

    expect($file)->not->toBeNull()
        ->and($file->getSize())->toBe(70)
        ->and(Validator::make(
            ['f' => $file],
            ['f' => 'image'],
        )->passes())->toBeTrue();

    expect(Base64File::toUploadedFile('not base64'))->toBeNull();
});

// =========================================================================
// DataUri — RFC 2397
// =========================================================================

it('accepts well-formed data URIs', function (string $value): void {
    expect(ruleAccepts(new DataUri(), $value))->toBeTrue();
})->with([
    'data:image/png;base64,' . PNG_B64,
    'data:text/plain,hello%20world',
    'data:,bare%20form',                          // both parts are optional
    'data:text/plain;charset=utf-8,hi',
]);

it('rejects malformed data URIs', function (mixed $value): void {
    expect(ruleAccepts(new DataUri(), $value))->toBeFalse();
})->with([
    'data:image/png;base64,!!!!',      // base64 flag with non-base64 payload
    'data:image/png;base64',           // no comma, no data
    'dat:image/png;base64,' . PNG_B64, // scheme typo
    'data:image;base64,abcd',          // media type missing its subtype
    'data:text/plain,hello world',     // raw space is not URL-encoded
    12345,
    null,
]);

it('can restrict the media types it accepts', function (): void {
    $imagesOnly = new DataUri(mediaTypes: ['image/*']);

    expect(ruleAccepts($imagesOnly, 'data:image/png;base64,' . PNG_B64))->toBeTrue()
        ->and(ruleAccepts($imagesOnly, 'data:text/plain,hi'))->toBeFalse()
        // Restricting to a type means an untyped URI no longer qualifies.
        ->and(ruleAccepts($imagesOnly, 'data:,hi'))->toBeFalse()
        ->and(ruleAccepts(new DataUri(mediaTypes: ['text/plain']), 'data:text/plain,hi'))->toBeTrue();
});
