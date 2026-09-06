<?php

declare(strict_types=1);

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Http\Client\ConnectionException;
use Simtabi\Laranail\Validation\Rules\Network\ImageUrl;
use Simtabi\Laranail\Validation\Rules\Network\HasGravatar;
use Simtabi\Laranail\Validation\Contracts\PrecognitionSkippable;

// =========================================================================
// ImageUrl — the redesigned image-URL probe (owner decision: implement,
// guarded, instead of the plan's recommended drop)
// =========================================================================

it('accepts a URL that serves an image content type', function (): void {
    Http::fake(['example.com/*' => Http::response('', 200, ['Content-Type' => 'image/png'])]);

    expect(ruleAccepts(new ImageUrl, 'https://example.com/logo.png'))->toBeTrue();
});

it('rejects non-image content, error statuses and unreachable hosts', function (): void {
    Http::fake([
        'text.example.com/*' => Http::response('', 200, ['Content-Type' => 'text/html']),
        'gone.example.com/*' => Http::response('', 404),
        'down.example.com/*' => static fn () => throw new ConnectionException('refused'),
    ]);

    expect(ruleAccepts(new ImageUrl, 'https://text.example.com/page'))->toBeFalse()
        ->and(ruleAccepts(new ImageUrl, 'https://gone.example.com/x.png'))->toBeFalse()
        // The probe IS the rule: unreachable fails, unlike DeliverableEmail's
        // fail-open DNS posture, because "serves an image right now" is
        // exactly what was asked.
        ->and(ruleAccepts(new ImageUrl, 'https://down.example.com/x.png'))->toBeFalse();
});

it('can restrict the image subtypes', function (): void {
    Http::fake(['example.com/*' => Http::response('', 200, ['Content-Type' => 'image/gif'])]);

    expect(ruleAccepts(new ImageUrl(mimes: ['png', 'jpeg']), 'https://example.com/a.gif'))->toBeFalse()
        ->and(ruleAccepts(new ImageUrl(mimes: ['gif']), 'https://example.com/a.gif'))->toBeTrue();
});

it('refuses non-http schemes, loopback names and reserved-range IP literals without any request', function (mixed $value): void {
    Http::fake();

    expect(ruleAccepts(new ImageUrl, $value))->toBeFalse();

    Http::assertNothingSent();
})->with([
    'ftp://example.com/logo.png',
    'http://localhost/x.png',
    'http://127.0.0.1/x.png',
    'http://169.254.169.254/latest/meta-data',
    'http://[::1]/x.png',
    'not a url',
    12,
    null,
]);

it('probes with HEAD and follows no redirects', function (): void {
    Http::fake(['example.com/*' => Http::response('', 302, ['Location' => 'http://127.0.0.1/'])]);

    expect(ruleAccepts(new ImageUrl, 'https://example.com/logo.png'))->toBeFalse();

    Http::assertSent(static fn (Request $request): bool => $request->method() === 'HEAD');
});

it('is skipped during precognition', function (): void {
    expect(new ImageUrl)->toBeInstanceOf(PrecognitionSkippable::class)
        ->and(new HasGravatar)->toBeInstanceOf(PrecognitionSkippable::class);
});

// =========================================================================
// HasGravatar — redesigned: https, sha256, fail-open on outage
// =========================================================================

it('reports whether the address has a gravatar via the sha256 endpoint', function (): void {
    Http::fake([
        'gravatar.com/avatar/' . hash('sha256', 'alice@example.com') . '*' => Http::response('', 200),
        'gravatar.com/*'                                                   => Http::response('', 404),
    ]);

    expect(ruleAccepts(new HasGravatar, 'alice@example.com'))->toBeTrue()
        ->and(ruleAccepts(new HasGravatar, 'nobody@example.com'))->toBeFalse();
});

it('normalises the address before hashing, as gravatar does', function (): void {
    Http::fake([
        'gravatar.com/avatar/' . hash('sha256', 'alice@example.com') . '*' => Http::response('', 200),
        'gravatar.com/*'                                                   => Http::response('', 404),
    ]);

    expect(ruleAccepts(new HasGravatar, '  Alice@Example.COM  '))->toBeTrue();
});

it('passes rather than blocks when gravatar is unreachable', function (): void {
    // The DeliverableEmail posture: a third party's outage must not turn
    // away real users. Treat a pass as "not shown to be missing".
    Http::fake(['gravatar.com/*' => static fn () => throw new ConnectionException('down')]);

    expect(ruleAccepts(new HasGravatar, 'alice@example.com'))->toBeTrue();
});

it('rejects a malformed address without asking gravatar', function (): void {
    Http::fake();

    expect(ruleAccepts(new HasGravatar, 'not-an-email'))->toBeFalse()
        ->and(ruleAccepts(new HasGravatar, 42))->toBeFalse();

    Http::assertNothingSent();
});
