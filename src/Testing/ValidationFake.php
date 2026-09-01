<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Validation\Testing;

use Closure;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\MessageBag;
use PHPUnit\Framework\Assert;
use Simtabi\Laranail\Validation\Events\ValidationCompleted;
use Simtabi\Laranail\Validation\Events\ValidationFailed;

/**
 * Records RuleSet runs for assertion — created via `Validation::fake()`.
 *
 * A thin recorder over the §5.6 events, NOT an interceptor: validation
 * still runs for real, verdicts and exceptions are unchanged, and the
 * fake only remembers what happened so a consumer's test can assert it
 * without re-validating. `Event::fake()` would silence the package's own
 * listeners; this listens alongside them.
 */
final class ValidationFake
{
    /** @var list<array<string, mixed>> Validated payloads of passing runs. */
    private array $completed = [];

    /** @var list<MessageBag> Error bags of failing runs. */
    private array $failed = [];

    public function __construct()
    {
        Event::listen(ValidationCompleted::class, function (ValidationCompleted $event): void {
            $this->completed[] = $event->validated;
        });

        Event::listen(ValidationFailed::class, function (ValidationFailed $event): void {
            $this->failed[] = $event->errors;
        });
    }

    /** @param  Closure(array<string, mixed>): bool|null  $matching */
    public function assertValidated(?Closure $matching = null): void
    {
        if (! $matching instanceof Closure) {
            Assert::assertNotEmpty($this->completed, 'Expected at least one passing validation run; none happened.');

            return;
        }

        Assert::assertTrue(
            array_any($this->completed, static fn (array $validated): bool => $matching($validated)),
            'No passing validation run matched the given callback.',
        );
    }

    /** @param  Closure(MessageBag): bool|null  $matching */
    public function assertFailed(?Closure $matching = null): void
    {
        if (! $matching instanceof Closure) {
            Assert::assertNotEmpty($this->failed, 'Expected at least one failing validation run; none happened.');

            return;
        }

        Assert::assertTrue(
            array_any($this->failed, static fn (MessageBag $errors): bool => $matching($errors)),
            'No failing validation run matched the given callback.',
        );
    }

    public function assertNothingValidated(): void
    {
        Assert::assertSame([], $this->completed, 'Expected no passing validation runs.');
    }

    public function assertNothingFailed(): void
    {
        Assert::assertSame([], $this->failed, 'Expected no failing validation runs.');
    }
}
