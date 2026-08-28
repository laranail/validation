<?php

declare(strict_types=1);

use Simtabi\Laranail\Validation\Rules\Net\Cidr;
use Simtabi\Laranail\Validation\Rules\Net\PublicIp;
use Simtabi\Laranail\Validation\Rules\Net\PrivateIp;
use Simtabi\Laranail\Validation\Rules\Net\Subdomain;
use Simtabi\Laranail\Validation\Rules\Net\DomainName;

// =========================================================================
// DomainName
// =========================================================================

it('accepts valid domain names', function (string $value): void {
    expect(ruleAccepts(new DomainName, $value))->toBeTrue();
})->with([
    'example.com',
    'sub.example.com',
    'deep.sub.example.co.uk',
    'example.com.',            // the explicit root dot is legal
    'xn--mnchen-3ya.de',       // A-label form
    'a.co',
    'EXAMPLE.COM',
    '123abc.example.com',      // a label may start with a digit
]);

it('rejects invalid domain names', function (string $value): void {
    expect(ruleAccepts(new DomainName, $value))->toBeFalse();
})->with([
    'localhost',               // single label, no TLD
    '-example.com',            // label may not lead with a hyphen
    'example-.com',            // nor end with one
    'exa mple.com',
    'example..com',            // empty label
    '192.168.0.1',             // an IP is not a domain name
    'example.123',             // all-numeric TLD
    'xn--.com',                // `xn--` with nothing to decode
    'example.com/path',
    'http://example.com',
]);

it('rejects a label longer than 63 characters', function (): void {
    expect(ruleAccepts(new DomainName, str_repeat('a', 63) . '.com'))->toBeTrue()
        ->and(ruleAccepts(new DomainName, str_repeat('a', 64) . '.com'))->toBeFalse();
});

it('rejects a name longer than 253 characters', function (): void {
    // filter_var(FILTER_VALIDATE_DOMAIN) has nothing to say about total
    // length, which is one of the reasons it is not enough on its own.
    $label = str_repeat('a', 49);                            // under the 63 label cap

    $atLimit = implode('.', array_fill(0, 5, $label)) . '.com';   // 5*49 + 4 dots + 4
    $overLimit = $atLimit . 'm';

    // Written in Pest's toHaveLength idiom deliberately: expressed as
    // strlen($x)->toBe(n), Rector's Pest set rewrites it and silently changed
    // the expected value while dropping two of the four assertions.
    expect($atLimit)->toHaveLength(253)
        ->and(ruleAccepts(new DomainName, $atLimit))->toBeTrue()
        ->and($overLimit)->toHaveLength(254)
        ->and(ruleAccepts(new DomainName, $overLimit))->toBeFalse();
});

it('treats a unicode name and its A-label form alike', function (): void {
    // The whole point of normalising to ASCII first: these are the same name.
    expect(DomainName::supportsInternationalNames())->toBeTrue()
        ->and(ruleAccepts(new DomainName, 'münchen.de'))->toBeTrue()
        ->and(ruleAccepts(new DomainName, 'xn--mnchen-3ya.de'))->toBeTrue();
})->skip(fn (): bool => ! DomainName::supportsInternationalNames(), 'ext-intl not installed');

it('can allow single-label names', function (): void {
    expect(ruleAccepts(new DomainName, 'localhost'))->toBeFalse()
        ->and(ruleAccepts(new DomainName(requireTld: false), 'localhost'))->toBeTrue();
});

// =========================================================================
// Subdomain
// =========================================================================

it('accepts valid subdomains', function (string $value): void {
    expect(ruleAccepts(new Subdomain, $value))->toBeTrue();
})->with(['blog', 'my-app', 'a', 'a1', '123', str_repeat('a', 63)]);

it('rejects invalid subdomains', function (string $value): void {
    expect(ruleAccepts(new Subdomain, $value))->toBeFalse();
})->with([
    'blog.example.com',        // one label only
    '-blog',
    'blog-',
    'my app',
    'my_app',
    str_repeat('a', 64),
]);

it('rejects punycode in a user-chosen subdomain', function (): void {
    // xn--pple-43d renders as `аpple` with a Cyrillic а. Accepting Punycode
    // from user input is a homograph attack waiting to happen, and a
    // self-chosen subdomain is exactly where it would land.
    expect(ruleAccepts(new Subdomain, 'xn--pple-43d'))->toBeFalse()
        ->and(ruleAccepts(new Subdomain, 'XN--pple-43d'))->toBeFalse();
});

