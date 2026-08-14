<?php declare(strict_types=1);

namespace Simtabi\Laranail\Validation\Tests;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

/**
 * Every message key a rule can emit must resolve to a real sentence.
 *
 * This failed silently for the whole life of the package. The provider never
 * called `hasTranslations()`, so `laranail-validation::validation.iban` was
 * never a registered namespace and `trans()` handed the key straight back —
 * meaning a user who typed a bad IBAN was shown the literal string
 * `laranail-validation::validation.iban`.
 *
 * Nothing caught it because that *is* a string, so every assertion of the form
 * "the field has an error" still passed.
 */
final class RuleMessagesResolveTest extends TestCase
{
    public function test_every_key_the_rules_reference_resolves(): void
    {
        $unresolved = [];

        foreach ($this->keysReferencedInSource() as $key) {
            if (trans($key) === $key) {
                $unresolved[] = $key;
            }
        }

        $this->assertSame([], $unresolved, 'These keys returned themselves instead of a message.');
    }

    public function test_the_namespace_is_dashed_not_slashed(): void
    {
        // `lang/vendor/{namespace}` is a single published directory, so a slash
        // nests the files a level deeper than vendor:publish and every
        // consumer's override path expect. The rules all say `laranail-`.
        $this->assertTrue(app('translator')->hasForLocale('laranail-validation::validation.iban', 'en'));
    }

    public function test_no_message_is_left_as_a_placeholder(): void
    {
        foreach ($this->keysReferencedInSource() as $key) {
            $message = trans($key);

            $this->assertIsString($message, "{$key} did not resolve to a string.");
            $this->assertStringContainsString(':attribute', $message, "{$key} never names the field it is about.");
        }
    }

    /**
     * @return list<string>
     */
    private function keysReferencedInSource(): array
    {
        $keys = [];
        $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(dirname(__DIR__) . '/src'));

        foreach ($files as $file) {
            // RecursiveIteratorIterator is typed as yielding mixed, and the
            // narrowing is what tells file_get_contents it has a path.
            if (! $file instanceof SplFileInfo || $file->getExtension() !== 'php') {
                continue;
            }

            preg_match_all(
                "/'(laranail-validation::validation\.[a-z_]+)'/",
                (string) file_get_contents($file->getPathname()),
                $matches,
            );

            foreach ($matches[1] as $key) {
                $keys[$key] = true;
            }
        }

        // CaseStyle appends its style to the key, so the literal in source is a
        // prefix rather than a whole key. Expanded here rather than skipped.
        unset($keys['laranail-validation::validation.case_style']);

        foreach (['camel', 'kebab', 'pascal', 'snake', 'title'] as $style) {
            $keys["laranail-validation::validation.case_style.{$style}"] = true;
        }

        return array_keys($keys);
    }
}
