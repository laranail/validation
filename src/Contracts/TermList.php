<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Validation\Contracts;

/**
 * A list of terms a value must not contain.
 *
 * A contract rather than bundled data, and that is a licensing decision as
 * much as a design one. The obvious sources are not usable here:
 * `laravel-validation-rules/offensive` is LGPL-3.0 and cannot be copied into
 * an MIT package, and the multi-language word lists floating around the
 * ecosystem generally record no licence at all.
 *
 * It is also the right shape regardless. What counts as unacceptable is a
 * product decision that differs by audience, jurisdiction and moderation
 * policy, and it changes over time. A list frozen into a package is wrong for
 * most of the people who install it.
 *
 * So this package ships the MATCHING — normalisation, obfuscation folding,
 * word boundaries, and the false-positive allow-list — and the application
 * supplies the words.
 */
interface TermList
{
    /**
     * The terms to match, lowercase.
     *
     * @return list<string>
     */
    public function terms(): array;

    /**
     * Words that contain a term but are not one — the Scunthorpe problem.
     *
     * Without this any list containing a short term rejects real place names,
     * surnames and ordinary vocabulary, and the people it rejects are exactly
     * the ones least able to work around it.
     *
     * @return list<string>
     */
    public function allowed(): array;
}
