<?php

declare(strict_types=1);

use Simtabi\Laranail\Validation\RuleSet;
use Simtabi\Laranail\Validation\FluentRule;
use Simtabi\Laranail\Validation\Rules\Net\MacAddress;
use Simtabi\Laranail\Validation\Rules\Net\InCidrRange;

function netCheck(mixed $rule, mixed $value): bool
{
    return RuleSet::from(['f' => $rule])->check(['f' => $value])->passes();
}

function netError(mixed $rule, mixed $value): string
{
    return (string) RuleSet::from(['f' => $rule])->check(['f' => $value])->errors()->first('f');
}

// =========================================================================
// MAC addresses
// =========================================================================

it('accepts every notation by default', function (string $value): void {
    expect(netCheck(FluentRule::macAddress(), $value))->toBeTrue(netError(FluentRule::macAddress(), $value));
})->with([
    '00:1B:44:11:3A:B7',
    '00-1B-44-11-3A-B7',
    '001b.4411.3ab7',
    '001B44113AB7',
    // EUI-64.
    '00:1B:44:11:3A:B7:12:34',
    '001B44113AB71234',
]);

it('rejects what is not a MAC address', function (string $value): void {
    expect(netCheck(FluentRule::macAddress(), $value))->toBeFalse();
})->with(['00:1B:44:11:3A', '00:1B:44:11:3A:BZ', 'nope', '00:1B:44:11:3A:B7:12', '00:1B:44:11:3A:B7:12:34:56']);

it('leaves a blank value to the presence rules, as Laravel does', function (): void {
    // A blank string runs only the implicit rules, so the format check never
    // sees it. `required` is what refuses it, and that separation is the
    // framework's rather than this rule's.
    expect(netCheck(FluentRule::macAddress(), ''))->toBeTrue()
        ->and(netCheck(FluentRule::macAddress()->required(), ''))->toBeFalse();
});

it('pins the notation, so a column can be looked up by equality', function (): void {
    // Three spellings of one address in one column is a duplicate that is
    // invisible in the table.
    $colon = FluentRule::macAddress()->colon();

    expect(netCheck($colon, '00:1B:44:11:3A:B7'))->toBeTrue()
        ->and(netCheck($colon, '00-1B-44-11-3A-B7'))->toBeFalse()
        ->and(netCheck($colon, '001b.4411.3ab7'))->toBeFalse()
        ->and(netCheck(FluentRule::macAddress()->dotted(), '001b.4411.3ab7'))->toBeTrue()
        ->and(netCheck(FluentRule::macAddress()->bare(), '001B44113AB7'))->toBeTrue()
        ->and(netCheck(FluentRule::macAddress()->hyphen(), '00-1B-44-11-3A-B7'))->toBeTrue();
});

it('bounds the length to EUI-48 or EUI-64', function (): void {
    expect(netCheck(FluentRule::macAddress()->eui48(), '00:1B:44:11:3A:B7'))->toBeTrue()
        ->and(netCheck(FluentRule::macAddress()->eui48(), '00:1B:44:11:3A:B7:12:34'))->toBeFalse()
        ->and(netCheck(FluentRule::macAddress()->eui64(), '00:1B:44:11:3A:B7:12:34'))->toBeTrue()
        ->and(netCheck(FluentRule::macAddress()->eui64(), '00:1B:44:11:3A:B7'))->toBeFalse();
});

it('refuses the two addresses that pass a format check and name no device', function (): void {
    expect(netCheck(FluentRule::macAddress(), 'FF:FF:FF:FF:FF:FF'))->toBeFalse()
        ->and(netCheck(FluentRule::macAddress(), '00:00:00:00:00:00'))->toBeFalse();
});

it('says broadcast rather than blaming a bit', function (): void {
    // Broadcast has the multicast bit set, so a naive ordering reports "must
    // be a unicast address" — true, and no help at all.
    expect(netError(FluentRule::macAddress()->unicast(), 'FF:FF:FF:FF:FF:FF'))
        ->toBe('The f must not be the broadcast address.');
});

