<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Validation\Rules\Identifiers;

use Closure;
use InvalidArgumentException;
use Illuminate\Contracts\Validation\ValidationRule;
use Simtabi\Laranail\Validation\Contracts\ClientCheckable;

/**
 * A hex digest of a named hash algorithm — `HashDigest('sha256')` for a
 * webhook signature column, a content checksum, a cache key.
 *
 * A digest's only checkable properties are its charset and its length, so
 * that is what is checked: hex (either case) at exactly the algorithm's
 * width. Length comes from a table rather than `strlen(hash($algo, ''))`
 * computed at runtime, because the table also defines which algorithms the
 * rule ADMITS — an unknown name is a configuration typo and throws at
 * construction with the known names in the message, instead of validating
 * nothing forever (the legacy rule swallowed it in a catch and returned
 * false, which reads as "every value is invalid" with no hint why).
 *
 * This proves shape, not provenance: a well-formed digest says nothing about
 * what was hashed. Pure tier — no IO.
 */
final readonly class HashDigest implements ClientCheckable, ValidationRule
{
    /** Hex-digest widths per admitted algorithm. */
    private const array LENGTHS = [
        'adler32'    => 8,
        'crc32'      => 8,
        'crc32b'     => 8,
        'crc32c'     => 8,
        'md4'        => 32,
        'md5'        => 32,
        'ripemd128'  => 32,
        'ripemd160'  => 40,
        'ripemd256'  => 64,
        'ripemd320'  => 80,
        'sha1'       => 40,
        'sha224'     => 56,
        'sha256'     => 64,
        'sha384'     => 96,
        'sha512'     => 128,
        'sha512/224' => 56,
        'sha512/256' => 64,
        'sha3-224'   => 56,
        'sha3-256'   => 64,
        'sha3-384'   => 96,
        'sha3-512'   => 128,
        'tiger128'   => 32,
        'tiger160'   => 40,
        'tiger192'   => 48,
        'whirlpool'  => 128,
        'xxh32'      => 8,
        'xxh64'      => 16,
        'xxh128'     => 32,
    ];

    private int $length;

    public function __construct(string $algorithm)
    {
        $this->length = self::LENGTHS[strtolower($algorithm)]
            ?? throw new InvalidArgumentException(sprintf(
                'Unknown hash algorithm [%s]. Known: %s.',
                $algorithm,
                implode(', ', array_keys(self::LENGTHS)),
            ));
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || preg_match($this->pattern(), $value) !== 1) {
            $fail('laranail/validation::validation.hash_digest')->translate(['length' => $this->length]);
        }
    }

    public function clientRules(): array
    {
        return [['rule' => 'regex', 'params' => ['pattern' => $this->pattern()]]];
    }

    private function pattern(): string
    {
        return '/^[a-fA-F0-9]{' . $this->length . '}$/D';
    }
}
