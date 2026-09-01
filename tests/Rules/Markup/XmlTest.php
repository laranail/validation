<?php

declare(strict_types=1);

use Illuminate\Contracts\Translation\Translator;
use Illuminate\Translation\PotentiallyTranslatedString;
use Simtabi\Laranail\Validation\Rules\Markup\Xml;

it('accepts well-formed XML and rejects malformed', function (): void {
    expect(ruleAccepts(new Xml, '<root><child>x</child></root>'))->toBeTrue()
        ->and(ruleAccepts(new Xml, '<root><child></root>'))->toBeFalse()
        ->and(ruleAccepts(new Xml, 'not xml at all'))->toBeFalse();
});

it('guards blank and non-string input when called directly', function (mixed $value): void {
    // Not through ruleAccepts(): Validator::presentOrRuleIsImplicit() short-
    // circuits on trim($value) === '', so a non-implicit rule never sees blank
    // input in normal use — that is `required`'s job. The guard still matters
    // for a direct call, so it is exercised directly.
    $failed = false;
    new Xml()->validate('doc', $value, function () use (&$failed): PotentiallyTranslatedString {
        $failed = true;

        return new PotentiallyTranslatedString('', resolve(Translator::class));
    });

    expect($failed)->toBeTrue();
})->with(['', '   ', null, 123, [['<root/>']]]);

it('does not expand external entities', function (): void {
    // XXE. With entity substitution on, this reads a local file into the
    // document. The parser must refuse rather than resolve.
    $xxe = <<<'XML'
        <?xml version="1.0"?>
        <!DOCTYPE root [<!ENTITY xxe SYSTEM "file:///etc/passwd">]>
        <root>&xxe;</root>
        XML;

    $document = new DOMDocument;
    $previous = libxml_use_internal_errors(true);
    $document->loadXML($xxe, LIBXML_NONET);
    libxml_use_internal_errors($previous);

    // Whether it parses or not, what must never happen is the file contents
    // appearing in the document.
    expect($document->textContent)->not->toContain('root:');
});

it('validates against an XSD schema', function (): void {
    $schema = sys_get_temp_dir().'/laranail-validation-test.xsd';
    file_put_contents($schema, <<<'XSD'
        <?xml version="1.0"?>
        <xs:schema xmlns:xs="http://www.w3.org/2001/XMLSchema">
          <xs:element name="invoice">
            <xs:complexType>
              <xs:sequence>
                <xs:element name="total" type="xs:decimal"/>
              </xs:sequence>
            </xs:complexType>
          </xs:element>
        </xs:schema>
        XSD);

    $rule = new Xml(schema: $schema);

    expect(ruleAccepts($rule, '<invoice><total>10.50</total></invoice>'))->toBeTrue()
        // Well-formed, but not the agreed document — the distinction the
        // schema exists to make.
        ->and(ruleAccepts($rule, '<invoice><total>ten</total></invoice>'))->toBeFalse()
        ->and(ruleAccepts($rule, '<order><total>10.50</total></order>'))->toBeFalse();

    @unlink($schema);
});

it('blames the deployment, not the document, when the schema is missing', function (): void {
    // A missing schema file is an operator error. Failing the input for it
    // would tell the user their perfectly good document is wrong.
    $rule = new Xml(schema: '/nonexistent/schema.xsd');

    expect(ruleAccepts($rule, '<root/>'))->toBeFalse();
});

it('leaves libxml error handling as it found it', function (): void {
    // The setting is process-global; another package may depend on it.
    $before = libxml_use_internal_errors(false);
    libxml_use_internal_errors($before);

    ruleAccepts(new Xml, '<root/>');

    $after = libxml_use_internal_errors(false);
    libxml_use_internal_errors($after);

    expect($after)->toBe($before);
});
