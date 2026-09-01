<?php

declare(strict_types=1);

use Simtabi\Laranail\Validation\Contracts\Email\DisposableDomainList;
use Simtabi\Laranail\Validation\Contracts\Email\RoleAccountList;
use Simtabi\Laranail\Validation\Providers\ValidationServiceProvider;
use Simtabi\Laranail\Validation\Rules\Email\EmailDomainIs;
use Simtabi\Laranail\Validation\Rules\Email\EmailDomainIsNot;
use Simtabi\Laranail\Validation\Rules\Email\NotDisposableEmail;
use Simtabi\Laranail\Validation\Rules\Email\NotRoleEmail;
use Simtabi\Laranail\Validation\Support\Email\BundledDisposableDomainList;
use Simtabi\Laranail\Validation\Support\Email\BundledRoleAccountList;

/**
 * A list that answers yes to exactly the entries given.
 *
 * Returned as the intersection of both contracts so one helper serves the
 * disposable and role rules — and so the tests exercise the same seam
 * laranail/email will use to swap in the real implementations.
 *
 * @param  list<string>  $entries
 */
function fakeList(array $entries): DisposableDomainList&RoleAccountList
{
    return new readonly class($entries) implements DisposableDomainList, RoleAccountList
    {
        /** @param  list<string>  $entries */
        public function __construct(private array $entries) {}

        public function contains(string $domain): bool
        {
            return in_array(strtolower($domain), $this->entries, true);
        }
    };
}

// =========================================================================
// EmailDomainIs / EmailDomainIsNot
// =========================================================================

it('matches an exact domain', function (): void {
    $rule = new EmailDomainIs(['example.com']);

    expect(ruleAccepts($rule, 'alice@example.com'))->toBeTrue()
        ->and(ruleAccepts($rule, 'alice@EXAMPLE.COM'))->toBeTrue()
        ->and(ruleAccepts($rule, 'alice@other.test'))->toBeFalse();
});

it('matches subdomains only with a wildcard, and not the parent', function (): void {
    // Strict on purpose: a `*.corp.example.com` that silently also admitted
    // the parent would be a quiet privilege widening.
    $wildcard = new EmailDomainIs(['*.example.com']);

    expect(ruleAccepts($wildcard, 'alice@mail.example.com'))->toBeTrue()
        ->and(ruleAccepts($wildcard, 'alice@a.b.example.com'))->toBeTrue()
        ->and(ruleAccepts($wildcard, 'alice@example.com'))->toBeFalse()
        ->and(ruleAccepts(new EmailDomainIs(['example.com', '*.example.com']), 'alice@example.com'))->toBeTrue();
});

it('does not match a domain that merely ends with the pattern', function (): void {
    // The hole in `str_ends_with($email, 'example.com')` and in a wildcard
    // implemented without the leading dot.
    $rule = new EmailDomainIs(['*.example.com']);

    expect(ruleAccepts($rule, 'alice@evilexample.com'))->toBeFalse()
        ->and(ruleAccepts($rule, 'alice@notexample.com'))->toBeFalse()
        ->and(ruleAccepts(new EmailDomainIs(['example.com']), 'alice@example.com.attacker.test'))->toBeFalse();
});

it('is the exact complement in EmailDomainIsNot', function (string $email): void {
    // Both share one matcher precisely so a pattern cannot mean one thing to
    // the allow-list and another to the deny-list — in a deny-list that
    // difference is a bypass.
    $patterns = ['example.com', '*.example.com'];

    expect(ruleAccepts(new EmailDomainIs($patterns), $email))
        ->not->toBe(ruleAccepts(new EmailDomainIsNot($patterns), $email));
})->with([
    'alice@example.com',
    'alice@mail.example.com',
    'alice@other.test',
    'alice@evilexample.com',
]);

it('rejects a malformed address', function (mixed $value): void {
    expect(ruleAccepts(new EmailDomainIs(['example.com']), $value))->toBeFalse();
})->with(['no-at-sign', 'alice@', '@example.com', 'alice@@']);

it('splits on the last at-sign, so a quoted local part still works', function (): void {
    // `"a@b"@example.com` is a legal address whose domain is example.com.
    expect(ruleAccepts(new EmailDomainIs(['example.com']), '"a@b"@example.com'))->toBeTrue();
});

// =========================================================================
// NotDisposableEmail / NotRoleEmail — list comes from the contract
// =========================================================================

it('rejects an address at a listed disposable domain', function (): void {
    $rule = new NotDisposableEmail(fakeList(['throwaway.test']));

    expect(ruleAccepts($rule, 'alice@throwaway.test'))->toBeFalse()
        ->and(ruleAccepts($rule, 'alice@example.com'))->toBeTrue();
});

it('rejects a role account', function (): void {
    $rule = new NotRoleEmail(fakeList(['info', 'sales']));

    expect(ruleAccepts($rule, 'info@example.com'))->toBeFalse()
        ->and(ruleAccepts($rule, 'INFO@example.com'))->toBeFalse()
        ->and(ruleAccepts($rule, 'alice@example.com'))->toBeTrue();
});

it('strips a plus tag before checking the role list', function (): void {
    // info+signup@ is still the info mailbox. Without this the rule is
    // bypassed by typing four characters.
    $rule = new NotRoleEmail(fakeList(['info']));

    expect(ruleAccepts($rule, 'info+signup@example.com'))->toBeFalse()
        ->and(ruleAccepts($rule, 'alice+info@example.com'))->toBeTrue();
});

// =========================================================================
// The bundled fallbacks and their binding
// =========================================================================

it('binds the bundled lists so the rules work with nothing configured', function (): void {
    expect(resolve(DisposableDomainList::class))->toBeInstanceOf(BundledDisposableDomainList::class)
        ->and(resolve(RoleAccountList::class))->toBeInstanceOf(BundledRoleAccountList::class);
});

it('leaves an already-bound implementation alone', function (): void {
    // singletonIf, not singleton: laranail/email binds these contracts
    // unconditionally, and whichever provider registers second must still
    // give the right answer. A consumer does not control provider order.
    $fake = fakeList(['bound-first.test']);
    app()->instance(DisposableDomainList::class, $fake);

    app()->register(ValidationServiceProvider::class, force: true);

    expect(resolve(DisposableDomainList::class))->toBe($fake);
});

it('recognises real disposable domains through the bundled snapshot', function (): void {
    $rule = new NotDisposableEmail(new BundledDisposableDomainList);

    expect(ruleAccepts($rule, 'alice@mailinator.com'))->toBeFalse()
        ->and(ruleAccepts($rule, 'alice@gmail.com'))->toBeTrue();
});

it('recognises RFC 2142 role mailboxes through the bundled snapshot', function (string $local): void {
    expect(ruleAccepts(new NotRoleEmail(new BundledRoleAccountList), "{$local}@example.com"))->toBeFalse();
})->with(['postmaster', 'abuse', 'hostmaster', 'webmaster', 'info', 'sales', 'support']);
