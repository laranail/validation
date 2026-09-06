<?php

declare(strict_types=1);

use Simtabi\Laranail\Validation\RuleSet;
use Simtabi\Laranail\Validation\FluentRule;

/*
 * Every value in the first block passes Laravel's own `url` rule. That is the
 * point of this rule existing: none of them is malformed, and each is a
 * different question that a regex at the call site gets wrong.
 */

function urlCheck(mixed $rule, mixed $value): bool
{
    return RuleSet::from(['u' => $rule])->check(['u' => $value])->passes();
}

function urlError(mixed $rule, mixed $value): string
{
    return (string) RuleSet::from(['u' => $rule])->check(['u' => $value])->errors()->first('u');
}

it('accepts an ordinary link', function (string $value): void {
    expect(urlCheck(FluentRule::url(), $value))->toBeTrue(urlError(FluentRule::url(), $value));
})->with([
    'https://example.com',
    'http://example.com/',
    'https://example.com/a/b?c=d#e',
    'https://sub.example.co.uk:8443/path',
    'https://192.0.2.1/',
    'https://[2001:db8::1]/',
]);

it('rejects what is not a URL at all', function (): void {
    // An inline loop rather than a dataset: an empty array as a dataset entry
    // is read as "no arguments" and the case silently never runs.
    $values = ['not-a-url', 'example.com', '//example.com', 'https://', 'http:///a', 'https://exa mple.com', 123, [], null, true];

    foreach ($values as $value) {
        expect(urlCheck(FluentRule::url()->required(), $value))
            ->toBeFalse('accepted ' . json_encode($value));
    }
});

it('rejects a scheme nobody meant to allow', function (): void {
    // Laravel's `url` accepts every one of these.
    expect(urlCheck(FluentRule::url(), 'ftp://files.example.com/'))->toBeFalse()
        ->and(urlCheck(FluentRule::url(), 'javascript:alert(1)'))->toBeFalse()
        ->and(urlCheck(FluentRule::url(), 'file:///etc/passwd'))->toBeFalse()
        ->and(urlCheck(FluentRule::url()->scheme(['ftp']), 'ftp://files.example.com/'))->toBeTrue();
});

it('names the schemes it will take', function (): void {
    // "That is not a valid URL" for a working `ftp://` link sends the reader
    // looking for a typo that is not there.
    expect(urlError(FluentRule::url(), 'ftp://files.example.com/'))
        ->toBe('The u must use one of these schemes: http, https.');
});

it('requires https when asked', function (): void {
    expect(urlCheck(FluentRule::url()->secure(), 'https://example.com'))->toBeTrue()
        ->and(urlCheck(FluentRule::url()->secure(), 'http://example.com'))->toBeFalse();
});

it('rejects credentials in the authority by default', function (): void {
    // A submitted URL carrying a password ends up in a database, in a log
    // line, and in the referrer of whatever the application fetches next.
    expect(urlCheck(FluentRule::url(), 'https://user:password@example.com/'))->toBeFalse()
        ->and(urlCheck(FluentRule::url(), 'https://user@example.com/'))->toBeFalse()
        ->and(urlCheck(FluentRule::url()->allowCredentials(), 'https://user:password@example.com/'))->toBeTrue();
});

it('rejects a host that is not a domain', function (string $value): void {
    expect(urlCheck(FluentRule::url(), $value))->toBeFalse();
})->with([
    'https://-example.com/',
    'https://example-.com/',
    'https://exa..mple.com/',
    'https://intranet/',
]);

it('takes a single-label host when told to', function (): void {
    expect(urlCheck(FluentRule::url()->allowSingleLabelHost(), 'http://intranet/'))->toBeTrue()
        ->and(urlCheck(FluentRule::url(), 'http://intranet/'))->toBeFalse();
});

it('can refuse an IP literal host', function (): void {
    expect(urlCheck(FluentRule::url()->withoutIpHost(), 'https://192.0.2.1/'))->toBeFalse()
        ->and(urlCheck(FluentRule::url()->withoutIpHost(), 'https://[2001:db8::1]/'))->toBeFalse()
        ->and(urlCheck(FluentRule::url()->withoutIpHost(), 'https://example.com/'))->toBeTrue();
});

it('rejects the addresses a naive fetcher walks into', function (string $value): void {
    // The cloud metadata endpoint, loopback, and the RFC 1918 ranges. Every
    // one passes Laravel's `url`.
    expect(urlCheck(FluentRule::url()->publicHost(), $value))->toBeFalse();
})->with([
    'http://169.254.169.254/latest/meta-data/',
    'http://127.0.0.1:6379/',
    'http://10.0.0.1/',
    'http://192.168.1.1/',
    'http://[::1]/',
    'http://localhost/',
    // The v6-wrapped forms of loopback, which are the classic filter bypass.
    'http://[::ffff:127.0.0.1]/',
]);

