<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Validation\Support\Text;

use Simtabi\Laranail\Validation\Contracts\ReservedUsernameList;
use Simtabi\Laranail\Validation\Support\I18n\CodeFile;

/**
 * The full bundled reserved set: ~350 infrastructure, route and product
 * names curated from the archive's list (OCR artifacts repaired, profanity
 * removed — what counts as offensive is TermList policy, not bundled
 * data). NOT bound by default: it refuses names that are merely
 * undesirable rather than concretely harmful, which is a policy an
 * application should choose, not inherit. Bind it to choose it:
 *
 *     $this->app->singleton(ReservedUsernameList::class, ExtendedReservedUsernameList::class);
 */
final class ExtendedReservedUsernameList implements ReservedUsernameList
{
    /** @var list<string>|null */
    private ?array $names = null;

    public function names(): array
    {
        return $this->names ??= array_keys(
            CodeFile::load(dirname(__DIR__, 3).'/resources/data/reserved-usernames.txt'),
        );
    }
}
