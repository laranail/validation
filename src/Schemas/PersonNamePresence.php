<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Validation\Schemas;

/**
 * How hard a {@see PersonNameSchema} insists on a name.
 *
 * `AtLeastOne` is the default and the interesting one. Making any single
 * column mandatory forces a placeholder into it — a column full of "." is a
 * null that lies — while requiring nothing lets a person exist with no name at
 * all. What is actually true is that a person has *a* name, and which box it
 * lands in is a property of the naming culture, not of the record.
 */
enum PersonNamePresence
{
    /** One of the declared fields must be filled; which one is the person's business. */
    case AtLeastOne;

    /** Every declared field is required — for the rare form that genuinely needs all of them. */
    case All;

    /** No requirement, for a partial update where absence means "unchanged". */
    case Optional;
}