it('separates unicast from multicast on the I/G bit', function (): void {
    // Bit 0 of the first octet. 01:… is multicast, 00:… is not.
    expect(netCheck(FluentRule::macAddress()->unicast(), '00:1B:44:11:3A:B7'))->toBeTrue()
        ->and(netCheck(FluentRule::macAddress()->unicast(), '01:1B:44:11:3A:B7'))->toBeFalse();
});

it('separates a manufacturer address from a randomised one on the U/L bit', function (): void {
    // Bit 1 of the first octet. Every modern phone presents a locally
    // administered address to a network it has not joined — a perfectly valid
    // MAC and a useless identity, because it changes.
    expect(netCheck(FluentRule::macAddress()->universal(), '00:1B:44:11:3A:B7'))->toBeTrue()
        ->and(netCheck(FluentRule::macAddress()->universal(), '02:1B:44:11:3A:B7'))->toBeFalse()
        ->and(netCheck(FluentRule::macAddress(), '02:1B:44:11:3A:B7'))->toBeTrue();
});

it('matches an OUI written in any notation', function (): void {
    $rule = FluentRule::macAddress()->oui(['00:1B:44']);

    expect(netCheck($rule, '00:1B:44:11:3A:B7'))->toBeTrue()
        ->and(netCheck($rule, '001b.4411.3ab7'))->toBeTrue()
        ->and(netCheck($rule, '00:1C:44:11:3A:B7'))->toBeFalse()
        ->and(netCheck(FluentRule::macAddress()->oui('001B44'), '00:1B:44:11:3A:B7'))->toBeTrue();
});

it('normalises to one canonical spelling', function (string $value): void {
    expect(MacAddress::normalise($value))->toBe('00:1B:44:11:3A:B7');
})->with(['00:1B:44:11:3A:B7', '00-1b-44-11-3a-b7', '001b.4411.3ab7', '001B44113AB7']);

it('returns null rather than half-converting a value that is not a MAC', function (): void {
    // A caller writing the result into a column must not be handed garbage.
    expect(MacAddress::normalise('nope'))->toBeNull();
});

// =========================================================================
// IP addresses
// =========================================================================

it('gates the family', function (): void {
    expect(netCheck(FluentRule::ip(), '127.0.0.1'))->toBeTrue()
        ->and(netCheck(FluentRule::ip(), '::1'))->toBeTrue()
        ->and(netCheck(FluentRule::ipv4(), '::1'))->toBeFalse()
        ->and(netCheck(FluentRule::ipv6(), '127.0.0.1'))->toBeFalse()
        ->and(netCheck(FluentRule::ipv4(), '127.0.0.1'))->toBeTrue()
        ->and(netCheck(FluentRule::ipv6(), '::1'))->toBeTrue();
});

it('separates routable from reserved', function (): void {
    expect(netCheck(FluentRule::ip()->public(), '8.8.8.8'))->toBeTrue()
        ->and(netCheck(FluentRule::ip()->public(), '10.0.0.1'))->toBeFalse()
        ->and(netCheck(FluentRule::ip()->private(), '10.0.0.1'))->toBeTrue()
        ->and(netCheck(FluentRule::ip()->private(), '8.8.8.8'))->toBeFalse();
});

it('sees through the two holes in the filter_var shortcut', function (): void {
    // FILTER_FLAG_NO_PRIV_RANGE|NO_RES_RANGE reads ::ffff:127.0.0.1 as an
    // ordinary global v6 address, and lets carrier-grade NAT through.
    expect(netCheck(FluentRule::ip()->public(), '::ffff:127.0.0.1'))->toBeFalse()
        ->and(netCheck(FluentRule::ip()->public(), '100.64.0.1'))->toBeFalse();
});

