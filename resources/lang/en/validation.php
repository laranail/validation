<?php

declare(strict_types=1);

/*
 * Messages for the extended rule library.
 *
 * Namespaced `laranail/validation::`, so publishing lands in
 * lang/vendor/laranail-validation and nothing here can shadow Laravel's own
 * lang/en/validation.php.
 *
 * Keys mirror the rule's snake_case name. Keep them flat: a nested structure
 * reads well but makes a missing key render as a bare dotted string rather
 * than fail loudly in tests.
 */

return [

    // Banking
    'bic'        => 'The :attribute must be a valid BIC/SWIFT code.',
    'bsb_number' => 'The :attribute must be a valid BSB number.',
    'iban'       => 'The :attribute must be a valid IBAN.',
    'isin'       => 'The :attribute must be a valid ISIN.',
    'luhn'       => 'The :attribute must pass the Luhn checksum.',

    // Codes
    'asin'  => 'The :attribute must be a valid ASIN.',
    'ean'   => 'The :attribute must be a valid EAN barcode.',
    'gtin'  => 'The :attribute must be a valid GTIN.',
    'isbn'  => 'The :attribute must be a valid ISBN.',
    'ismn'  => 'The :attribute must be a valid ISMN.',
    'upc_e' => 'The :attribute must be a valid UPC-E barcode.',
    'issn'  => 'The :attribute must be a valid ISSN.',

    // Identifiers
    'hash_digest' => 'The :attribute must be a :length-character hex digest.',
    'imei'        => 'The :attribute must be a valid IMEI.',
    'jwt'         => 'The :attribute must be a valid JSON Web Token.',
    'semver'      => 'The :attribute must be a valid semantic version.',
    'vin'         => 'The :attribute must be a valid vehicle identification number.',

    // Telecom
    //
    // Deliberately several keys rather than one. A single "invalid phone number" tells the user
    // nothing they can act on; "must be a mobile number" and "must be a number from KE" each name
    // the thing to change. None of the three upstream Filament phone packages ships any message at
    // all — each tells you to add one to your own validation.php.
    'phone'            => 'The :attribute must be a valid phone number.',
    'phone_possible'   => 'The :attribute is not a possible phone number.',
    'phone_country'    => 'The :attribute must be a phone number from :country.',
    'phone_type'       => 'The :attribute must be a :type number.',
    'phone_extension'  => 'The :attribute must not include an extension.',
    'phone_short_code' => 'The :attribute must be a full phone number, not a short code.',
    'phone_emergency'  => 'The :attribute must not be an emergency number.',
    'phone_unique'     => 'The :attribute has already been taken.',

    // Net
    'cidr'        => 'The :attribute must be a valid CIDR network.',
    'domain_name' => 'The :attribute must be a valid domain name.',
    'private_ip'  => 'The :attribute must be a private or reserved IP address.',
    'public_ip'   => 'The :attribute must be a publicly routable IP address.',
    'subdomain'   => 'The :attribute must be a valid subdomain.',

    //
    // Several keys rather than one, because "that is not a URL" and "that
    // scheme is not allowed here" send the reader to different places. A
    // single message for both makes a working link look broken for no
    // stated reason.
    'url' => [
        'credentials'  => 'The :attribute must not contain a username or password.',
        'fragment'     => 'The :attribute must not contain a fragment.',
        'host'         => 'The :attribute must have a valid host.',
        'host_is'      => 'The :attribute must be at one of: :hosts.',
        'host_is_not'  => 'The :attribute must not be at any of: :hosts.',
        'malformed'    => 'The :attribute must be a valid URL.',
        'port'         => 'The :attribute must use one of these ports: :ports.',
        'private_host' => 'The :attribute must point at a public address.',
        'query'        => 'The :attribute must not contain a query string.',
        'scheme'       => 'The :attribute must use one of these schemes: :schemes.',
    ],

    'mac_address' => [
        'broadcast' => 'The :attribute must not be the broadcast address.',
        'format'    => 'The :attribute must be written in one of these notations: :formats.',
        'length'    => 'The :attribute must be :bytes bytes.',
        'local'     => 'The :attribute must be a manufacturer-assigned address, not a randomised one.',
        'malformed' => 'The :attribute must be a valid MAC address.',
        'multicast' => 'The :attribute must be a unicast address.',
        'null'      => 'The :attribute must not be the null address.',
        'oui'       => 'The :attribute must begin with one of: :ouis.',
    ],

    'in_cidr_range' => 'The :attribute must be within one of these networks: :networks.',

    'slug' => 'The :attribute must be a URL slug: lowercase letters, digits and single hyphens, with no hyphen at the start or end.',

    'username'          => 'The :attribute may contain letters, digits, and single dots, hyphens or underscores between them — not at the start, at the end, or doubled.',
    'username_reserved' => 'The :attribute is reserved. Please choose another.',

    // Three keys rather than one. A field whose characters were fine and whose count was not gets
    // sent looking for a bad character that is not there.
    'person_name'          => 'The :attribute must be a name: letters, marks, spaces, apostrophes and hyphens.',
    'person_name_min'      => 'The :attribute must contain at least :min names.',
    'person_name_max'      => 'The :attribute must not contain more than :max names.',
    'person_name_required' => 'Please provide at least one of :values.',

    'contains_html' => 'The :attribute must contain HTML markup.',
    'html_clean'    => 'The :attribute must not contain HTML tags.',
    'max_words'     => 'The :attribute must not contain more than :max words.',
    'min_words'     => 'The :attribute must contain at least :min words.',
    'salutation'    => 'The :attribute must be a recognised salutation.',

    'without_spaces' => 'The :attribute must not contain any spaces, including non-breaking and zero-width ones.',

    // Storage
    'file_exists_on_disk' => 'The :attribute must name an existing file.',

    // Networking probes
    'has_gravatar' => 'The :attribute must have a Gravatar.',
    'image_url'    => 'The :attribute must be a URL serving an image.',

    // Payment
    'card_cvc'             => 'The :attribute must be a valid security code.',
    'card_expiry'          => 'The :attribute must be a valid expiry date that has not passed.',
    'card_number'          => 'The :attribute is not a recognised card number.',
    'card_number_brand'    => 'The :attribute must be one of these card brands: :brands.',
    'card_number_checksum' => 'The :attribute fails the card number check digit.',
    'card_number_length'   => 'The :attribute must be :lengths digits for :brand.',

    // Chrono
    'date_interval'          => 'The :attribute must be an ISO 8601 duration.',
    'date_interval_positive' => 'The :attribute must be a non-zero ISO 8601 duration.',
    'max_date_difference'    => 'The :attribute must be within :hours hours of the reference date.',
    'minimum_age'            => 'The :attribute must be a date of birth at least :years years ago.',
    'minute_in'              => 'The :attribute must be a time whose minutes are one of: :minutes.',
    'rfc3339'                => 'The :attribute must be an RFC 3339 timestamp.',
    'time_of_day'            => 'The :attribute must be a valid time of day.',
    'timezone_abbreviation'  => 'The :attribute must be a timezone abbreviation.',
    'unix_timestamp'         => 'The :attribute must be a Unix timestamp.',

    // Encoding
    'base64'            => 'The :attribute must be a base64-encoded value.',
    'base64_image'      => 'The :attribute must be a base64-encoded image of an accepted type.',
    'base64_image_size' => 'The :attribute must be an image no larger than :max.',
    'data_uri'          => 'The :attribute must be a valid data URI.',

    // I18n
    'country_code'          => 'The :attribute must be a valid ISO 3166-1 country code.',
    'currency_code'         => 'The :attribute must be a valid ISO 4217 currency code.',
    'currency_code_numeric' => 'The :attribute must be a valid ISO 4217 numeric currency code.',
    'currency_symbol'       => 'The :attribute must be a recognised currency symbol.',
    'language_code'         => 'The :attribute must be a valid ISO 639-1 language code.',

    // Geo
    'ca_province' => 'The :attribute must be a Canadian province or territory.',
    'lat_lng'     => 'The :attribute must be a latitude,longitude pair.',
    'latitude'    => 'The :attribute must be a latitude between -90 and 90.',
    'longitude'   => 'The :attribute must be a longitude between -180 and 180.',
    'us_state'    => 'The :attribute must be a US state.',

    // Email
    'email' => [
        'disposable'    => 'The :attribute must not be a disposable email address.',
        'domain_is'     => 'The :attribute must be at one of: :domains.',
        'domain_is_not' => 'The :attribute must not be at any of: :domains.',
        'malformed'     => 'The :attribute must be a valid email address.',
        'role'          => 'The :attribute must belong to a person, not a shared mailbox.',
        'subaddress'    => 'The :attribute must not contain a plus tag.',
        'undeliverable' => 'The :attribute is at a domain that cannot receive mail.',
    ],

    // Database
    'authorized'        => 'The selected :attribute is invalid.',
    'compare_to_column' => 'The :attribute is outside the allowed bound.',
    'models_exist'      => [
        'array'   => 'The :attribute must be a list of identifiers.',
        'missing' => 'The :attribute contains values that do not exist: :values.',
    ],

    // Structure
    //
    // Nested and several keys, because "the field is invalid" is useless when
    // the field holds a dozen values. Each key names what to change.
    'delimited' => [
        'distinct' => 'The :attribute must not repeat an entry.',
        'empty'    => 'Entry :position of :attribute is empty — check for a stray separator.',
        'invalid'  => 'The :attribute must be a delimited list.',
        'item'     => 'Entry :position of :attribute is not valid.',
        'max'      => 'The :attribute must not have more than :max entries.',
        'min'      => 'The :attribute must have at least :min entries.',
    ],

    // Crypto
    'bitcoin_address'  => 'The :attribute must be a valid Bitcoin address.',
    'ethereum_address' => 'The :attribute must be a valid Ethereum address.',

    // Postal
    'postal_code' => 'The :attribute is not a valid postcode for the selected country.',

    // Numbers
    'parity' => [
        'even' => 'The :attribute must be an even number.',
        'odd'  => 'The :attribute must be an odd number.',
    ],
    'monetary_amount' => 'The :attribute must be an amount with at most :decimals decimal places.',

    // Colour
    'css_color' => 'The :attribute must be a colour in one of these notations: :notations.',

    // Anti-spam
    'honeypot'          => 'The :attribute could not be submitted.',
    'submission_timing' => [
        'too_fast' => 'The form was submitted too quickly. Please try again.',
        'expired'  => 'The form has expired. Please reload and try again.',
    ],

    // Vendor identifiers
    'vendor_identifier' => 'The :attribute must be a valid :vendor identifier.',

    // Fiscal
    'vat_number'          => 'The :attribute must be a valid VAT number.',
    'national_identifier' => 'The :attribute must be a valid :country national identification number.',

    // Profanity
    'no_profanity' => 'The :attribute contains language that is not allowed.',

    // Markup
    'xml' => [
        'malformed'      => 'The :attribute must be well-formed XML.',
        'schema'         => 'The :attribute does not match the required schema.',
        'schema_missing' => 'The :attribute could not be checked: the schema is unavailable.',
    ],

    /*
    | Nested, because CaseStyle appends its style to the key — one rule with a
    | parameter rather than five classes differing by a pattern and a message.
    */
    'case_style' => [
        'camel'  => 'The :attribute must be camelCase.',
        'kebab'  => 'The :attribute must be kebab-case.',
        'pascal' => 'The :attribute must be PascalCase.',
        'snake'  => 'The :attribute must be snake_case.',
        'title'  => 'The :attribute must be Title Case.',
    ],

];
