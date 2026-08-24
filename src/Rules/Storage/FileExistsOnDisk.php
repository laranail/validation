<?php declare(strict_types=1);

namespace Simtabi\Laranail\Validation\Rules\Storage;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Facades\Storage;

/**
 * The value names a file that exists on a Laravel disk, optionally scoped
 * to a directory:
 *
 *     new FileExistsOnDisk('uploads', 'avatars')
 *
 * The scope is a SECURITY boundary, not a prefix: the value is rejected
 * outright if it could step outside it — `..` segments, an absolute path,
 * backslashes, a null byte. `../top-level.txt` may well exist on the
 * disk; that is precisely why the legacy rule's bare concatenation was a
 * traversal hole, and why the answer is refusal rather than
 * normalisation.
 *
 * Storage tier: one metadata read on the named disk. Local disks make it
 * cheap; on an S3-style disk this is a network round-trip per validated
 * value — know which disk the rule points at.
 */
final readonly class FileExistsOnDisk implements ValidationRule
{
    public function __construct(
        private string $disk,
        private string $directory = '',
    ) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || ! $this->passes($value)) {
            $fail('laranail-validation::validation.file_exists_on_disk')->translate();
        }
    }

    private function passes(string $value): bool
    {
        if ($value === '' || str_contains($value, "\0") || str_contains($value, '\\')
            || str_starts_with($value, '/')
            || preg_match('#(?:^|/)\.\.(?:/|$)#', $value) === 1) {
            return false;
        }

        $path = $this->directory === '' ? $value : rtrim($this->directory, '/') . '/' . $value;

        return Storage::disk($this->disk)->exists($path);
    }
}