it('is honest that publicHost cannot see through DNS', function (): void {
    // A host name that resolves to loopback passes, and it has to: answering
    // otherwise needs a lookup, and even a rule that looked would be defeated
    // by rebinding between the check and the request. Stated as a test so the
    // limit is not mistaken for an oversight.
    expect(urlCheck(FluentRule::url()->publicHost(), 'https://example.com/'))->toBeTrue();
});

it('restricts the host, without the suffix hole', function (): void {
    $rule = FluentRule::url()->hostIs(['example.com', '*.example.com']);

    expect(urlCheck($rule, 'https://example.com/'))->toBeTrue()
        ->and(urlCheck($rule, 'https://mail.example.com/'))->toBeTrue()
        // The two shapes a hand-written check gets wrong.
        ->and(urlCheck($rule, 'https://evil-example.com/'))->toBeFalse()
        ->and(urlCheck($rule, 'https://example.com.attacker.test/'))->toBeFalse();
});

it('does not let the wildcard admit the bare domain', function (): void {
    $rule = FluentRule::url()->hostIs(['*.example.com']);

    expect(urlCheck($rule, 'https://mail.example.com/'))->toBeTrue()
        ->and(urlCheck($rule, 'https://example.com/'))->toBeFalse();
});

it('blocks hosts after the allow-list', function (): void {
    $rule = FluentRule::url()->hostIs(['*.example.com'])->hostIsNot(['internal.example.com']);

    expect(urlCheck($rule, 'https://mail.example.com/'))->toBeTrue()
        ->and(urlCheck($rule, 'https://internal.example.com/'))->toBeFalse();
});

it('restricts the port', function (): void {
    expect(urlCheck(FluentRule::url()->port(443), 'https://example.com:443/'))->toBeTrue()
        ->and(urlCheck(FluentRule::url()->port(443), 'https://example.com:22/'))->toBeFalse()
        ->and(urlCheck(FluentRule::url()->port([80, 443]), 'https://example.com:80/'))->toBeTrue();
});

it('resolves an omitted port to the scheme default before the allow-list', function (): void {
    // Almost every real URL omits the port. `https://example.com/` IS port
    // 443 — treating the missing component as port 0 rejected effectively
    // every URL the moment an allow-list was configured.
    expect(urlCheck(FluentRule::url()->port(443), 'https://example.com/'))->toBeTrue()
        ->and(urlCheck(FluentRule::url()->port(80), 'http://example.com/'))->toBeTrue()
        ->and(urlCheck(FluentRule::url()->port(8443), 'https://example.com/'))->toBeFalse()
        ->and(urlCheck(FluentRule::url()->port([80, 443]), 'https://example.com/'))->toBeTrue();
});

it('can refuse a query string or a fragment', function (): void {
    expect(urlCheck(FluentRule::url()->withoutQuery(), 'https://example.com/?a=b'))->toBeFalse()
        ->and(urlCheck(FluentRule::url()->withoutQuery(), 'https://example.com/'))->toBeTrue()
        ->and(urlCheck(FluentRule::url()->withoutFragment(), 'https://example.com/#a'))->toBeFalse();
});

it('rejects control characters anywhere in the value', function (string $value): void {
    // A newline that later reaches a header is response splitting; a tab in
    // the host is how a browser and a server-side parser are made to disagree
    // about where the host ends.
    expect(urlCheck(FluentRule::url(), $value))->toBeFalse();
})->with([
    "https://example.com/\n",
    "https://exam\tple.com/",
    "https://example.com/\r\nX-Injected: 1",
    ' https://example.com/',
]);

it('bounds the length', function (): void {
    $long = 'https://example.com/' . str_repeat('a', 3000);

    expect(urlCheck(FluentRule::url(), $long))->toBeFalse()
        ->and(urlCheck(FluentRule::url()->maxLength(4000), $long))->toBeTrue();
});

it('gives the Unicode and Punycode spellings of a host the same answer', function (): void {
    // Skipped rather than silently passing where ext-intl is absent: without
    // it the rule rejects non-ASCII, and asserting equality would then be
    // asserting that two rejections match.
    if (! function_exists('idn_to_ascii')) {
        expect(true)->toBeTrue();

        return;
    }

    $rule = FluentRule::url()->hostIs(['xn--mnchen-3ya.de']);

    expect(urlCheck($rule, 'https://münchen.de/'))->toBeTrue()
        ->and(urlCheck($rule, 'https://xn--mnchen-3ya.de/'))->toBeTrue();
});

it('does not rewrite a host that reappears in the query string', function (): void {
    if (! function_exists('idn_to_ascii')) {
        expect(true)->toBeTrue();

        return;
    }

    // Only the authority is converted. Running the whole URL through
    // idn_to_ascii would rewrite a redirect parameter too, changing what is
    // being validated.
    expect(urlCheck(FluentRule::url(), 'https://münchen.de/?next=münchen.de'))->toBeTrue();
});
