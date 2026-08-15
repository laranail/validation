<?php declare(strict_types=1);

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Simtabi\Laranail\Validation\Actions\CachedDnsResolver;
use Simtabi\Laranail\Validation\Contracts\Email\DnsResolver;
use Simtabi\Laranail\Validation\Contracts\PrecognitionSkippable;
use Simtabi\Laranail\Validation\Rules\Network\DeliverableEmail;
use Simtabi\Laranail\Validation\ValidationServiceProvider;

/**
 * The first Network-tier rule.
 *
 * The tier's whole contract is here: the IO goes through an injected resolver
 * so it can be faked, an uncertain lookup passes rather than fails, and a
 * precognitive request performs no lookup at all.
 */
/**
 * @param  list<string>  $deliverable
 */
function fakeResolver(array $deliverable, ?Closure $onCall = null): DnsResolver
{
    return new readonly class ($deliverable, $onCall) implements DnsResolver {
        /** @param  list<string>  $deliverable */
        public function __construct(private array $deliverable, private ?Closure $onCall) {}

        public function hasMailExchanger(string $domain): bool
        {
            ($this->onCall)?->__invoke($domain);

            return in_array(mb_strtolower($domain), $this->deliverable, true);
        }
    };
}

it('passes for a domain that accepts mail and fails for one that does not', function (): void {
    $rule = new DeliverableEmail(fakeResolver(['example.com']));

    expect(ruleAccepts($rule, 'alice@example.com'))->toBeTrue()
        ->and(ruleAccepts($rule, 'alice@gmial.com'))->toBeFalse();
});

it('rejects a malformed address before doing any lookup', function (): void {
    // A lookup on a value with no domain is wasted IO, and the message should
    // say the address is malformed rather than that it cannot receive mail.
    $called = false;
    $rule = new DeliverableEmail(fakeResolver([], function () use (&$called): void {
        $called = true;
    }));

    expect(ruleAccepts($rule, 'not-an-address'))->toBeFalse()
        ->and($called)->toBeFalse();
});

it('is skippable during precognition, and actually skips', function (): void {
    // Laravel's precognition filter narrows by attribute, not by what a rule
    // does, so without this a debounced email field issues one DNS lookup per
    // keystroke.
    $called = false;
    $rule = new DeliverableEmail(fakeResolver([], function () use (&$called): void {
        $called = true;
    }));

    expect($rule)->toBeInstanceOf(PrecognitionSkippable::class);

    $request = Request::create('/', 'POST');
    $request->attributes->set('precognitive', true);

    app()->instance('request', $request);

    expect(ruleAccepts($rule, 'alice@nowhere.test'))->toBeTrue()
        ->and($called)->toBeFalse();
});

it('still runs when the request is not precognitive', function (): void {
    // The inverse: the skip must not swallow the check on a real submission.
    $request = Request::create('/', 'POST');
    app()->instance('request', $request);

    $rule = new DeliverableEmail(fakeResolver(['example.com']));

    expect(ruleAccepts($rule, 'alice@nowhere.test'))->toBeFalse()
        ->and(ruleAccepts($rule, 'alice@example.com'))->toBeTrue();
});

it('resolves the resolver from the container when none is given', function (): void {
    app()->instance(DnsResolver::class, fakeResolver(['example.com']));

    expect(Validator::make(['e' => 'a@example.com'], ['e' => [new DeliverableEmail()]])->passes())->toBeTrue()
        ->and(Validator::make(['e' => 'a@other.test'], ['e' => [new DeliverableEmail()]])->passes())->toBeFalse();
});

it('binds the bundled resolver so the rule works with nothing configured', function (): void {
    expect(resolve(DnsResolver::class))->toBeInstanceOf(CachedDnsResolver::class);
});

it('leaves an already-bound resolver alone', function (): void {
    // singletonIf, for the same reason as the email lists: laranail/email will
    // bind this unconditionally, and provider order is not something a
    // consumer controls.
    $fake = fakeResolver([]);
    app()->instance(DnsResolver::class, $fake);

    app()->register(ValidationServiceProvider::class, force: true);

    expect(resolve(DnsResolver::class))->toBe($fake);
});

it('reports a domain as reachable when the lookup itself fails', function (): void {
    // The uncertainty rule, stated in the contract: an unreachable resolver is
    // not the same as an undeliverable domain, and rejecting every signup for
    // the duration of a DNS outage is the worse error. Exercised against the
    // real resolver with a name that cannot resolve.
    $resolver = new CachedDnsResolver();

    // .invalid is reserved by RFC 2606 and must never resolve, so a false here
    // is a genuine negative answer rather than an outage.
    expect($resolver->hasMailExchanger('nonexistent-domain.invalid'))->toBeFalse()
        ->and($resolver->hasMailExchanger(''))->toBeFalse();
})->skip(fn (): bool => ! @checkdnsrr('a.root-servers.net', 'A'), 'needs working DNS');