it('checks membership of a CIDR network', function (): void {
    $rule = FluentRule::ip()->inRange(['203.0.113.0/24', '2001:db8::/32']);

    expect(netCheck($rule, '203.0.113.7'))->toBeTrue()
        ->and(netCheck($rule, '203.0.114.7'))->toBeFalse()
        ->and(netCheck($rule, '2001:db8::1'))->toBeTrue()
        ->and(netCheck($rule, '2001:db9::1'))->toBeFalse();
});

it('handles a prefix that does not land on a byte boundary', function (): void {
    // /10, /12 and /7 all appear in the reserved tables, and a whole-byte
    // comparison gets every one of them wrong.
    expect(InCidrRange::contains('100.64.0.0/10', '100.64.0.1'))->toBeTrue()
        ->and(InCidrRange::contains('100.64.0.0/10', '100.128.0.1'))->toBeFalse()
        ->and(InCidrRange::contains('172.16.0.0/12', '172.31.255.255'))->toBeTrue()
        ->and(InCidrRange::contains('172.16.0.0/12', '172.32.0.1'))->toBeFalse();
});

it('never matches an address against the other family', function (): void {
    expect(InCidrRange::contains('10.0.0.0/8', '::1'))->toBeFalse()
        ->and(InCidrRange::contains('2001:db8::/32', '10.0.0.1'))->toBeFalse();
});

it('unwraps IPv4-mapped v6 before comparing, which is how an allow-list is bypassed', function (): void {
    expect(InCidrRange::contains('10.0.0.0/8', '::ffff:10.0.0.1'))->toBeTrue()
        ->and(InCidrRange::contains('10.0.0.0/8', '::ffff:11.0.0.1'))->toBeFalse();
});

it('lets nothing through a malformed network rather than everything', function (): void {
    // An allow-list with a typo should fail closed, and should not throw
    // mid-request on a value the user chose.
    expect(InCidrRange::contains('10.0.0.0/64', '10.0.0.1'))->toBeFalse()
        ->and(InCidrRange::contains('not-a-network', '10.0.0.1'))->toBeFalse()
        ->and(netCheck(FluentRule::ip()->inRange(['garbage']), '10.0.0.1'))->toBeFalse();
});

it('names the networks it will accept', function (): void {
    expect(netError(FluentRule::ip()->inRange(['203.0.113.0/24']), '10.0.0.1'))
        ->toBe('The f must be within one of these networks: 203.0.113.0/24.');
});

// =========================================================================
// The narrow surface — the reason these are their own nodes
// =========================================================================

it('offers only what applies to the field', function (): void {
    // FluentRule::ip() returned a StringRule, so an IP field autocompleted
    // hexColor(), uuid() and dateFormat() and offered nothing about routing.
    // Read through reflection rather than method_exists(), which PHPStan
    // resolves at analysis time and reports as always-true.
    $surface = static fn (object $node): array => array_map(
        static fn (ReflectionMethod $m): string => $m->getName(),
        new ReflectionClass($node)->getMethods(ReflectionMethod::IS_PUBLIC),
    );

    expect($surface(FluentRule::ip()))->toContain('public')->not->toContain('hexColor')
        ->and($surface(FluentRule::url()))->toContain('secure')->not->toContain('uuid')
        ->and($surface(FluentRule::macAddress()))->toContain('universal')->not->toContain('dateFormat')
        ->and($surface(FluentRule::username()))->toContain('reserved')->not->toContain('timezone');
});

it('still carries the shared modifiers every field needs', function (): void {
    $surface = static fn (object $node): array => array_map(
        static fn (ReflectionMethod $m): string => $m->getName(),
        new ReflectionClass($node)->getMethods(ReflectionMethod::IS_PUBLIC),
    );

    foreach ([FluentRule::url(), FluentRule::ip(), FluentRule::macAddress(), FluentRule::username()] as $node) {
        expect($surface($node))
            ->toContain('required', 'nullable', 'unique', 'label', 'when', 'rule');
    }
});
