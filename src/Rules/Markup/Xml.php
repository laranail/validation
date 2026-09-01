<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Validation\Rules\Markup;

use Closure;
use DOMDocument;
use Illuminate\Contracts\Validation\ValidationRule;
use LibXMLError;

/**
 * Well-formed XML, optionally valid against an XSD schema.
 *
 *     new Xml()
 *     new Xml(schema: resource_path('schemas/invoice.xsd'))
 *
 * Nothing in the Laravel ecosystem validates against a schema, which is the
 * reason this exists: "is it XML" is a weak question, and "is it the document
 * we agreed on" is the one an integration actually asks.
 *
 * **External entities are disabled.** Parsing untrusted XML with entity
 * substitution on is XXE — the classic path to reading `/etc/passwd` or
 * making the server issue requests on an attacker's behalf. libxml has
 * disabled network access by default since 2.9, but this does not rely on the
 * library version: `LIBXML_NONET` is passed explicitly and entity loading is
 * restored to its previous state afterwards rather than left flipped, since
 * that setting is process-global and another package may depend on it.
 *
 * A schema failure and a well-formedness failure produce different messages,
 * because they are different problems for whoever has to fix the document.
 *
 * Pure tier — no IO. The schema is read from local disk; `LIBXML_NONET`
 * guarantees the parser never reaches the network.
 */
final readonly class Xml implements ValidationRule
{
    public function __construct(private ?string $schema = null) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || trim($value) === '') {
            $fail('laranail/validation::validation.xml.malformed')->translate();

            return;
        }

        $previous = libxml_use_internal_errors(true);
        libxml_clear_errors();

        try {
            $document = new DOMDocument;

            // LIBXML_NONET: no network fetches for entities or includes.
            // LIBXML_NOENT is deliberately NOT set — expanding entities is the
            // XXE vector.
            if ($document->loadXML($value, LIBXML_NONET) === false) {
                $fail('laranail/validation::validation.xml.malformed')
                    ->translate(['reason' => $this->firstError()]);

                return;
            }

            if ($this->schema === null) {
                return;
            }

            if ($this->schema === '' || ! is_file($this->schema)) {
                // A missing schema is a deployment fault, not a bad document.
                // Failing the input would blame the user for it.
                $fail('laranail/validation::validation.xml.schema_missing')->translate();

                return;
            }

            libxml_clear_errors();

            if (! $document->schemaValidate($this->schema)) {
                $fail('laranail/validation::validation.xml.schema')
                    ->translate(['reason' => $this->firstError()]);
            }
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
    }

    private function firstError(): string
    {
        $errors = libxml_get_errors();
        $first = $errors[0] ?? null;

        return $first instanceof LibXMLError ? trim($first->message) : '';
    }
}
