<?php declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | String rule aliases
    |--------------------------------------------------------------------------
    |
    | The rule library's canonical surface is its rule classes. String aliases
    | are opt-in because Laravel keeps validator extensions in a flat map and
    | resolves them last-writer-wins: registering a generic name like `iban`
    | or `slug` silently replaces whatever a sibling package, a third-party
    | package, or the application itself already registered under it.
    |
    | Enable them only if you want `'account' => 'laranail_iban'` to work in
    | plain rule strings. Every alias is vendor-prefixed for the reason above.
    | The prefix is configurable so an application that already owns the name
    | can move ours out of the way rather than fight it.
    |
    */

    'aliases' => [
        'enabled' => env('LARANAIL_VALIDATION_ALIASES', false),
        'prefix' => 'laranail_',
    ],

    /*
    |--------------------------------------------------------------------------
    | Deliverability lookups
    |--------------------------------------------------------------------------
    |
    | How long a domain's MX result is cached, in seconds. The same handful of
    | domains dominates any signup form, so this is what keeps DeliverableEmail
    | from issuing a lookup per submission. Only read when the bundled
    | CachedDnsResolver is in use.
    |
    */

    'dns' => [
        'ttl' => env('LARANAIL_VALIDATION_DNS_TTL', 3600),
    ],

    /*
    |--------------------------------------------------------------------------
    | Batched database validation
    |--------------------------------------------------------------------------
    |
    | Wildcard `exists`/`unique` rules are collapsed into a single whereIn per
    | table+column instead of one query per item. This caps how many values a
    | single group may carry before the batch is refused, so a hostile payload
    | cannot turn one request into an unbounded IN list.
    |
    | Read at boot only. Mutating it per-request is unsafe under Octane.
    |
    */

    'batch' => [
        'max_values_per_group' => 10_000,
    ],

];
