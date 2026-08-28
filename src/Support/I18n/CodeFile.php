<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Validation\Support\I18n;

use RuntimeException;
use Simtabi\Laranail\Validation\Support\Email\DomainFile;

/**
 * Reads a one-entry-per-line code file into a lookup hash.
 *
 * The sibling of {@see DomainFile},
 * with one deliberate difference: codes keep their case. ISO code sets are
 * case-canonical (`US`, `usd` is wrong), and the strict-by-default rules
 * depend on the dataset answering exactly — folding happens in the rule,
 * only when asked.
 *
 * @internal
 */
final class CodeFile
{
    /**
     * A plain set: one code per line, `#` comments skipped.
     *
     * @return array<string, true>
     */
    public static function load(string $path): array
    {
        $entries = [];

        foreach (self::lines($path) as $line) {
            $entries[$line] = true;
        }

        return $entries;
    }

    /**
     * A two-column map: `KEY VALUE` per line; a `-` value means "none" and
     * the row is kept key-only (value dropped).
     *
     * @return array<string, string>
     */
    public static function loadMap(string $path): array
    {
        $entries = [];

        foreach (self::lines($path) as $line) {
            [$key, $value] = explode(' ', $line, 2);

            if ($value !== '-') {
                $entries[$key] = $value;
            }
        }

        return $entries;
    }

    /** @return list<string> */
    private static function lines(string $path): array
    {
        $contents = @file_get_contents($path);

        if ($contents === false) {
            throw new RuntimeException("Unable to read the bundled data file at [{$path}].");
        }

        $lines = [];

        foreach (explode("\n", $contents) as $line) {
            $line = trim($line);

            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }

            $lines[] = $line;
        }

        return $lines;
    }
}
