<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Validation\Tests;

use SplFileInfo;
use RecursiveIteratorIterator;
use RecursiveDirectoryIterator;

/**
 * Every message key a rule can emit must resolve to a real sentence.
 *
 * This failed silently for the whole life of the package. The provider never
 * called `hasTranslations()`, so `laranail/validation::validation.iban` was
 * never a registered namespace and `trans()` handed the key straight back —
 * meaning a user who typed a bad IBAN was shown the literal string
 * `laranail/validation::validation.iban`.
 *
 * Nothing caught it because that *is* a string, so every assertion of the form
 * "the field has an error" still passed.
 */
final class RuleMessagesResolveTest extends TestCase
{
    /**
     * The one message that is about SEVERAL fields rather than one.
     *
     * `PersonNameSchema`'s at-least-one requirement is attached to a single
     * field for the mundane reason that attaching it to all of them reports one
     * mistake three times — but what it asks for is any of them. Naming the
     * carrier would be worse than naming none: "The first name field: please
     * provide at least one of first name, middle name or last name" tells the
     * user to fill in the box they just declined to fill in. It names every
     * field through `:values` instead.
     */
    private const string NAMES_SEVERAL_FIELDS = 'laranail/validation::validation.person_name_required';

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

    public function test_the_namespace_is_the_composer_package_name(): void
    {
        // Reversed deliberately. The earlier reasoning here was that a slash nests
        // the published files a level deeper than vendor:publish expects -- but
        // Laravel interpolates the namespace into the override path itself
        // (FileLoader::loadNamespaceOverrides reads
        // {$path}/vendor/{$namespace}/{$locale}/{$group}.php), so the nesting is
        // exactly where it reads them back from. The slash groups a vendor's
        // packages under one directory instead of scattering them across the
        // lang/vendor root, and names the composer package that ships the string.
        // Botble's CMS has shipped this shape platform-wide for years.
        //
        // Blade component tags are the one registry that cannot spell it; this
        // package registers none.
        //
        // Asserted through trans() in both directions rather than through the
        // translator's hasForLocale(): that method is on the concrete
        // Translator and not on the contract, so reaching it means either a
        // string container key or a type-hint that does not declare it.
        $slashed = 'laranail/validation::validation.iban';
        $dashed = 'laranail-validation::validation.iban';

        $this->assertNotSame($slashed, trans($slashed), 'The composer-package namespace did not resolve.');
        $this->assertSame($dashed, trans($dashed), 'The dashed namespace resolved, so both are registered.');
    }

    /**
     * Every shipped locale carries every key the English file does — the
     * §14.11 completeness bar. A partial translation is a red build, not a
     * file that quietly falls back to English for half its rules. Trivial
     * while only `en` ships; binding the day a contributed locale lands.
     */
    public function test_every_shipped_locale_is_complete(): void
    {
        $root = dirname(__DIR__) . '/resources/lang';
        $reference = require $root . '/en/validation.php';
        $this->assertIsArray($reference);

        $locales = glob($root . '/*', GLOB_ONLYDIR);
        $this->assertNotFalse($locales);

        foreach ($locales as $localeDir) {
            $locale = basename($localeDir);
            $messages = require $localeDir . '/validation.php';
            $this->assertIsArray($messages, "{$locale}/validation.php did not return an array.");

            $missing = array_diff(array_keys($reference), array_keys($messages));
            $extra = array_diff(array_keys($messages), array_keys($reference));

            $this->assertSame([], array_values($missing), "Locale {$locale} is missing keys.");
            $this->assertSame([], array_values($extra), "Locale {$locale} has keys en does not — add them to en first.");
        }
    }

    public function test_no_message_is_left_as_a_placeholder(): void
    {
        foreach ($this->keysReferencedInSource() as $key) {
            $message = trans($key);

            $this->assertIsString($message, "{$key} did not resolve to a string.");

            $names = $key === self::NAMES_SEVERAL_FIELDS ? ':values' : ':attribute';

            $this->assertStringContainsString($names, $message, "{$key} never names the field it is about.");
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
                "#'(laranail/validation::validation\.[a-z_]+)'#",
                (string) file_get_contents($file->getPathname()),
                $matches,
            );

            foreach ($matches[1] as $key) {
                $keys[$key] = true;
            }
        }

        // CaseStyle appends its style to the key, so the literal in source is a
        // prefix rather than a whole key. Expanded here rather than skipped.
        unset($keys['laranail/validation::validation.case_style']);

        foreach (['camel', 'kebab', 'pascal', 'snake', 'title'] as $style) {
            $keys["laranail/validation::validation.case_style.{$style}"] = true;
        }

        return array_keys($keys);
    }
}
