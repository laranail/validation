<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Validation\Contracts\Payment;

use Simtabi\Laranail\Validation\Support\Payment\CardBrand;

/**
 * The set of card brands the Payment rules recognise.
 *
 * A contract because brand data is exactly the kind that outlives any
 * snapshot: ranges are reassigned, regional brands matter to some
 * applications and not others, and a store-card programme is a brand this
 * package has never heard of. The bundled default carries the fifteen
 * legacy brands (corrected — see {@see CardBrand}); bind your own to add,
 * remove or reorder.
 */
interface CardBrandCatalogue
{
    /** @return list<CardBrand> */
    public function brands(): array;

    /**
     * The brand a (digits-only) number belongs to — the most specific
     * range match wins — or null when no range claims it.
     */
    public function identify(string $number): ?CardBrand;
}
