<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Validation\Support\Email;

use RuntimeException;

/**
 * Reads a one-entry-per-line data file into a lookup hash.
 *
 * Flipped rather than kept as a list: `in_array()` over 8,201 domains is a
 * linear scan on every validated address, and `isset()` on a hash is not.
 *
 * @internal
 */
final class DomainFile
{
    /**
     * @return array<string, true>
     */
    public static function load(string $path): array
    {
        $contents = @file_get_contents($path);

        if ($contents === false) {
            throw new RuntimeException("Unable to read the bundled data file at [{$path}].");
        }

        $entries = [];

        foreach (explode("\n", $contents) as $line) {
            $line = strtolower(trim($line));

            // `#` starts a comment; the files carry a provenance header.
            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }

            $entries[$line] = true;
        }

        return $entries;
    }
}
