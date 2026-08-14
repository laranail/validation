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

];
