<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Validation\Support;

use Simtabi\Laranail\Validation\Contracts\TermList;

/**
 * A {@see TermList} over arrays the application supplies.
 *
 * The smallest honest implementation of the contract: no dataset ships with
 * this package (see the contract for why), and most applications keep their
 * terms in config or a database anyway. This class is the one-liner between
 * either of those and the container:
 *
 *     $this->app->singleton(TermList::class, fn () => new InlineTermList(
 *         terms: config('moderation.blocked_terms'),
 *         allowed: config('moderation.allowed_words'),
 *     ));
 *
 * It serves equally as the test double — construct it with two or three
 * terms and assert the rule's behaviour without inventing a fake.
 */
final readonly class InlineTermList implements TermList
{
    /**
     * @param list<string> $terms Lowercase terms to match.
     * @param list<string> $allowed Words containing a term that are not one
     *                              — the Scunthorpe problem.
     */
    public function __construct(
        private array $terms,
        private array $allowed = [],
    ) {}

    public function terms(): array
    {
        return $this->terms;
    }

    public function allowed(): array
    {
        return $this->allowed;
    }
}