// =========================================================================
// PublicIp / PrivateIp — SSRF-relevant, so exhaustive
// =========================================================================

it('accepts publicly routable addresses', function (string $ip): void {
    expect(ruleAccepts(new PublicIp, $ip))->toBeTrue()
        ->and(ruleAccepts(new PrivateIp, $ip))->toBeFalse();
})->with([
    '8.8.8.8',
    '1.1.1.1',
    '93.184.216.34',
    '172.32.0.1',              // just outside 172.16/12
    '100.128.0.1',             // just outside the CGNAT 100.64/10
    '2001:4860:4860::8888',
    '::ffff:8.8.8.8',          // mapped, but genuinely public
]);

it('rejects addresses that are not publicly routable', function (string $ip): void {
    expect(ruleAccepts(new PublicIp, $ip))->toBeFalse()
        ->and(ruleAccepts(new PrivateIp, $ip))->toBeTrue();
})->with([
    '10.0.0.1',                // RFC 1918
    '172.16.0.1',
    '172.31.255.255',          // upper edge of 172.16/12
    '192.168.1.1',
    '127.0.0.1',               // loopback
    '169.254.169.254',         // link-local: the cloud metadata endpoint
    '100.64.0.1',              // carrier-grade NAT
    '0.0.0.0',
    '255.255.255.255',
    '224.0.0.1',               // multicast
    '203.0.113.5',             // documentation
    '::1',
    'fe80::1',                 // link-local
    'fc00::1',                 // unique local
    'fd12:3456::1',
    'ff02::1',                 // multicast
    '2001:db8::1',             // documentation
]);

it('unwraps IPv4-mapped IPv6 before classifying', function (string $ip): void {
    // The classic SSRF filter bypass: ::ffff:127.0.0.1 is loopback written as
    // v6, and filter_var's NO_PRIV_RANGE|NO_RES_RANGE flags read it as an
    // ordinary global v6 address.
    expect(ruleAccepts(new PublicIp, $ip))->toBeFalse();
})->with([
    '::ffff:127.0.0.1',
    '::ffff:10.0.0.1',
    '::ffff:192.168.1.1',
    '::ffff:169.254.169.254',
]);

it('rejects values that are not IP addresses at all', function (string $value): void {
    expect(ruleAccepts(new PublicIp, $value))->toBeFalse()
        ->and(ruleAccepts(new PrivateIp, $value))->toBeFalse();
})->with(['not-an-ip', '999.999.999.999', '10.0.0', 'example.com', '10.0.0.1/8']);

it('is the exact complement of PrivateIp over valid addresses', function (): void {
    // Both rules delegate to one classifier precisely so a range cannot be
    // private to one and public to the other.
    foreach (['8.8.8.8', '10.0.0.1', '::1', '2001:4860:4860::8888', '::ffff:127.0.0.1'] as $ip) {
        expect(ruleAccepts(new PublicIp, $ip))
            ->not->toBe(ruleAccepts(new PrivateIp, $ip), "both agreed on {$ip}");
    }
});

// =========================================================================
// CIDR
// =========================================================================

it('accepts valid CIDR notation', function (string $value): void {
    expect(ruleAccepts(new Cidr, $value))->toBeTrue();
})->with([
    '10.0.0.0/8',
    '192.168.1.0/24',
    '0.0.0.0/0',
    '1.2.3.4/32',
    '10.0.0.1/8',              // host bits set: normal interface notation
    '2001:db8::/32',
    '::/0',
    'fe80::1/128',
]);

it('rejects invalid CIDR notation', function (string $value): void {
    expect(ruleAccepts(new Cidr, $value))->toBeFalse();
})->with([
    '10.0.0.0/33',             // v4 prefix out of range
    '10.0.0.0/64',             // a v6 prefix on a v4 address
    '2001:db8::/129',
    '10.0.0.0',                // no prefix
    '10.0.0.0/',
    '10.0.0.0/8/8',
    '10.0.0.0/-1',
    '10.0.0.0/08',             // leading zero
    '10.0.0.0/ 8',
    'not-an-ip/24',
]);
