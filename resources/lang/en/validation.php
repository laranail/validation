<?php declare(strict_types=1);

/*
 * Messages for the extended rule library.
 *
 * Namespaced `laranail-validation::`, so publishing lands in
 * lang/vendor/laranail-validation and nothing here can shadow Laravel's own
 * lang/en/validation.php.
 *
 * Keys mirror the rule's snake_case name. Keep them flat: a nested structure
 * reads well but makes a missing key render as a bare dotted string rather
 * than fail loudly in tests.
 */

return [

    // Banking
    'bic' => 'The :attribute must be a valid BIC/SWIFT code.',
    'iban' => 'The :attribute must be a valid IBAN.',
    'isin' => 'The :attribute must be a valid ISIN.',
    'luhn' => 'The :attribute must pass the Luhn checksum.',

    // Codes
    'ean' => 'The :attribute must be a valid EAN barcode.',
    'gtin' => 'The :attribute must be a valid GTIN.',
    'isbn' => 'The :attribute must be a valid ISBN.',
    'issn' => 'The :attribute must be a valid ISSN.',

    // Identifiers
    'imei' => 'The :attribute must be a valid IMEI.',
    'jwt' => 'The :attribute must be a valid JSON Web Token.',
    'semver' => 'The :attribute must be a valid semantic version.',
    'vin' => 'The :attribute must be a valid vehicle identification number.',

    // Telecom
    //
    // Deliberately several keys rather than one. A single "invalid phone number" tells the user
    // nothing they can act on; "must be a mobile number" and "must be a number from KE" each name
    // the thing to change. None of the three upstream Filament phone packages ships any message at
    // all — each tells you to add one to your own validation.php.
    'phone' => 'The :attribute must be a valid phone number.',
    'phone_possible' => 'The :attribute is not a possible phone number.',
    'phone_country' => 'The :attribute must be a phone number from :country.',
    'phone_type' => 'The :attribute must be a :type number.',
    'phone_extension' => 'The :attribute must not include an extension.',
    'phone_short_code' => 'The :attribute must be a full phone number, not a short code.',
    'phone_emergency' => 'The :attribute must not be an emergency number.',
    'phone_unique' => 'The :attribute has already been taken.',

    // Net
    'cidr' => 'The :attribute must be a valid CIDR network.',
    'domain_name' => 'The :attribute must be a valid domain name.',
    'private_ip' => 'The :attribute must be a private or reserved IP address.',
    'public_ip' => 'The :attribute must be a publicly routable IP address.',
    'subdomain' => 'The :attribute must be a valid subdomain.',

    'slug' => 'The :attribute must be a URL slug: lowercase letters, digits and single hyphens, with no hyphen at the start or end.',

    'username' => 'The :attribute may contain letters, digits, and single dots, hyphens or underscores between them — not at the start, at the end, or doubled.',

    'person_name' => 'The :attribute must be a name: letters, marks, spaces, apostrophes and hyphens.',

    'html_clean' => 'The :attribute must not contain HTML tags.',

    'without_spaces' => 'The :attribute must not contain any spaces, including non-breaking and zero-width ones.',

    // Geo
    'ca_province' => 'The :attribute must be a Canadian province or territory.',
    'lat_lng' => 'The :attribute must be a latitude,longitude pair.',
    'latitude' => 'The :attribute must be a latitude between -90 and 90.',
    'longitude' => 'The :attribute must be a longitude between -180 and 180.',
    'us_state' => 'The :attribute must be a US state.',

    // Email
    'email' => [
        'disposable' => 'The :attribute must not be a disposable email address.',
        'domain_is' => 'The :attribute must be at one of: :domains.',
        'domain_is_not' => 'The :attribute must not be at any of: :domains.',
        'malformed' => 'The :attribute must be a valid email address.',
        'role' => 'The :attribute must belong to a person, not a shared mailbox.',
        'undeliverable' => 'The :attribute is at a domain that cannot receive mail.',
    ],

    // Database
    'authorized' => 'The selected :attribute is invalid.',
    'models_exist' => [
        'array' => 'The :attribute must be a list of identifiers.',
        'missing' => 'The :attribute contains values that do not exist: :values.',
    ],

    // Structure
    //
    // Nested and several keys, because "the field is invalid" is useless when
    // the field holds a dozen values. Each key names what to change.
    'delimited' => [
        'distinct' => 'The :attribute must not repeat an entry.',
        'empty' => 'Entry :position of :attribute is empty — check for a stray separator.',
        'invalid' => 'The :attribute must be a delimited list.',
        'item' => 'Entry :position of :attribute is not valid.',
        'max' => 'The :attribute must not have more than :max entries.',
        'min' => 'The :attribute must have at least :min entries.',
    ],

    // Crypto
    'bitcoin_address' => 'The :attribute must be a valid Bitcoin address.',
    'ethereum_address' => 'The :attribute must be a valid Ethereum address.',

    // Postal
    'postal_code' => 'The :attribute is not a valid postcode for the selected country.',

    // Numbers
    'parity' => [
        'even' => 'The :attribute must be an even number.',
        'odd' => 'The :attribute must be an odd number.',
    ],
    'monetary_amount' => 'The :attribute must be an amount with at most :decimals decimal places.',

    // Colour
    'css_color' => 'The :attribute must be a colour in one of these notations: :notations.',

    // Anti-spam
    'honeypot' => 'The :attribute could not be submitted.',
    'submission_timing' => [
        'too_fast' => 'The form was submitted too quickly. Please try again.',
        'expired' => 'The form has expired. Please reload and try again.',
    ],

    // Vendor identifiers
    'vendor_identifier' => 'The :attribute must be a valid :vendor identifier.',

    // Fiscal
    'national_identifier' => 'The :attribute must be a valid :country national identification number.',

    // Profanity
    'no_profanity' => 'The :attribute contains language that is not allowed.',

    // Markup
    'xml' => [
        'malformed' => 'The :attribute must be well-formed XML.',
        'schema' => 'The :attribute does not match the required schema.',
        'schema_missing' => 'The :attribute could not be checked: the schema is unavailable.',
    ],

    /*
    | Nested, because CaseStyle appends its style to the key — one rule with a
    | parameter rather than five classes differing by a pattern and a message.
    */
    'case_style' => [
        'camel' => 'The :attribute must be camelCase.',
        'kebab' => 'The :attribute must be kebab-case.',
        'pascal' => 'The :attribute must be PascalCase.',
        'snake' => 'The :attribute must be snake_case.',
        'title' => 'The :attribute must be Title Case.',
    ],

];
