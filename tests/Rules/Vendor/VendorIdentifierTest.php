<?php

declare(strict_types=1);

use Simtabi\Laranail\Validation\Rules\Vendor\VendorIdentifier;

it('accepts a well-formed identifier for each vendor', function (string $vendor, string $value): void {
    expect(VendorIdentifier::passes($value, $vendor))->toBeTrue();
})->with([
    'ga4' => [VendorIdentifier::GOOGLE_ANALYTICS, 'G-ABCDE12345'],
    'gtm' => [VendorIdentifier::GOOGLE_TAG_MANAGER, 'GTM-ABC1234'],
    'pixel 15' => [VendorIdentifier::FACEBOOK_PIXEL, '123456789012345'],
    'pixel 16' => [VendorIdentifier::FACEBOOK_PIXEL, '1234567890123456'],
    'tenant uuid' => [VendorIdentifier::MICROSOFT_TENANT, '72f988bf-86f1-41af-91ab-2d7cd011db47'],
    'tenant alias' => [VendorIdentifier::MICROSOFT_TENANT, 'common'],
    'aws' => [VendorIdentifier::AWS_REGION, 'us-east-1'],
    'aws long' => [VendorIdentifier::AWS_REGION, 'ap-southeast-4'],
    'aws govcloud' => [VendorIdentifier::AWS_REGION, 'us-gov-west-1'],
    'discord' => [VendorIdentifier::DISCORD_USERNAME, 'alice.smith'],
]);

it('rejects a plausible but wrong identifier', function (string $vendor, string $value): void {
    expect(VendorIdentifier::passes($value, $vendor))->toBeFalse();
})->with([
    'ga4 too short' => [VendorIdentifier::GOOGLE_ANALYTICS, 'G-ABCDE1234'],
    'ga4 old UA form' => [VendorIdentifier::GOOGLE_ANALYTICS, 'UA-12345-1'],
    'gtm no prefix' => [VendorIdentifier::GOOGLE_TAG_MANAGER, 'ABC1234'],
    'pixel too short' => [VendorIdentifier::FACEBOOK_PIXEL, '12345'],
    'pixel non-numeric' => [VendorIdentifier::FACEBOOK_PIXEL, '12345678901234a'],
    'tenant not a uuid' => [VendorIdentifier::MICROSOFT_TENANT, '72f988bf86f141af91ab2d7cd011db47'],
    'aws no digit' => [VendorIdentifier::AWS_REGION, 'us-east'],
    'aws uppercase' => [VendorIdentifier::AWS_REGION, 'US-EAST-1'],
    'discord uppercase' => [VendorIdentifier::DISCORD_USERNAME, 'Alice'],
    'discord dots' => [VendorIdentifier::DISCORD_USERNAME, 'alice..smith'],
    'discord too short' => [VendorIdentifier::DISCORD_USERNAME, 'a'],
]);

it('is case-insensitive where the vendor is, and not where it is not', function (): void {
    // Google ids are uppercase by convention but pasted in any case; a Discord
    // username is lowercase by rule, so accepting "Alice" would let through a
    // value Discord itself rejects.
    expect(VendorIdentifier::passes('g-abcde12345', VendorIdentifier::GOOGLE_ANALYTICS))->toBeTrue()
        ->and(VendorIdentifier::passes('Alice', VendorIdentifier::DISCORD_USERNAME))->toBeFalse();
});

it('rejects an unknown vendor rather than passing everything', function (): void {
    expect(VendorIdentifier::passes('anything', 'not-a-vendor'))->toBeFalse();
});

it('rejects a non-string', function (mixed $value): void {
    expect(VendorIdentifier::passes($value, VendorIdentifier::AWS_REGION))->toBeFalse();
})->with([null, 123, [['us-east-1']]]);
