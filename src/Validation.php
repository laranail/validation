<?php declare(strict_types=1);

namespace Simtabi\Laranail\Validation;

use Simtabi\Laranail\Validation\Testing\ValidationFake;

/**
 * The package's one static accessor, existing for exactly the reason the
 * plan allowed it: the fake story. `Validation::fake()` gives a consumer's
 * test a recorder over the RuleSet event lifecycle; everything else in the
 * package stays instance- or factory-shaped.
 */
final class Validation
{
    private function __construct() {}

    /**
     * Record subsequent RuleSet runs for assertion. Validation still runs
     * for real — verdicts, exceptions and listeners are untouched; the
     * fake only remembers outcomes:
     *
     *     $fake = Validation::fake();
     *     $service->import($rows);
     *     $fake->assertFailed(fn ($errors) => $errors->has('email'));
     */
    public static function fake(): ValidationFake
    {
        return new ValidationFake();
    }
}
